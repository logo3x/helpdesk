<?php

namespace App\Services;

use App\Models\EscalationLog;
use App\Models\SlaConfig;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\SlaAlertNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * SLA engine — computes due dates, elapsed business time, and breach detection.
 *
 * Business hours: America/Bogota, Mon-Fri 08:00-18:00 (600 min/day).
 * Configurable via config('app.timezone') and constants below.
 */
class SlaService
{
    protected const WORK_START = 8;  // 08:00

    protected const WORK_END = 18;   // 18:00

    protected const MINUTES_PER_DAY = 600; // 10 hours × 60

    /**
     * Attach the matching SLA config to a ticket and compute due dates.
     * Called from TicketService::create() after ticket is persisted.
     *
     * El parámetro $from permite recomputar el SLA tomando como origen
     * una fecha distinta a now() — útil al recalibrar la prioridad de
     * un ticket existente, donde el reloj SLA arrancó en created_at y
     * no debe "reiniciarse" al recalcular.
     */
    public function attachSla(Ticket $ticket, ?CarbonInterface $from = null): void
    {
        $sla = SlaConfig::findFor($ticket->department_id, $ticket->priority);

        if ($sla === null) {
            return;
        }

        $from ??= now();

        $ticket->update([
            'sla_config_id' => $sla->id,
            'first_response_due_at' => $this->addBusinessMinutes($from, $sla->first_response_minutes),
            'resolution_due_at' => $this->addBusinessMinutes($from, $sla->resolution_minutes),
        ]);
    }

    /**
     * Compute the total business minutes elapsed since $from, up to $until.
     * Accounts for weekends and non-working hours.
     */
    public function businessMinutesBetween(CarbonInterface $from, CarbonInterface $until): int
    {
        $from = $from->copy()->setTimezone(config('app.timezone'));
        $until = $until->copy()->setTimezone(config('app.timezone'));

        if ($until->lte($from)) {
            return 0;
        }

        $minutes = 0;
        $cursor = $from->copy();

        while ($cursor->lt($until)) {
            if ($this->isBusinessDay($cursor)) {
                $dayStart = $cursor->copy()->setTime(self::WORK_START, 0);
                $dayEnd = $cursor->copy()->setTime(self::WORK_END, 0);

                $effectiveStart = $cursor->max($dayStart);
                $effectiveEnd = $until->min($dayEnd);

                if ($effectiveStart->lt($effectiveEnd)) {
                    $minutes += (int) $effectiveStart->diffInMinutes($effectiveEnd);
                }
            }

            $cursor = $cursor->copy()->addDay()->setTime(self::WORK_START, 0);
        }

        return $minutes;
    }

    /**
     * Add $minutes of business time to a starting point.
     */
    public function addBusinessMinutes(CarbonInterface $from, int $minutes): CarbonInterface
    {
        $cursor = $from->copy()->setTimezone(config('app.timezone'));
        $remaining = $minutes;

        // If starting outside business hours, fast-forward to next business start
        $cursor = $this->fastForwardToBusinessHours($cursor);

        while ($remaining > 0) {
            if (! $this->isBusinessDay($cursor)) {
                $cursor = $cursor->copy()->addDay()->setTime(self::WORK_START, 0);

                continue;
            }

            $dayEnd = $cursor->copy()->setTime(self::WORK_END, 0);
            $availableToday = (int) $cursor->diffInMinutes($dayEnd);

            if ($availableToday <= 0) {
                $cursor = $cursor->copy()->addDay()->setTime(self::WORK_START, 0);

                continue;
            }

            if ($remaining <= $availableToday) {
                return $cursor->addMinutes($remaining);
            }

            $remaining -= $availableToday;
            $cursor = $cursor->copy()->addDay()->setTime(self::WORK_START, 0);
        }

        return $cursor;
    }

    /**
     * Check all open tickets for SLA breaches and record escalation logs.
     * Called by CheckSlaBreachesJob every 5 minutes.
     *
     * @return int Number of escalations created
     */
    public function checkBreaches(?CarbonInterface $now = null): int
    {
        $escalations = 0;
        $now ??= now();

        $tickets = Ticket::query()
            ->open()
            ->whereNotNull('sla_config_id')
            ->with('slaConfig')
            ->get();

        foreach ($tickets as $ticket) {
            $escalations += $this->checkFirstResponseBreach($ticket, $now);
            $escalations += $this->checkResolutionBreach($ticket, $now);
        }

        return $escalations;
    }

    protected function checkFirstResponseBreach(Ticket $ticket, CarbonInterface $now): int
    {
        if ($ticket->first_responded_at !== null || $ticket->first_response_due_at === null) {
            return 0;
        }

        $elapsed = $this->elapsedBusinessMinutes($ticket, $now);
        $limit = $ticket->slaConfig->first_response_minutes;

        return $this->evaluateThresholds($ticket, 'first_response', $elapsed, $limit);
    }

    protected function checkResolutionBreach(Ticket $ticket, CarbonInterface $now): int
    {
        if ($ticket->resolved_at !== null || $ticket->resolution_due_at === null) {
            return 0;
        }

        $elapsed = $this->elapsedBusinessMinutes($ticket, $now);
        $limit = $ticket->slaConfig->resolution_minutes;

        return $this->evaluateThresholds($ticket, 'resolution', $elapsed, $limit);
    }

    /**
     * Minutos hábiles que lleva corriendo el reloj SLA de un ticket abierto:
     * descuenta las pausas ya cerradas (paused_minutes) y también la pausa
     * en curso (paused_at → $now), para que un ticket en «Pendiente de
     * cliente» no siga consumiendo plazo ni dispare avisos mientras espera.
     */
    protected function elapsedBusinessMinutes(Ticket $ticket, CarbonInterface $now): int
    {
        $elapsed = $this->businessMinutesBetween($ticket->created_at, $now)
            - (int) $ticket->paused_minutes;

        if ($ticket->paused_at !== null) {
            $elapsed -= $this->businessMinutesBetween($ticket->paused_at, $now);
        }

        return max(0, $elapsed);
    }

    /**
     * Create escalation logs at 70%, 90%, and 100% thresholds.
     * Idempotent — skips if an escalation log of that type already exists.
     *
     * Si en la misma pasada se crean varios registros del mismo reloj
     * (p. ej. un ticket que ya superó el 100 % sin registros previos),
     * solo se avisa del umbral más alto recién creado.
     */
    protected function evaluateThresholds(Ticket $ticket, string $prefix, int $elapsed, int $limit): int
    {
        /** @var list<EscalationLog> $createdLogs */
        $createdLogs = [];

        $thresholds = [
            ['pct' => 100, 'type' => "{$prefix}_breach"],
            ['pct' => 90, 'type' => "warning_90_{$prefix}"],
            ['pct' => 70, 'type' => "warning_70_{$prefix}"],
        ];

        foreach ($thresholds as $t) {
            $thresholdMinutes = (int) ($limit * $t['pct'] / 100);

            if ($elapsed < $thresholdMinutes) {
                continue;
            }

            $alreadyLogged = $ticket->escalationLogs()
                ->where('type', $t['type'])
                ->exists();

            if ($alreadyLogged) {
                continue;
            }

            $createdLogs[] = EscalationLog::create([
                'ticket_id' => $ticket->id,
                'type' => $t['type'],
                'sla_minutes' => $limit,
                'elapsed_minutes' => $elapsed,
                'notified_user_id' => $ticket->assigned_to_id,
            ]);

            // Mark breach flag on the ticket
            if ($t['pct'] === 100) {
                $field = "{$prefix}_breached";
                $ticket->{$field} = true;
                $ticket->save();
            }
        }

        if ($createdLogs !== []) {
            $this->notifyAlert($ticket, $createdLogs[0]);
        }

        return count($createdLogs);
    }

    /**
     * Avisa (campanita y, según config, correo) a los destinatarios de
     * una alerta SLA y deja constancia en el registro de escalación.
     */
    public function notifyAlert(Ticket $ticket, EscalationLog $log): void
    {
        if (! config('tickets.sla_alerts.enabled', true)) {
            return;
        }

        $recipients = $this->alertRecipients($ticket, $log);

        if ($recipients->isEmpty()) {
            $log->update(['notes' => 'Sin destinatarios: el ticket no tiene asignado ni supervisores activos en su departamento.']);

            return;
        }

        $notification = new SlaAlertNotification($log->setRelation('ticket', $ticket));

        foreach ($recipients as $recipient) {
            $recipient->notify($notification);
        }

        $log->update([
            'notified_user_id' => $recipients->firstWhere('id', $ticket->assigned_to_id)?->id ?? $recipients->first()->id,
            'notes' => mb_substr('Aviso enviado a: '.$recipients->pluck('name')->implode(', '), 0, 1000),
        ]);
    }

    /**
     * Quién recibe el aviso de una alerta SLA:
     *  - 70 % y 90 %: el agente asignado; si no hay, los supervisores
     *    activos del departamento del ticket.
     *  - Incumplimiento: el asignado y los supervisores del departamento
     *    (y los administradores si notify_admins_on_breach está activo).
     *
     * Solo usuarios activos y sin duplicados.
     *
     * @return Collection<int, User>
     */
    public function alertRecipients(Ticket $ticket, EscalationLog $log): Collection
    {
        $assignee = $ticket->assigned_to_id !== null
            ? $this->activeUsers()->whereKey($ticket->assigned_to_id)->first()
            : null;

        $recipients = new Collection(array_filter([$assignee]));

        if ($log->isBreach() || $assignee === null) {
            $recipients = $recipients->merge($this->activeDepartmentSupervisors($ticket->department_id));
        }

        if ($log->isBreach() && config('tickets.sla_alerts.notify_admins_on_breach', false)) {
            $recipients = $recipients->merge(
                $this->activeUsers()
                    ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', ['admin', 'super_admin']))
                    ->get()
            );
        }

        return $recipients->unique('id')->values();
    }

    /**
     * Supervisores de soporte activos de un departamento.
     *
     * @return Collection<int, User>
     */
    protected function activeDepartmentSupervisors(?int $departmentId): Collection
    {
        if ($departmentId === null) {
            return new Collection;
        }

        return $this->activeUsers()
            ->where('department_id', $departmentId)
            ->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'supervisor_soporte'))
            ->get();
    }

    /**
     * Usuarios que pueden recibir avisos: excluye a los retirados
     * (sincronización con Kactus) y al usuario de sistema, que no tiene buzón.
     *
     * @return Builder<User>
     */
    protected function activeUsers(): Builder
    {
        return User::query()
            ->where(fn (Builder $status) => $status
                ->whereNull('employment_status')
                ->orWhere('employment_status', '!=', 'terminated'))
            ->where(fn (Builder $email) => $email
                ->whereNull('email')
                ->orWhere('email', '!=', KbSuggestionService::SYSTEM_USER_EMAIL));
    }

    /**
     * Formatea minutos hábiles para mostrar en reportes: "45 min",
     * "2 h", "2 h 30 min".
     */
    public static function formatMinutes(int $minutes): string
    {
        $minutes = max(0, $minutes);

        if ($minutes < 60) {
            return "{$minutes} min";
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest > 0 ? "{$hours} h {$rest} min" : "{$hours} h";
    }

    protected function isBusinessDay(CarbonInterface $date): bool
    {
        return $date->isWeekday();
    }

    protected function fastForwardToBusinessHours(CarbonInterface $cursor): CarbonInterface
    {
        // Move to next weekday if on weekend
        while (! $this->isBusinessDay($cursor)) {
            $cursor = $cursor->copy()->addDay()->setTime(self::WORK_START, 0);
        }

        // Before business hours → move to start
        if ($cursor->hour < self::WORK_START) {
            return $cursor->setTime(self::WORK_START, 0);
        }

        // After business hours → move to next day start
        if ($cursor->hour >= self::WORK_END) {
            return $cursor->copy()->addDay()->setTime(self::WORK_START, 0);
        }

        return $cursor;
    }
}
