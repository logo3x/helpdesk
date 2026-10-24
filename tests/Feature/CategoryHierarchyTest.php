<?php

use App\Livewire\Portal\CreateTicket;
use App\Models\Category;
use App\Models\Department;
use App\Models\User;
use Livewire\Livewire;

it('mantiene la jerarquía padre → hija', function () {
    $dept = Department::factory()->create();

    $parent = Category::create([
        'department_id' => $dept->id,
        'name' => 'Soporte técnico',
        'slug' => 'soporte-tecnico-'.uniqid(),
        'is_active' => true,
    ]);

    $child = Category::create([
        'department_id' => $dept->id,
        'parent_id' => $parent->id,
        'name' => 'Impresoras',
        'slug' => 'impresoras-'.uniqid(),
        'is_active' => true,
    ]);

    expect($child->parent_id)->toBe($parent->id);
    expect($parent->children()->count())->toBe(1);
    expect($child->qualifiedName())->toBe('Soporte técnico › Impresoras');
    expect($parent->qualifiedName())->toBe('Soporte técnico');
});

it('scope roots devuelve solo categorías sin padre', function () {
    $dept = Department::factory()->create();

    $parent = Category::create([
        'department_id' => $dept->id,
        'name' => 'Root',
        'slug' => 'root-'.uniqid(),
        'is_active' => true,
    ]);

    Category::create([
        'department_id' => $dept->id,
        'parent_id' => $parent->id,
        'name' => 'Hija',
        'slug' => 'hija-'.uniqid(),
        'is_active' => true,
    ]);

    expect(Category::query()->roots()->count())->toBe(1);
});

it('canBecomeChild rechaza categorías que ya tienen hijas', function () {
    $dept = Department::factory()->create();
    $parent = Category::create(['department_id' => $dept->id, 'name' => 'P', 'slug' => 'p-'.uniqid(), 'is_active' => true]);
    Category::create(['department_id' => $dept->id, 'parent_id' => $parent->id, 'name' => 'C', 'slug' => 'c-'.uniqid(), 'is_active' => true]);

    expect($parent->canBecomeChild())->toBeFalse();

    $orphan = Category::create(['department_id' => $dept->id, 'name' => 'O', 'slug' => 'o-'.uniqid(), 'is_active' => true]);
    expect($orphan->canBecomeChild())->toBeTrue();
});

it('portal: muestra el departamento al seleccionar una categoría', function () {
    $dept = Department::factory()->create(['name' => 'Tecnología de la Información']);
    $category = Category::create([
        'department_id' => $dept->id,
        'name' => 'VPN',
        'slug' => 'vpn-'.uniqid(),
        'is_active' => true,
    ]);

    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(CreateTicket::class)
        ->set('category_id', $category->id)
        ->assertSee('Tecnología de la Información');
});
