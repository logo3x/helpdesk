<?php

use App\Livewire\Portal\CreateTicket;
use App\Models\Category;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->dept = Department::factory()->create();
    $this->category = fn (string $name, ?string $icon) => Category::create([
        'department_id' => $this->dept->id,
        'name' => $name,
        'slug' => str($name)->slug().'-'.uniqid(),
        'is_active' => true,
        'icon' => $icon,
    ]);
});

it('la lista de categorías carga aunque haya íconos guardados a mano', function () {
    ($this->category)('Impresoras', 'heroicon-o-printer');
    ($this->category)('Legado', 'printer');
    ($this->category)('Roto', 'icono-que-no-existe');

    $this->actingAs($this->admin)
        ->get('/admin/categories')
        ->assertOk()
        ->assertSee('Impresoras')
        ->assertSee('Legado')
        ->assertSee('Roto');
});

it('la edición de una categoría con ícono inválido carga', function () {
    $broken = ($this->category)('Roto', 'icono-que-no-existe');

    $this->actingAs($this->admin)
        ->get("/admin/categories/{$broken->id}/edit")
        ->assertOk();
});

it('displayIcon devuelve un ícono dibujable o null', function () {
    expect(($this->category)('A', 'heroicon-o-printer')->displayIcon())->toBe('heroicon-o-printer')
        ->and(($this->category)('B', 'printer')->displayIcon())->toBe('heroicon-o-printer')
        ->and(($this->category)('C', 'icono-que-no-existe')->displayIcon())->toBeNull()
        ->and(($this->category)('D', null)->displayIcon())->toBeNull();
});

it('el portal muestra la categoría elegida aunque su ícono sea inválido', function () {
    $broken = ($this->category)('Roto', 'icono-que-no-existe');
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(CreateTicket::class)
        ->set('category_id', $broken->id)
        ->assertOk()
        ->assertSee('Roto');
});
