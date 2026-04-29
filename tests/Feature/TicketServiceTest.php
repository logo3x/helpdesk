<?php

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketUrgency;
use App\Models\Category;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketCounter;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->service = app(TicketService::class);
});

describe('numbering', function () {
    it('starts the current year at 00001', function () {
        Carbon::setTestNow('2026-01-15');

        expect($this->service->nextNumber())->toBe('TK-2026-00001');
        expect($this->service->nextNumber())->toBe('TK-2026-00002');
    });

    it('keeps separate counters per year', function () {
        $this->service->nextNumber(2025);
        $this->service->nextNumber(2025);
        $first2026 = $this->service->nextNumber(2026);

        expect($first2026)->toBe('TK-2026-00001');
        expect(TicketCounter::find(2025)->last_number)->toBe(2);
        expect(TicketCounter::find(2026)->last_number)->toBe(1);
    });
});

describe('create', function () {
    it('creates a ticket with numbered sequence, priority from matrix and Nuevo status', function () {
        Carbon::setTestNow('2026-04-13');

        $requester = User::factory()->create();
        $category = Category::factory()->create();

        $ticket = $this->service->create($requester, [
            'subject' => 'El correo no entra',
            'description' => 'Outlook sin conexión desde las 9am',
            'impact' => TicketImpact::Alto,
            'urgency' => TicketUrgency::Alta,
            'category_id' => $category->id,
        ]);

        expect($ticket->number)->toBe('TK-2026-00001');
        expect($ticket->status)->toBe(TicketStatus::Nuevo);
        expect($ticket->priority)->toBe(TicketPriority::Critica);
        expect($ticket->requester_id)->toBe($requester->id);
        expect($ticket->category_id)->toBe($category->id);
    });

    it('defaults to medio/media when impact and urgency are omitted', function () {
        $ticket = $this->service->create(
            User::factory()->create(),
            ['subject' => 'Prueba', 'description' => 'Cuerpo'],
        );

        expect($ticket->impact)->toBe(TicketImpact::Medio);
        expect($ticket->urgency)->toBe(TicketUrgency::Media);
        expect($ticket->priority)->toBe(TicketPriority::Media);
    });
});

describe('lifecycle transitions', function () {
    it('moves from Nuevo to EnProgreso when assigned with auto-comment (default)', function () {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Nuevo]);
        $agent = User::factory()->create();

        $this->service->assign($ticket, $agent);

        // El auto-comment cuenta como primera respuesta, así que pasa
        // directamente a EnProgreso.
        expect($ticket->fresh()->status)->toBe(TicketStatus::EnProgreso);
        expect($ticket->fresh()->assigned_to_id)->toBe($agent->id);
        expect($ticket->fresh()->first_responded_at)->not->toBeNull();
        expect($ticket->fresh()->comments()->where('is_private', false)->count())->toBe(1);
    });

    it('moves from Nuevo to Asignado when assigned without auto-comment', function () {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::Nuevo]);
        $agent = User::factory()->create();

        $this->service->assign($ticket, $agent, autoComment: false);

        expect($ticket->fresh()->status)->toBe(TicketStatus::Asignado);
        expect($ticket->fresh()->assigned_to_id)->toBe($agent->id);
        expect($ticket->fresh()->first_responded_at)->toBeNull();
        expect($ticket->fresh()->comments()->count())->toBe(0);
    });

    it('promotes Asignado to EnProgreso on first response and is idempotent', function () {
        $ticket = Ticket::factory()->assigned()->create();

        $this->service->markFirstResponse($ticket);
        $firstStamp = $ticket->fresh()->first_responded_at;

        expect($ticket->fresh()->status)->toBe(TicketStatus::EnProgreso);
        expect($firstStamp)->not->toBeNull();

        Carbon::setTestNow(now()->addHour());
        $this->service->markFirstResponse($ticket->fresh());

        expect($ticket->fresh()->first_responded_at->equalTo($firstStamp))->toBeTrue();
    });

    it('recalibrates priority from matrix and logs the reason', function () {
        $ticket = Ticket::factory()->assigned()->create([
            'impact' => TicketImpact::Bajo,
            'urgency' => TicketUrgency::Baja,
            'priority' => TicketPriority::Baja,
        ]);

        // Autenticamos para que el activity log capture al causante.
        $supervisor = User::factory()->create();
        $this->actingAs($supervisor);

        $result = $this->service->recalibratePriority(
            $ticket,
            TicketImpact::Alto,
            TicketUrgency::Alta,
            'El solicitante subestimó el impacto; afecta a toda el área.',
        );

        expect($result->impact)->toBe(TicketImpact::Alto);
        expect($result->urgency)->toBe(TicketUrgency::Alta);
        expect($result->priority)->toBe(TicketPriority::Critica);

        $fresh = $ticket->fresh();
        expect($fresh->impact)->toBe(TicketImpact::Alto);
        expect($fresh->priority)->toBe(TicketPriority::Critica);

        $activity = Activity::query()
            ->where('subject_type', Ticket::class)
            ->where('subject_id', $ticket->id)
            ->where('description', 'priority_recalibrated')
            ->latest('id')
            ->first();

        expect($activity)->not->toBeNull();
        expect($activity->causer_id)->toBe($supervisor->id);
        expect($activity->properties['reason'])->toContain('subestimó');
        expect($activity->properties['old']['priority'])->toBe(TicketPriority::Baja->value);
        expect($activity->properties['new']['priority'])->toBe(TicketPriority::Critica->value);
    });

    it('transfers a ticket: creates system event comment, resets assignment and notifies', function () {
        Notification::fake();

        $deptFrom = Department::factory()->create(['name' => 'TI']);
        $deptTo = Department::factory()->create(['name' => 'RRHH']);

        $supervisorRrhh = User::factory()->create(['department_id' => $deptTo->id]);
        $requester = User::factory()->create();
        $supervisorTi = User::factory()->create(['department_id' => $deptFrom->id]);
        $this->actingAs($supervisorTi);

        $ticket = Ticket::factory()->create([
            'department_id' => $deptFrom->id,
            'requester_id' => $requester->id,
            'assigned_to_id' => User::factory()->create()->id,
            'category_id' => Category::factory()->create(['department_id' => $deptFrom->id])->id,
        ]);

        $this->service->transfer(
            ticket: $ticket,
            toDepartment: $deptTo,
            reason: 'Estaba mal clasificado',
            transferredBy: $supervisorTi,
        );

        $fresh = $ticket->fresh();
        expect($fresh->department_id)->toBe($deptTo->id);
        expect($fresh->assigned_to_id)->toBeNull();
        expect($fresh->category_id)->toBeNull();

        $event = TicketComment::where('ticket_id', $ticket->id)
            ->where('is_system_event', true)
            ->where('event_type', 'transferred')
            ->latest('id')
            ->first();

        expect($event)->not->toBeNull();
        expect($event->user_id)->toBe($supervisorTi->id);
        expect($event->is_private)->toBeFalse();
        expect($event->body)->toContain('TI');
        expect($event->body)->toContain('RRHH');
        expect($event->body)->toContain('Estaba mal clasificado');
    });

    it('resolve, close and reopen stamp the expected timestamps', function () {
        $ticket = Ticket::factory()->assigned()->create();

        $this->service->resolve($ticket);
        expect($ticket->fresh()->status)->toBe(TicketStatus::Resuelto);
        expect($ticket->fresh()->resolved_at)->not->toBeNull();

        $this->service->close($ticket->fresh());
        expect($ticket->fresh()->status)->toBe(TicketStatus::Cerrado);
        expect($ticket->fresh()->closed_at)->not->toBeNull();

        $this->service->reopen($ticket->fresh());
        $reopened = $ticket->fresh();
        expect($reopened->status)->toBe(TicketStatus::Reabierto);
        expect($reopened->resolved_at)->toBeNull();
        expect($reopened->closed_at)->toBeNull();
        expect($reopened->reopened_at)->not->toBeNull();
    });
});
