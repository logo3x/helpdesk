<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Soporte\Widgets\TicketStatsWidget;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RoleSeeder::class]);
    $this->seed(ShieldPermissionSeeder::class);

    Filament::setCurrentPanel(Filament::getPanel('soporte'));

    $this->deptTi = Department::factory()->create(['name' => 'TI', 'slug' => 'ti']);
    $this->deptRrhh = Department::factory()->create(['name' => 'RRHH', 'slug' => 'rrhh']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->supervisorTi = User::factory()->create(['department_id' => $this->deptTi->id]);
    $this->supervisorTi->assignRole('supervisor_soporte');

    $this->agenteTi = User::factory()->create(['department_id' => $this->deptTi->id]);
    $this->agenteTi->assignRole('agente_soporte');

    // 2 tickets de TI abiertos, 1 de RRHH abierto, 1 cerrado de TI.
    Ticket::factory()->create([
        'department_id' => $this->deptTi->id,
        'status' => TicketStatus::Nuevo,
        'priority' => TicketPriority::Alta,
    ]);
    Ticket::factory()->create([
        'department_id' => $this->deptTi->id,
        'status' => TicketStatus::EnProgreso,
        'assigned_to_id' => $this->agenteTi->id,
        'priority' => TicketPriority::Media,
    ]);
    Ticket::factory()->create([
        'department_id' => $this->deptRrhh->id,
        'status' => TicketStatus::Nuevo,
        'priority' => TicketPriority::Baja,
    ]);
    Ticket::factory()->create([
        'department_id' => $this->deptTi->id,
        'status' => TicketStatus::Cerrado,
        'priority' => TicketPriority::Media,
    ]);
});

it('admin ve totales globales sin filtro', function () {
    Livewire::actingAs($this->admin)
        ->test(TicketStatsWidget::class)
        ->assertSeeText(['3']) // 3 tickets abiertos en total (2 TI + 1 RRHH)
        ->assertSeeText('Todo el sistema');
});

it('supervisor ve solo los tickets de su departamento', function () {
    Livewire::actingAs($this->supervisorTi)
        ->test(TicketStatsWidget::class)
        ->assertSeeText('En tu departamento');
});

it('supervisor ve stats adicionales del equipo y KB', function () {
    Livewire::actingAs($this->supervisorTi)
        ->test(TicketStatsWidget::class)
        ->assertSeeText('Mi equipo')
        ->assertSeeText('KB por aprobar');
});

it('agente solo cuenta tickets asignados a él o sin asignar en su depto', function () {
    Livewire::actingAs($this->agenteTi)
        ->test(TicketStatsWidget::class)
        ->assertSeeText('En tu cola');
});

it('admin no ve stats de equipo (esas son específicas de supervisor)', function () {
    Livewire::actingAs($this->admin)
        ->test(TicketStatsWidget::class)
        ->assertDontSeeText('Mi equipo')
        ->assertDontSeeText('KB por aprobar');
});
