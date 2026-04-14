<?php

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketUrgency;
use App\Models\Category;
use App\Models\Department;
use App\Models\SlaConfig;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaService;
use App\Services\TicketService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->sla = app(SlaService::class);
});

describe('business minutes calculation', function () {
    it('counts zero minutes for same instant', function () {
        Carbon::setTestNow('2026-04-14 10:00:00');
        $now = now();

        expect($this->sla->businessMinutesBetween($now, $now))->toBe(0);
    });

    it('counts minutes within a single business day', function () {
        $from = Carbon::parse('2026-04-14 09:00:00'); // Tuesday
        $until = Carbon::parse('2026-04-14 11:30:00');

        expect($this->sla->businessMinutesBetween($from, $until))->toBe(150);
    });

    it('excludes weekend days', function () {
        $friday = Carbon::parse('2026-04-17 17:00:00'); // Friday 5pm
        $monday = Carbon::parse('2026-04-20 09:00:00'); // Monday 9am

        // Friday: 1 hr (17:00-18:00) + Monday: 1 hr (08:00-09:00)
        expect($this->sla->businessMinutesBetween($friday, $monday))->toBe(120);
    });

    it('excludes time outside business hours', function () {
        $from = Carbon::parse('2026-04-14 06:00:00'); // Before work
        $until = Carbon::parse('2026-04-14 20:00:00'); // After work

        // Only 08:00-18:00 = 600 minutes
        expect($this->sla->businessMinutesBetween($from, $until))->toBe(600);
    });

    it('spans multiple business days', function () {
        $from = Carbon::parse('2026-04-14 08:00:00'); // Tuesday start
        $until = Carbon::parse('2026-04-16 18:00:00'); // Thursday end

        // 3 full days × 600 = 1800
        expect($this->sla->businessMinutesBetween($from, $until))->toBe(1800);
    });
});

describe('addBusinessMinutes', function () {
    it('adds within same day', function () {
        $start = Carbon::parse('2026-04-14 10:00:00');
        $result = $this->sla->addBusinessMinutes($start, 120);

        expect($result->format('Y-m-d H:i'))->toBe('2026-04-14 12:00');
    });

    it('rolls over to next business day', function () {
        $start = Carbon::parse('2026-04-14 17:00:00'); // 1hr left in day
        $result = $this->sla->addBusinessMinutes($start, 120); // need 2 hrs

        // 1hr today, 1hr tomorrow: next day 09:00
        expect($result->format('Y-m-d H:i'))->toBe('2026-04-15 09:00');
    });

    it('skips weekends', function () {
        $friday = Carbon::parse('2026-04-17 17:00:00'); // 1hr left
        $result = $this->sla->addBusinessMinutes($friday, 120); // 2 hrs

        // 1hr Friday + skip Sat+Sun + 1hr Monday
        expect($result->format('Y-m-d H:i'))->toBe('2026-04-20 09:00');
    });
});

describe('SLA attachment on ticket creation', function () {
    it('sets due dates from SLA config when department matches', function () {
        Carbon::setTestNow('2026-04-14 10:00:00'); // Tuesday 10am

        $dept = Department::factory()->create();
        SlaConfig::create([
            'department_id' => $dept->id,
            'priority' => TicketPriority::Critica->value,
            'first_response_minutes' => 30,
            'resolution_minutes' => 240,
            'is_active' => true,
        ]);

        $user = User::factory()->create(['department_id' => $dept->id]);
        $cat = Category::factory()->create(['department_id' => $dept->id]);

        $ticket = app(TicketService::class)->create($user, [
            'subject' => 'Server down',
            'description' => 'Production server unreachable',
            'impact' => TicketImpact::Alto,
            'urgency' => TicketUrgency::Alta,
            'category_id' => $cat->id,
            'department_id' => $dept->id,
        ]);

        $fresh = $ticket->fresh();
        expect($fresh->sla_config_id)->not->toBeNull();
        expect($fresh->first_response_due_at->format('H:i'))->toBe('10:30');
        expect($fresh->resolution_due_at->format('H:i'))->toBe('14:00');
    });
});

describe('breach detection', function () {
    it('creates escalation logs at 70, 90 and 100 percent', function () {
        Carbon::setTestNow('2026-04-14 08:00:00');

        $dept = Department::factory()->create();
        $sla = SlaConfig::create([
            'department_id' => $dept->id,
            'priority' => TicketPriority::Media->value,
            'first_response_minutes' => 100,
            'resolution_minutes' => 200,
            'is_active' => true,
        ]);

        $ticket = Ticket::factory()->create([
            'department_id' => $dept->id,
            'sla_config_id' => $sla->id,
            'priority' => TicketPriority::Media,
            'first_response_due_at' => now()->addMinutes(100),
            'resolution_due_at' => now()->addMinutes(200),
            'created_at' => now(),
        ]);

        // At 70 minutes: 70% warning
        Carbon::setTestNow('2026-04-14 09:10:00');
        $this->sla->checkBreaches();
        expect($ticket->escalationLogs()->where('type', 'warning_70_first_response')->exists())->toBeTrue();

        // At 90 minutes: 90% warning
        Carbon::setTestNow('2026-04-14 09:30:00');
        $this->sla->checkBreaches();
        expect($ticket->escalationLogs()->where('type', 'warning_90_first_response')->exists())->toBeTrue();

        // At 100 minutes: breach
        Carbon::setTestNow('2026-04-14 09:40:00');
        $this->sla->checkBreaches();
        expect($ticket->fresh()->first_response_breached)->toBeTrue();
        expect($ticket->escalationLogs()->where('type', 'first_response_breach')->exists())->toBeTrue();

        // Idempotent: running again does not duplicate
        $this->sla->checkBreaches();
        expect($ticket->escalationLogs()->where('type', 'first_response_breach')->count())->toBe(1);
    });
});
