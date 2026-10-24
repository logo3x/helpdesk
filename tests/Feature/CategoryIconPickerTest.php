<?php

use App\Filament\Resources\Categories\Schemas\CategoryForm;
use App\Models\Category;
use App\Models\Department;

it('la lista curada de iconos tiene al menos 30 opciones y todas son heroicons', function () {
    $reflection = new ReflectionClass(CategoryForm::class);
    $method = $reflection->getMethod('iconOptions');
    $method->setAccessible(true);
    $options = $method->invoke(null);

    expect(count($options))->toBeGreaterThanOrEqual(30);

    foreach ($options as $key => $label) {
        expect($key)->toStartWith('heroicon-')
            ->and($label)->toBeString()->not->toBeEmpty();
    }
});

it('los nombres de icono caben en la columna varchar(60)', function () {
    $reflection = new ReflectionClass(CategoryForm::class);
    $method = $reflection->getMethod('iconOptions');
    $method->setAccessible(true);
    $options = $method->invoke(null);

    foreach ($options as $key => $label) {
        expect(strlen($key))->toBeLessThanOrEqual(60);
    }
});

it('permite guardar una categoría con un icono válido del catálogo', function () {
    $dept = Department::factory()->create();

    $category = Category::create([
        'department_id' => $dept->id,
        'name' => 'Impresoras',
        'slug' => 'impresoras-'.uniqid(),
        'icon' => 'heroicon-o-printer',
        'is_active' => true,
    ]);

    expect($category->fresh()->icon)->toBe('heroicon-o-printer');
});
