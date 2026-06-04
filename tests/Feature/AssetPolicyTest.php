<?php

use App\Models\Asset;
use App\Models\Department;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['super_admin', 'admin', 'supervisor_soporte', 'agente_soporte', 'tecnico_campo', 'usuario_final'] as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
    }
});

function userWith(string $role, bool $deptInventory = true): User
{
    $dept = Department::factory()->create(['can_access_inventory' => $deptInventory]);
    $user = User::factory()->create(['department_id' => $dept->id]);
    $user->assignRole($role);

    return $user;
}

it('agente_soporte puede ver inventario pero NO crear ni editar', function () {
    $agent = userWith('agente_soporte');
    $asset = Asset::factory()->create();

    expect($agent->can('viewAny', Asset::class))->toBeTrue()
        ->and($agent->can('view', $asset))->toBeTrue()
        ->and($agent->can('create', Asset::class))->toBeFalse()
        ->and($agent->can('update', $asset))->toBeFalse()
        ->and($agent->can('delete', $asset))->toBeFalse();
});

it('supervisor_soporte puede crear/editar/borrar', function () {
    $sup = userWith('supervisor_soporte');
    $asset = Asset::factory()->create();

    expect($sup->can('create', Asset::class))->toBeTrue()
        ->and($sup->can('update', $asset))->toBeTrue()
        ->and($sup->can('delete', $asset))->toBeTrue();
});

it('tecnico_campo puede crear/editar pero NO borrar', function () {
    $tec = userWith('tecnico_campo');
    $asset = Asset::factory()->create();

    expect($tec->can('create', Asset::class))->toBeTrue()
        ->and($tec->can('update', $asset))->toBeTrue()
        ->and($tec->can('delete', $asset))->toBeFalse();
});

it('si el depto NO tiene can_access_inventory, ningún rol bajo accede', function () {
    foreach (['agente_soporte', 'tecnico_campo', 'supervisor_soporte'] as $role) {
        $u = userWith($role, deptInventory: false);
        $asset = Asset::factory()->create();

        expect($u->can('viewAny', Asset::class))->toBeFalse()
            ->and($u->can('create', Asset::class))->toBeFalse()
            ->and($u->can('update', $asset))->toBeFalse();
    }
});

it('super_admin tiene acceso total sin importar can_access_inventory', function () {
    $sa = userWith('super_admin', deptInventory: false);
    $asset = Asset::factory()->create();

    expect($sa->can('viewAny', Asset::class))->toBeTrue()
        ->and($sa->can('create', Asset::class))->toBeTrue()
        ->and($sa->can('update', $asset))->toBeTrue()
        ->and($sa->can('delete', $asset))->toBeTrue();
});

it('usuario_final nunca accede al inventario', function () {
    $u = userWith('usuario_final');
    $asset = Asset::factory()->create();

    expect($u->can('viewAny', Asset::class))->toBeFalse()
        ->and($u->can('create', Asset::class))->toBeFalse()
        ->and($u->can('update', $asset))->toBeFalse();
});
