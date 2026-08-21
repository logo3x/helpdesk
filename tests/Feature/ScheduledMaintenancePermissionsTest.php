<?php

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceStatus;
use App\Filament\Soporte\Resources\ScheduledMaintenances\ScheduledMaintenanceResource;
use App\Models\Asset;
use App\Models\Department;
use App\Models\ScheduledMaintenance;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * Verifica la matriz de permisos del módulo Mantenimientos Programados:
 *   - super_admin/admin: todo (ver, crear, editar, borrar).
 *   - supervisor_soporte: ver su depto + crear/editar/borrar.
 *   - agente_soporte/tecnico_campo: solo ven sus asignados, NO borran.
 */
beforeEach(function () {
    foreach (['super_admin', 'admin', 'supervisor_soporte', 'agente_soporte', 'tecnico_campo', 'usuario_final'] as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
    }
});

function userWithMaintRole(string $role, ?Department $dept = null): User
{
    $dept ??= Department::factory()->create(['can_access_inventory' => true]);
    $user = User::factory()->create(['department_id' => $dept->id]);
    $user->assignRole($role);

    return $user;
}

function scheduledMaintenanceFor(User $agent, ?Asset $asset = null): ScheduledMaintenance
{
    $asset ??= Asset::factory()->create(['type' => 'laptop']);

    return ScheduledMaintenance::create([
        'asset_id' => $asset->id,
        'agent_id' => $agent->id,
        'created_by_id' => $agent->id,
        'scheduled_at' => now()->addDays(30),
        'status' => MaintenanceStatus::Pendiente->value,
        'frequency' => MaintenanceFrequency::Cuatrimestral->value,
    ]);
}

it('agente_soporte solo ve mtto asignados a él', function () {
    $dept = Department::factory()->create(['can_access_inventory' => true]);
    $agent = userWithMaintRole('agente_soporte', $dept);
    $otherAgent = userWithMaintRole('agente_soporte', $dept);

    $mine = scheduledMaintenanceFor($agent);
    $others = scheduledMaintenanceFor($otherAgent);

    auth()->login($agent);

    $ids = ScheduledMaintenanceResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($others->id);
});

it('tecnico_campo solo ve mtto asignados a él', function () {
    $dept = Department::factory()->create(['can_access_inventory' => true]);
    $tecnico = userWithMaintRole('tecnico_campo', $dept);
    $otherTecnico = userWithMaintRole('tecnico_campo', $dept);

    $mine = scheduledMaintenanceFor($tecnico);
    $others = scheduledMaintenanceFor($otherTecnico);

    auth()->login($tecnico);

    $ids = ScheduledMaintenanceResource::getEloquentQuery()->pluck('id')->all();

    expect($ids)->toContain($mine->id)
        ->and($ids)->not->toContain($others->id);
});

it('agente NO puede borrar mantenimientos (ni los suyos)', function () {
    $agent = userWithMaintRole('agente_soporte');
    $m = scheduledMaintenanceFor($agent);

    auth()->login($agent);

    expect(ScheduledMaintenanceResource::canDelete($m))->toBeFalse();
});

it('tecnico_campo NO puede borrar mantenimientos', function () {
    $tecnico = userWithMaintRole('tecnico_campo');
    $m = scheduledMaintenanceFor($tecnico);

    auth()->login($tecnico);

    expect(ScheduledMaintenanceResource::canDelete($m))->toBeFalse();
});

it('supervisor_soporte SÍ puede borrar mantenimientos', function () {
    $dept = Department::factory()->create(['can_access_inventory' => true]);
    $sup = userWithMaintRole('supervisor_soporte', $dept);
    $agent = userWithMaintRole('agente_soporte', $dept);
    $m = scheduledMaintenanceFor($agent);

    auth()->login($sup);

    expect(ScheduledMaintenanceResource::canDelete($m))->toBeTrue();
});

it('super_admin SÍ puede borrar mantenimientos', function () {
    $admin = userWithMaintRole('super_admin');
    $agent = userWithMaintRole('agente_soporte');
    $m = scheduledMaintenanceFor($agent);

    auth()->login($admin);

    expect(ScheduledMaintenanceResource::canDelete($m))->toBeTrue();
});

it('agente puede editar sus propios mantenimientos', function () {
    $agent = userWithMaintRole('agente_soporte');
    $m = scheduledMaintenanceFor($agent);

    auth()->login($agent);

    expect(ScheduledMaintenanceResource::canEdit($m))->toBeTrue();
});

it('agente NO puede editar mantenimientos de otros', function () {
    $agent = userWithMaintRole('agente_soporte');
    $otherAgent = userWithMaintRole('agente_soporte');
    $m = scheduledMaintenanceFor($otherAgent);

    auth()->login($agent);

    expect(ScheduledMaintenanceResource::canEdit($m))->toBeFalse();
});

it('agente NO puede crear mantenimientos', function () {
    $agent = userWithMaintRole('agente_soporte');
    auth()->login($agent);

    expect(ScheduledMaintenanceResource::canCreate())->toBeFalse();
});
