<?php

use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RoleSeeder::class]);
    $this->seed(ShieldPermissionSeeder::class);

    $this->deptTi = Department::factory()->create(['name' => 'TI', 'slug' => 'ti']);
    $this->deptRrhh = Department::factory()->create(['name' => 'RRHH', 'slug' => 'rrhh']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->supervisorTi = User::factory()->create(['department_id' => $this->deptTi->id]);
    $this->supervisorTi->assignRole('supervisor_soporte');

    $this->agenteTi = User::factory()->create(['department_id' => $this->deptTi->id]);
    $this->agenteTi->assignRole('agente_soporte');

    $this->agenteRrhh = User::factory()->create(['department_id' => $this->deptRrhh->id]);
    $this->agenteRrhh->assignRole('agente_soporte');

    $this->usuarioFinal = User::factory()->create();
    $this->usuarioFinal->assignRole('usuario_final');
});

test('super_admin puede ver cualquier ticket', function () {
    $ticket = Ticket::factory()->create(['department_id' => $this->deptTi->id]);

    expect($this->admin->can('view', $ticket))->toBeTrue();
});

test('supervisor solo ve tickets de su departamento', function () {
    $ticketTi = Ticket::factory()->create(['department_id' => $this->deptTi->id]);
    $ticketRrhh = Ticket::factory()->create(['department_id' => $this->deptRrhh->id]);

    expect($this->supervisorTi->can('view', $ticketTi))->toBeTrue();
    expect($this->supervisorTi->can('view', $ticketRrhh))->toBeFalse();
});

test('agente solo ve tickets de su depto asignados a él o sin asignar', function () {
    $sinAsignar = Ticket::factory()->create([
        'department_id' => $this->deptTi->id,
        'assigned_to_id' => null,
    ]);
    $asignadoAMi = Ticket::factory()->create([
        'department_id' => $this->deptTi->id,
        'assigned_to_id' => $this->agenteTi->id,
    ]);
    $asignadoAOtro = Ticket::factory()->create([
        'department_id' => $this->deptTi->id,
        'assigned_to_id' => $this->supervisorTi->id,
    ]);
    $otroDept = Ticket::factory()->create([
        'department_id' => $this->deptRrhh->id,
        'assigned_to_id' => $this->agenteTi->id,
    ]);

    expect($this->agenteTi->can('view', $sinAsignar))->toBeTrue();
    expect($this->agenteTi->can('view', $asignadoAMi))->toBeTrue();
    expect($this->agenteTi->can('view', $asignadoAOtro))->toBeFalse();
    expect($this->agenteTi->can('view', $otroDept))->toBeFalse();
});

test('agente de TI NO puede ver tickets de RRHH aunque tenga permiso Shield', function () {
    $ticketRrhh = Ticket::factory()->create([
        'department_id' => $this->deptRrhh->id,
        'assigned_to_id' => null,
    ]);

    expect($this->agenteTi->can('view', $ticketRrhh))->toBeFalse();
});

test('usuario_final no tiene permiso View:Ticket (accede via portal Livewire, no policy)', function () {
    $ticket = Ticket::factory()->create([
        'requester_id' => $this->usuarioFinal->id,
        'department_id' => $this->deptTi->id,
    ]);

    // usuario_final no tiene permiso Shield "View:Ticket" — el portal
    // Livewire usa su propio query scoped por requester_id, no delega
    // al Policy. Aquí validamos que el Policy les niega (no pueden
    // entrar al panel /soporte).
    expect($this->usuarioFinal->can('view', $ticket))->toBeFalse();
});

test('solo supervisor+admin pueden trasladar tickets (transfer)', function () {
    $ticket = Ticket::factory()->create(['department_id' => $this->deptTi->id]);

    expect($this->admin->can('transfer', $ticket))->toBeTrue();
    expect($this->supervisorTi->can('transfer', $ticket))->toBeTrue();
    expect($this->agenteTi->can('transfer', $ticket))->toBeFalse();
    expect($this->usuarioFinal->can('transfer', $ticket))->toBeFalse();
});

test('agente no puede eliminar ni restaurar tickets', function () {
    $ticket = Ticket::factory()->create([
        'department_id' => $this->deptTi->id,
        'assigned_to_id' => $this->agenteTi->id,
    ]);

    expect($this->agenteTi->can('delete', $ticket))->toBeFalse();
    expect($this->agenteTi->can('restore', $ticket))->toBeFalse();
    expect($this->agenteTi->can('forceDelete', $ticket))->toBeFalse();
});

test('supervisor puede eliminar tickets de su depto pero no de otro', function () {
    $ticketTi = Ticket::factory()->create(['department_id' => $this->deptTi->id]);
    $ticketRrhh = Ticket::factory()->create(['department_id' => $this->deptRrhh->id]);

    expect($this->supervisorTi->can('delete', $ticketTi))->toBeTrue();
    expect($this->supervisorTi->can('delete', $ticketRrhh))->toBeFalse();
});
