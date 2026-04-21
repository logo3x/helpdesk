<?php

use App\Filament\Soporte\Resources\Tickets\TicketResource;
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

    // Setup de tickets para los tests
    $this->ticketTiSinAsignar = Ticket::factory()->create([
        'department_id' => $this->deptTi->id,
        'assigned_to_id' => null,
    ]);
    $this->ticketTiMio = Ticket::factory()->create([
        'department_id' => $this->deptTi->id,
        'assigned_to_id' => $this->agenteTi->id,
    ]);
    $this->ticketTiAjeno = Ticket::factory()->create([
        'department_id' => $this->deptTi->id,
        'assigned_to_id' => $this->supervisorTi->id,
    ]);
    $this->ticketRrhh = Ticket::factory()->create([
        'department_id' => $this->deptRrhh->id,
    ]);
});

test('super_admin ve todos los tickets sin filtro', function () {
    $this->actingAs($this->admin);

    $query = TicketResource::getEloquentQuery();

    expect($query->count())->toBe(4);
});

test('supervisor solo ve tickets de su departamento', function () {
    $this->actingAs($this->supervisorTi);

    $query = TicketResource::getEloquentQuery();
    $ids = $query->pluck('id')->all();

    expect($ids)->toHaveCount(3);
    expect($ids)->toContain($this->ticketTiSinAsignar->id);
    expect($ids)->toContain($this->ticketTiMio->id);
    expect($ids)->toContain($this->ticketTiAjeno->id);
    expect($ids)->not->toContain($this->ticketRrhh->id);
});

test('agente solo ve sus asignados + sin asignar de su depto', function () {
    $this->actingAs($this->agenteTi);

    $query = TicketResource::getEloquentQuery();
    $ids = $query->pluck('id')->all();

    expect($ids)->toHaveCount(2);
    expect($ids)->toContain($this->ticketTiSinAsignar->id);
    expect($ids)->toContain($this->ticketTiMio->id);
    expect($ids)->not->toContain($this->ticketTiAjeno->id);
    expect($ids)->not->toContain($this->ticketRrhh->id);
});

test('el route binding también respeta el scope (anti-bypass por ID)', function () {
    $this->actingAs($this->agenteTi);

    // find() muta el builder subyacente; se genera una query nueva por
    // cada comprobación para evitar acumular WHERE id = ... anteriores.
    $resolve = fn (int $id) => TicketResource::getRecordRouteBindingEloquentQuery()->find($id);

    expect($resolve($this->ticketRrhh->id))->toBeNull();                // bloqueado (otro depto)
    expect($resolve($this->ticketTiAjeno->id))->toBeNull();             // bloqueado (asignado a otro)
    expect($resolve($this->ticketTiSinAsignar->id))->not->toBeNull();   // permitido
    expect($resolve($this->ticketTiMio->id))->not->toBeNull();          // permitido
});

test('agente sin department_id no ve ningún ticket', function () {
    $sinDept = User::factory()->create(['department_id' => null]);
    $sinDept->assignRole('agente_soporte');

    $this->actingAs($sinDept);

    $query = TicketResource::getEloquentQuery();

    expect($query->count())->toBe(0);
});
