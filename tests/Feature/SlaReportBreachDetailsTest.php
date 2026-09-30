<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Pages\SlaReport as AdminSlaReport;
use App\Filament\Soporte\Pages\SlaReport as SoporteSlaReport;
use App\Models\Department;
use App\Models\EscalationLog;
use App\Models\SlaConfig;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->actingAs($this->admin);

    $this->department = Department::factory()->create(['name' => 'Soporte TI', 'is_active' => true]);
    $this->sla = SlaConfig::factory()->create([
        'department_id' => $this->department->id,
        'priority' => TicketPriority::Alta,
        'resolution_minutes' => 120,
    ]);
    $this->agent = User::factory()->create(['name' => 'Laura Agente']);
});

function breachedTicket(array $attributes = []): Ticket
{
    return Ticket::factory()->create(array_merge([
        'subject' => 'Impresora sin conexión',
        'department_id' => test()->department->id,
        'priority' => TicketPriority::Alta,
        'status' => TicketStatus::Resuelto,
        'assigned_to_id' => test()->agent->id,
        'sla_config_id' => test()->sla->id,
        // Martes 09:00 → martes 13:00 = 240 min hábiles contra un límite de 120.
        'created_at' => Carbon::parse('2026-09-22 09:00:00'),
        'resolved_at' => Carbon::parse('2026-09-22 13:00:00'),
        'resolution_breached' => true,
        'paused_minutes' => 0,
    ], $attributes));
}

it('lista los tickets con SLA incumplido con enlace y quién lo atendió', function () {
    Carbon::setTestNow('2026-09-29 10:00:00');
    $ticket = breachedTicket();
    breachedTicket(['subject' => 'Dentro del SLA', 'resolution_breached' => false]);

    Livewire::test(AdminSlaReport::class)
        ->assertOk()
        ->assertSee('Tickets con SLA incumplido (1)')
        ->assertSee($ticket->number)
        ->assertSee(route('filament.admin.resources.tickets.view', ['record' => $ticket->id]), false)
        ->assertSee('Laura Agente')
        ->assertSee('2 h')
        ->assertSee('4 h')
        ->assertDontSee('Dentro del SLA');
});

it('filtra los incumplidos por departamento y prioridad desde la matriz', function () {
    Carbon::setTestNow('2026-09-29 10:00:00');
    $alta = breachedTicket();
    $otroDepto = breachedTicket(['department_id' => Department::factory()->create()->id]);

    Livewire::test(AdminSlaReport::class)
        ->call('filterBreaches', $this->department->id, TicketPriority::Alta->value)
        ->assertSet('breachDepartmentId', $this->department->id)
        ->assertSet('breachPriority', 'alta')
        ->assertSee($alta->number)
        ->assertDontSee($otroDepto->number)
        ->call('clearBreachFilter')
        ->assertSet('breachDepartmentId', null)
        ->assertSee($otroDepto->number);
});

it('muestra las escalaciones en español con el agente y el estado del ticket', function () {
    Carbon::setTestNow('2026-09-29 10:00:00');
    $ticket = breachedTicket();

    EscalationLog::create([
        'ticket_id' => $ticket->id,
        'type' => 'resolution_breach',
        'sla_minutes' => 120,
        'elapsed_minutes' => 125,
        'notified_user_id' => $this->agent->id,
    ]);
    EscalationLog::create([
        'ticket_id' => $ticket->id,
        'type' => 'warning_90_first_response',
        'sla_minutes' => 30,
        'elapsed_minutes' => 27,
        'notified_user_id' => $this->agent->id,
    ]);

    Livewire::test(AdminSlaReport::class)
        ->assertSee('Incumplido')
        ->assertSee('Alerta 90%')
        ->assertSee('Primera respuesta')
        ->assertSee('Resolución')
        ->assertSee('2 h 5 min de 2 h')
        ->assertSee('Resuelto')
        ->assertDontSee('resolution breach')
        ->assertDontSee('warning 90');
});

it('el panel soporte enlaza al ticket dentro de /soporte', function () {
    Carbon::setTestNow('2026-09-29 10:00:00');
    Filament::setCurrentPanel(Filament::getPanel('soporte'));
    $ticket = breachedTicket();

    Livewire::test(SoporteSlaReport::class)
        ->assertOk()
        ->assertSee(route('filament.soporte.resources.tickets.view', ['record' => $ticket->id]), false);
});

it('etiqueta los tipos de escalación en español', function (string $type, string $label, string $metric, bool $isBreach) {
    $log = new EscalationLog(['type' => $type, 'sla_minutes' => 100, 'elapsed_minutes' => 90]);

    expect($log->typeLabel())->toBe($label)
        ->and($log->metricLabel())->toBe($metric)
        ->and($log->isBreach())->toBe($isBreach)
        ->and($log->consumedPercent())->toBe(90);
})->with([
    ['resolution_breach', 'Incumplido', 'Resolución', true],
    ['first_response_breach', 'Incumplido', 'Primera respuesta', true],
    ['warning_70_resolution', 'Alerta 70%', 'Resolución', false],
    ['warning_90_first_response', 'Alerta 90%', 'Primera respuesta', false],
]);

it('formatea minutos hábiles', function (int $minutes, string $expected) {
    expect(SlaService::formatMinutes($minutes))->toBe($expected);
})->with([
    [45, '45 min'],
    [120, '2 h'],
    [150, '2 h 30 min'],
    [-5, '0 min'],
]);
