<?php

namespace App\Services;

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketUrgency;
use App\Models\Ticket;
use App\Models\TicketCounter;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates ticket lifecycle operations: numbering, priority derivation
 * and state transitions. Kept free of HTTP/Filament concerns so it can be
 * called from controllers, Livewire components, Filament actions, jobs,
 * or the API agent endpoints interchangeably.
 */
class TicketService
{
    /**
     * Create a new ticket from a validated payload.
     *
     * The caller is responsible for authorization; this service trusts
     * whoever invokes it and focuses on correctness (atomic numbering,
     * priority matrix, default status).
     *
     * @param  array{
     *     subject: string,
     *     description: string,
     *     impact?: TicketImpact|string,
     *     urgency?: TicketUrgency|string,
     *     category_id?: int|null,
     *     department_id?: int|null,
     *     assigned_to_id?: int|null,
     * }  $data
     */
    public function create(User $requester, array $data): Ticket
    {
        $impact = $this->normaliseImpact($data['impact'] ?? TicketImpact::Medio);
        $urgency = $this->normaliseUrgency($data['urgency'] ?? TicketUrgency::Media);
        $priority = TicketPriority::fromMatrix($impact, $urgency);

        return DB::transaction(function () use ($requester, $data, $impact, $urgency, $priority): Ticket {
            return Ticket::create([
                'number' => $this->nextNumber(),
                'subject' => $data['subject'],
                'description' => $data['description'],
                'status' => TicketStatus::Nuevo,
                'priority' => $priority,
                'impact' => $impact,
                'urgency' => $urgency,
                'requester_id' => $requester->id,
                'assigned_to_id' => $data['assigned_to_id'] ?? null,
                'department_id' => $data['department_id'] ?? $requester->department_id,
                'category_id' => $data['category_id'] ?? null,
            ]);
        });
    }

    /**
     * Assign a ticket to a user and move status to "asignado" if it was new.
     */
    public function assign(Ticket $ticket, User $assignee): Ticket
    {
        $ticket->assigned_to_id = $assignee->id;

        if ($ticket->status === TicketStatus::Nuevo) {
            $ticket->status = TicketStatus::Asignado;
        }

        $ticket->save();

        return $ticket;
    }

    /**
     * Record that support has answered for the first time. Idempotent.
     */
    public function markFirstResponse(Ticket $ticket, ?Carbon $at = null): Ticket
    {
        if ($ticket->first_responded_at !== null) {
            return $ticket;
        }

        $ticket->first_responded_at = $at ?? now();

        if ($ticket->status === TicketStatus::Asignado) {
            $ticket->status = TicketStatus::EnProgreso;
        }

        $ticket->save();

        return $ticket;
    }

    public function resolve(Ticket $ticket): Ticket
    {
        $ticket->status = TicketStatus::Resuelto;
        $ticket->resolved_at = now();
        $ticket->save();

        return $ticket;
    }

    public function close(Ticket $ticket): Ticket
    {
        $ticket->status = TicketStatus::Cerrado;
        $ticket->closed_at = now();
        $ticket->save();

        return $ticket;
    }

    public function reopen(Ticket $ticket): Ticket
    {
        $ticket->status = TicketStatus::Reabierto;
        $ticket->reopened_at = now();
        $ticket->resolved_at = null;
        $ticket->closed_at = null;
        $ticket->save();

        return $ticket;
    }

    /**
     * Produce the next ticket number atomically (TK-YYYY-NNNNN).
     *
     * Row-level locked via SELECT ... FOR UPDATE inside a transaction so
     * concurrent requests never collide on the counter. Opening a nested
     * transaction when already inside one is safe — Laravel handles savepoints.
     */
    public function nextNumber(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');

        return DB::transaction(function () use ($year): string {
            /** @var TicketCounter|null $counter */
            $counter = TicketCounter::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if ($counter === null) {
                $counter = TicketCounter::create([
                    'year' => $year,
                    'last_number' => 0,
                ]);
            }

            $counter->last_number += 1;
            $counter->save();

            return sprintf('TK-%d-%05d', $year, $counter->last_number);
        });
    }

    protected function normaliseImpact(TicketImpact|string $value): TicketImpact
    {
        return $value instanceof TicketImpact ? $value : TicketImpact::from($value);
    }

    protected function normaliseUrgency(TicketUrgency|string $value): TicketUrgency
    {
        return $value instanceof TicketUrgency ? $value : TicketUrgency::from($value);
    }
}
