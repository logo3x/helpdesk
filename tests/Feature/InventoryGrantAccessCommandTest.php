<?php

use App\Models\Department;

it('habilita el acceso al inventario por slug', function () {
    Department::create(['name' => 'Tecnología', 'slug' => 'ti', 'can_access_inventory' => false]);

    $this->artisan('inventory:grant', ['department' => 'ti'])
        ->expectsOutputToContain('habilitado para')
        ->assertExitCode(0);

    expect(Department::where('slug', 'ti')->value('can_access_inventory'))->toBeTrue();
});

it('habilita el acceso al inventario por fragmento del nombre', function () {
    Department::create(['name' => 'Tecnología de la Información', 'slug' => 'tecnologia', 'can_access_inventory' => false]);

    $this->artisan('inventory:grant', ['department' => 'Tecnología'])
        ->assertExitCode(0);

    expect(Department::where('slug', 'tecnologia')->value('can_access_inventory'))->toBeTrue();
});

it('habilita el acceso al inventario por ID', function () {
    $dept = Department::create(['name' => 'IT', 'slug' => 'it', 'can_access_inventory' => false]);

    $this->artisan('inventory:grant', ['department' => (string) $dept->id])
        ->assertExitCode(0);

    expect($dept->fresh()->can_access_inventory)->toBeTrue();
});

it('revoca el acceso con --revoke', function () {
    Department::create(['name' => 'Soporte', 'slug' => 'soporte', 'can_access_inventory' => true]);

    $this->artisan('inventory:grant', ['department' => 'soporte', '--revoke' => true])
        ->expectsOutputToContain('revocado')
        ->assertExitCode(0);

    expect(Department::where('slug', 'soporte')->value('can_access_inventory'))->toBeFalse();
});

it('reporta no-op si el flag ya está en el estado solicitado', function () {
    Department::create(['name' => 'Mantenimiento', 'slug' => 'mantenimiento', 'can_access_inventory' => true]);

    $this->artisan('inventory:grant', ['department' => 'mantenimiento'])
        ->expectsOutputToContain('ya tenía')
        ->assertExitCode(0);
});

it('falla con código no-cero y sugiere departamentos cuando no encuentra match', function () {
    Department::create(['name' => 'Compras', 'slug' => 'compras', 'can_access_inventory' => false]);

    $this->artisan('inventory:grant', ['department' => 'inexistente-xyz'])
        ->expectsOutputToContain('No se encontró')
        ->expectsOutputToContain('Compras')
        ->assertExitCode(1);
});
