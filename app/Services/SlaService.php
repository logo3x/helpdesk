<?php

namespace App\Services;

use App\Models\EscalationLog;
use App\Models\SlaConfig;
use App\Models\Ticket;
use Carbon\CarbonInterface;

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
     */
    public function attachSla(Ticket $ticket): void
    {
        $sla = SlaConfig::findFor($ticket->department_id, $ticket->priority);

        if ($sla === null) {
            return;
        }

        $now = now();

        $ticket->update([
            'sla_config_id' => $sla->id,
            'first_response_due_at' => $this->addBusinessMinutes($now, $sla->first_response_minutes),
            'resolution_due_at' => $this->addBusinessMinutes($now, $sla->resolution_minutes),
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

        $elapsed = $this->businessMinutesBetween($ticket->created_at, $now)
            - $ticket->paused_minutes;
        $limit = $ticket->slaConfig->first_response_minutes;

        return $this->evaluateThresholds($ticket, 'first_response', $elapsed, $limit);
    }

    protected function checkResolutionBreach(Ticket $ticket, CarbonInterface $now): int
    {
        if ($ticket->resolved_at !== null || $ticket->resolution_due_at === null) {
            return 0;
        }

        $elapsed = $this->businessMinutesBetween($ticket->created_at, $now)
            - $ticket->paused_minutes;
        $limit = $ticket->slaConfig->resolution_minutes;

        return $this->evaluateThresholds($ticket, 'resolution', $elapsed, $limit);
    }

    /**
     * Create escalation logs at 70%, 90%, and 100% thresholds.
     * Idempotent — skips if an escalation log of that type already exists.
     */
    protected function evaluateThresholds(Ticket $ticket, string $prefix, int $elapsed, int $limit): int
    {
        $created = 0;

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

            EscalationLog::create([
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

            $created++;
        }

        return $created;
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
