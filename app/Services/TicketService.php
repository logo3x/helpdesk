<?php

namespace App\Services;

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketUrgency;
use App\Jobs\SendSatisfactionSurveyJob;
use App\Models\Category;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketCounter;
use App\Models\User;
use App\Notifications\TicketAssignedNotification;
use App\Notifications\TicketCreatedNotification;
use App\Notifications\TicketReceivedFromTransferNotification;
use App\Notifications\TicketReopenedNotification;
use App\Notifications\TicketResolvedNotification;
use App\Notifications\TicketTransferredNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates ticket lifecycle operations: numbering, priority derivation,
 * SLA attachment, pause/resume tracking and state transitions.
 */

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

        $ticket = DB::transaction(function () use ($requester, $data, $impact, $urgency, $priority): Ticket {
            // El depto del ticket se deriva de la categoría elegida
            // (cada categoría pertenece a un depto). Fallback al depto
            // explícito del payload, y luego al depto del solicitante.
            // Esto asegura que un ticket con categoría "TI - Software"
            // llegue a TI aunque el solicitante sea de otro depto.
            $categoryId = $data['category_id'] ?? null;
            $departmentId = $data['department_id'] ?? null;

            if (! $departmentId && $categoryId) {
                $departmentId = Category::where('id', $categoryId)->value('department_id');
            }

            if (! $departmentId) {
                $departmentId = $requester->department_id;
            }

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
                'department_id' => $departmentId,
                'category_id' => $categoryId,
            ]);
        });

        // Attach SLA due dates based on department × priority
        app(SlaService::class)->attachSla($ticket);

        $requester->notify(new TicketCreatedNotification($ticket));

        return $ticket;
    }

    /**
     * Pause the SLA clock when ticket moves to "pendiente_cliente".
     */
    public function pauseSla(Ticket $ticket): Ticket
    {
        $ticket->status = TicketStatus::PendienteCliente;
        $ticket->paused_at = now();
        $ticket->save();

        return $ticket;
    }

    /**
     * Resume the SLA clock when ticket leaves "pendiente_cliente".
     * Accumulates the paused time into paused_minutes.
     */
    public function resumeSla(Ticket $ticket, TicketStatus $newStatus): Ticket
    {
        if ($ticket->paused_at !== null) {
            $pausedBizMinutes = app(SlaService::class)
                ->businessMinutesBetween($ticket->paused_at, now());
            $ticket->paused_minutes += $pausedBizMinutes;
            $ticket->paused_at = null;
        }

        $ticket->status = $newStatus;
        $ticket->save();

        return $ticket;
    }

    /**
     * Assign a ticket to a user and move status to "asignado" if it was new.
     *
     * Cuando el assignee es un agente/técnico que toma el ticket, además
     * se crea un comentario público automático para que el solicitante
     * sepa que alguien se hizo cargo. Esto resuelve el gap de UX donde
     * el usuario no tenía forma de enterarse hasta recibir un comentario
     * manual.
     */
    public function assign(Ticket $ticket, User $assignee, bool $autoComment = true): Ticket
    {
        $wasUnassigned = $ticket->assigned_to_id === null;

        $ticket->assigned_to_id = $assignee->id;

        if ($ticket->status === TicketStatus::Nuevo) {
            $ticket->status = TicketStatus::Asignado;
        }

        $ticket->save();

        // Comentario público automático al asignar por primera vez
        // (no al reasignar, para evitar ruido).
        if ($autoComment && $wasUnassigned) {
            TicketComment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $assignee->id,
                'body' => "Hola, he tomado tu ticket y lo voy a revisar. Te contacto en breve con novedades. — {$assignee->name}",
                'is_private' => false,
            ]);

            // Este comentario público cuenta como primera respuesta para SLA.
            $this->markFirstResponse($ticket);
        }

        $assignee->notify(new TicketAssignedNotification($ticket));

        return $ticket;
    }

    /**
     * Record that support has answered for the first time. Idempotent.
     *
     * Si el ticket no estaba asignado y se marca primera respuesta,
     * se asume que quien marca lo está tomando implícitamente y se
     * auto-asigna al $firstResponder (o a auth()->user() si no se pasa).
     */
    public function markFirstResponse(Ticket $ticket, ?Carbon $at = null, ?User $firstResponder = null): Ticket
    {
        if ($ticket->first_responded_at !== null) {
            return $ticket;
        }

        // Auto-asignación: si el ticket no tiene agente, quien marca
        // primera respuesta se vuelve el asignado.
        if ($ticket->assigned_to_id === null) {
            $responder = $firstResponder ?? auth()->user();
            if ($responder instanceof User) {
                $ticket->assigned_to_id = $responder->id;
            }
        }

        $ticket->first_responded_at = $at ?? now();

        // Transición de status: si estaba Nuevo o Asignado, pasa a En progreso.
        if (in_array($ticket->status, [TicketStatus::Nuevo, TicketStatus::Asignado], true)) {
            $ticket->status = TicketStatus::EnProgreso;
        }

        $ticket->save();

        return $ticket;
    }

    /**
     * Recalibrate a ticket's priority by providing new impact/urgency
     * values. Recomputes the ITIL matrix priority, re-attaches the SLA
     * (preserving the original created_at as the SLA clock origin so the
     * reloj no se "reinicia") y deja un registro en el activity log con
     * el motivo del cambio.
     *
     * Usado por supervisores/admins cuando la clasificación inicial
     * estaba mal y subestimaba/sobrestimaba la criticidad del ticket.
     */
    public function recalibratePriority(
        Ticket $ticket,
        TicketImpact|string $impact,
        TicketUrgency|string $urgency,
        ?string $reason = null,
    ): Ticket {
        $newImpact = $this->normaliseImpact($impact);
        $newUrgency = $this->normaliseUrgency($urgency);
        $newPriority = TicketPriority::fromMatrix($newImpact, $newUrgency);

        $oldImpact = $ticket->impact;
        $oldUrgency = $ticket->urgency;
        $oldPriority = $ticket->priority;

        $ticket->impact = $newImpact;
        $ticket->urgency = $newUrgency;
        $ticket->priority = $newPriority;
        $ticket->save();

        // Re-anclar SLA preservando el origen del reloj (created_at) para
        // que recalibrar no "regale" tiempo adicional al ticket.
        app(SlaService::class)->attachSla($ticket->fresh(), $ticket->created_at);
        $ticket->refresh();

        activity('tickets')
            ->performedOn($ticket)
            ->causedBy(auth()->user())
            ->withProperties([
                'reason' => $reason,
                'old' => [
                    'impact' => $oldImpact?->value,
                    'urgency' => $oldUrgency?->value,
                    'priority' => $oldPriority?->value,
                ],
                'new' => [
                    'impact' => $newImpact->value,
                    'urgency' => $newUrgency->value,
                    'priority' => $newPriority->value,
                ],
            ])
            ->log('priority_recalibrated');

        return $ticket;
    }

    /**
     * Traslado de un ticket a otro departamento.
     *
     * Centraliza toda la lógica del traslado para que las acciones de
     * /soporte y /admin pasen por aquí y mantengan el mismo
     * comportamiento:
     *
     *   - Reset de asignación y categoría (el equipo destino debe
     *     re-triagear).
     *   - Comentario público "de sistema" en el thread con texto
     *     estandarizado (is_system_event=true) que en la UI se
     *     renderiza como un divisor de timeline en lugar de burbuja.
     *   - Notif al solicitante (TicketTransferredNotification).
     *   - Notif a supervisores del depto destino
     *     (TicketReceivedFromTransferNotification).
     *
     * `$transferredBy` es opcional: si se omite, intenta usar
     * auth()->user() para que el comentario tenga autor.
     */
    public function transfer(
        Ticket $ticket,
        Department $toDepartment,
        ?string $reason = null,
        ?User $transferredBy = null,
    ): Ticket {
        $from = $ticket->department;
        $by = $transferredBy ?? (auth()->user() instanceof User ? auth()->user() : null);

        $ticket->forceFill([
            'department_id' => $toDepartment->id,
            'assigned_to_id' => null,
            'category_id' => null,
        ])->save();

        // Comentario "de sistema" en el thread con autor = quien
        // hizo el traslado. Se marca is_system_event=true para que la
        // UI lo dibuje como divisor en vez de burbuja de chat.
        $body = $this->buildTransferEventBody($from, $toDepartment, $reason);
        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $by?->id,
            'body' => $body,
            'is_private' => false,
            'is_system_event' => true,
            'event_type' => 'transferred',
        ]);

        // Notif al solicitante.
        if ($ticket->requester) {
            $ticket->requester->notify(new TicketTransferredNotification(
                $ticket->refresh(),
                $from,
                $toDepartment,
                $reason,
            ));
        }

        // Notif a supervisores del depto destino.
        $supervisors = User::query()
            ->where('department_id', $toDepartment->id)
            ->whereHas('roles', fn ($q) => $q->where('name', 'supervisor_soporte'))
            ->get();

        foreach ($supervisors as $supervisor) {
            $supervisor->notify(new TicketReceivedFromTransferNotification(
                ticket: $ticket,
                fromDepartment: $from,
                reason: $reason,
                transferredBy: $by?->name,
            ));
        }

        return $ticket;
    }

    /**
     * Construye el cuerpo Markdown del comentario "de sistema" para
     * un traslado. Usa nombres de departamento sanitizados para que
     * no se rompa el render si alguien meta caracteres raros.
     */
    protected function buildTransferEventBody(?Department $from, Department $to, ?string $reason): string
    {
        $sanitize = fn (?string $s): string => trim(strip_tags((string) $s));

        $fromName = $from ? $sanitize($from->name) : 'depto desconocido';
        $toName = $sanitize($to->name);

        $body = "🔄 **Ticket trasladado** de `{$fromName}` → `{$toName}`.";

        if ($reason) {
            $body .= "\n\nMotivo: {$sanitize($reason)}";
        }

        return $body;
    }

    public function resolve(Ticket $ticket): Ticket
    {
        $ticket->status = TicketStatus::Resuelto;
        $ticket->resolved_at = now();
        $ticket->save();

        // Avisar al solicitante para que pueda confirmar o reabrir.
        $ticket->loadMissing('requester');
        if ($ticket->requester) {
            $ticket->requester->notify(new TicketResolvedNotification($ticket));
        }

        return $ticket;
    }

    public function close(Ticket $ticket): Ticket
    {
        $ticket->status = TicketStatus::Cerrado;
        $ticket->closed_at = now();
        $ticket->save();

        SendSatisfactionSurveyJob::dispatch($ticket);

        return $ticket;
    }

    public function reopen(Ticket $ticket): Ticket
    {
        $ticket->status = TicketStatus::Reabierto;
        $ticket->reopened_at = now();
        $ticket->resolved_at = null;
        $ticket->closed_at = null;
        $ticket->save();

        // Avisar al agente asignado (si hay) y supervisores del depto
        // para que retomen el trabajo. Si no hay asignado, solo
        // supervisor se entera.
        $ticket->loadMissing('assignee', 'department.users.roles');
        $notified = collect();

        if ($ticket->assignee) {
            $ticket->assignee->notify(new TicketReopenedNotification($ticket));
            $notified->push($ticket->assignee->id);
        }

        $supervisors = $ticket->department?->users
            ?->filter(fn (User $u) => $u->hasRole('supervisor_soporte'))
            ?? collect();

        foreach ($supervisors as $supervisor) {
            if ($notified->contains($supervisor->id)) {
                continue;
            }
            $supervisor->notify(new TicketReopenedNotification($ticket));
        }

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
