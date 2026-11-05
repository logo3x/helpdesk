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
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    Carbon::setTestNow('2026-09-29 10:00:00');
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->deptA = Department::factory()->create(['name' => 'Soporte TI', 'is_active' => true]);
    $this->deptB = Department::factory()->create(['name' => 'Mantenimiento', 'is_active' => true]);

    foreach (['A' => $this->deptA, 'B' => $this->deptB] as $suffix => $department) {
        $sla = SlaConfig::factory()->create([
            'department_id' => $department->id,
            'priority' => TicketPriority::Alta,
            'resolution_minutes' => 120,
        ]);

        $base = [
            'department_id' => $department->id,
            'priority' => TicketPriority::Alta,
            'sla_config_id' => $sla->id,
            'paused_minutes' => 0,
        ];

        Ticket::factory()->create($base + [
            'number' => "TK-INCUMPLIDO-{$suffix}",
            'status' => TicketStatus::Resuelto,
            'created_at' => Carbon::parse('2026-09-22 09:00:00'),
            'resolved_at' => Carbon::parse('2026-09-22 13:00:00'),
            'resolution_breached' => true,
        ]);

        Ticket::factory()->create($base + [
            'number' => "TK-RIESGO-{$suffix}",
            'status' => TicketStatus::EnProgreso,
            'resolved_at' => null,
            'resolution_due_at' => now()->addHours(2),
            'resolution_breached' => false,
        ]);

        $escalated = Ticket::factory()->create($base + [
            'number' => "TK-ESCALADO-{$suffix}",
            'status' => TicketStatus::EnProgreso,
            'resolved_at' => null,
            'resolution_due_at' => now()->addDays(5),
            'resolution_breached' => false,
        ]);

        EscalationLog::create([
            'ticket_id' => $escalated->id,
            'type' => 'warning_70_resolution',
            'sla_minutes' => 120,
            'elapsed_minutes' => 85,
        ]);
    }
});

it('renderiza ambas páginas para un super_admin', function () {
    $this->actingAs($this->admin);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(AdminSlaReport::class)
        ->assertOk()
        ->assertSee('Exportar Consolidado (Excel)');

    Filament::setCurrentPanel(Filament::getPanel('soporte'));
    Livewire::test(SoporteSlaReport::class)
        ->assertOk()
        ->assertSee('Exportar Consolidado (Excel)');
});

it('un supervisor en soporte solo ve tickets y escalaciones de su departamento', function () {
    $supervisor = User::factory()->create(['department_id' => $this->deptA->id]);
    $supervisor->assignRole('supervisor_soporte');
    $this->actingAs($supervisor);
    Filament::setCurrentPanel(Filament::getPanel('soporte'));

    Livewire::test(SoporteSlaReport::class)
        ->assertOk()
        ->assertSee('TK-INCUMPLIDO-A')
        ->assertSee('TK-RIESGO-A')
        ->assertSee('TK-ESCALADO-A')
        ->assertDontSee('TK-INCUMPLIDO-B')
        ->assertDontSee('TK-RIESGO-B')
        ->assertDontSee('TK-ESCALADO-B')
        ->assertViewHas('summary', fn (array $summary) => $summary['resolved'] === 1 && $summary['breached'] === 1)
        ->assertViewHas('report', fn (array $report) => collect($report)->pluck('department_id')->all() === [$this->deptA->id])
        ->assertDontSee('Exportar Consolidado (Excel)');
});

it('un supervisor no puede saltarse el alcance filtrando por otro departamento', function () {
    $supervisor = User::factory()->create(['department_id' => $this->deptA->id]);
    $supervisor->assignRole('supervisor_soporte');
    $this->actingAs($supervisor);
    Filament::setCurrentPanel(Filament::getPanel('soporte'));

    Livewire::test(SoporteSlaReport::class)
        ->call('filterBreaches', $this->deptB->id, TicketPriority::Alta->value)
        ->assertDontSee('TK-INCUMPLIDO-B')
        ->call('exportConsolidado')
        ->assertForbidden();
});

it('un supervisor sin departamento no ve datos de ningún departamento', function () {
    $supervisor = User::factory()->create(['department_id' => null]);
    $supervisor->assignRole('supervisor_soporte');
    $this->actingAs($supervisor);
    Filament::setCurrentPanel(Filament::getPanel('soporte'));

    Livewire::test(SoporteSlaReport::class)
        ->assertOk()
        ->assertDontSee('TK-INCUMPLIDO-A')
        ->assertDontSee('TK-INCUMPLIDO-B')
        ->assertDontSee('TK-RIESGO-A')
        ->assertDontSee('TK-ESCALADO-B');
});

it('un admin en soporte ve ambos departamentos', function () {
    $admin = User::factory()->create(['department_id' => $this->deptA->id]);
    $admin->assignRole('admin');
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('soporte'));

    Livewire::test(SoporteSlaReport::class)
        ->assertOk()
        ->assertSee('TK-INCUMPLIDO-A')
        ->assertSee('TK-INCUMPLIDO-B')
        ->assertSee('TK-RIESGO-A')
        ->assertSee('TK-RIESGO-B')
        ->assertSee('TK-ESCALADO-A')
        ->assertSee('TK-ESCALADO-B');
});

it('un agente de soporte no puede acceder al reporte de soporte', function () {
    $agent = User::factory()->create(['department_id' => $this->deptA->id]);
    $agent->assignRole('agente_soporte');
    $this->actingAs($agent);
    Filament::setCurrentPanel(Filament::getPanel('soporte'));

    expect(SoporteSlaReport::canAccess())->toBeFalse()
        ->and(SoporteSlaReport::shouldRegisterNavigation())->toBeFalse();

    Livewire::test(SoporteSlaReport::class)->assertForbidden();
});

it('los enlaces de ticket apuntan al panel de cada página', function () {
    $this->actingAs($this->admin);
    $ticket = Ticket::query()->where('number', 'TK-INCUMPLIDO-A')->firstOrFail();

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(AdminSlaReport::class)
        ->assertViewHas('ticketRoute', 'filament.admin.resources.tickets.view')
        ->assertSee(route('filament.admin.resources.tickets.view', ['record' => $ticket->id]), false);

    Filament::setCurrentPanel(Filament::getPanel('soporte'));
    Livewire::test(SoporteSlaReport::class)
        ->assertViewHas('ticketRoute', 'filament.soporte.resources.tickets.view')
        ->assertSee(route('filament.soporte.resources.tickets.view', ['record' => $ticket->id]), false);
});

it('el PDF del panel soporte respeta el alcance del supervisor', function () {
    $supervisor = User::factory()->create(['department_id' => $this->deptA->id]);
    $supervisor->assignRole('supervisor_soporte');
    $this->actingAs($supervisor);

    $page = new SoporteSlaReport;
    $data = $page->getViewData();

    expect($data['breachedTickets']->map(fn (array $row) => $row['ticket']->number)->all())->toBe(['TK-INCUMPLIDO-A'])
        ->and($data['atRisk']->map(fn (array $row) => $row['ticket']->number)->all())->toBe(['TK-RIESGO-A'])
        ->and($page->exportPdf()->headers->get('Content-Type'))->toBe('application/pdf');
});
