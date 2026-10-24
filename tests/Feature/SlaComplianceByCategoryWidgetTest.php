<?php

use App\Filament\Widgets\SlaComplianceByCategoryWidget;
use App\Models\Category;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'agente_soporte', 'guard_name' => 'web']);
});

function buildCategoryWidgetQuery(): Builder
{
    $widget = new SlaComplianceByCategoryWidget;
    $m = new ReflectionMethod($widget, 'buildQuery');
    $m->setAccessible(true);

    return $m->invoke($widget);
}

it('calcula el porcentaje de compliance por categoría', function () {
    $dept = Department::factory()->create(['name' => 'TI']);
    $cat = Category::create([
        'department_id' => $dept->id,
        'name' => 'VPN',
        'slug' => 'vpn-'.uniqid(),
        'is_active' => true,
    ]);

    // 3 resueltos a tiempo + 1 incumplido = 75%
    Ticket::factory()->count(3)->create([
        'category_id' => $cat->id,
        'department_id' => $dept->id,
        'resolved_at' => now()->subDays(5),
        'resolution_breached' => false,
    ]);
    Ticket::factory()->create([
        'category_id' => $cat->id,
        'department_id' => $dept->id,
        'resolved_at' => now()->subDays(5),
        'resolution_breached' => true,
    ]);

    $row = buildCategoryWidgetQuery()->where('categories.id', $cat->id)->first();

    expect((int) $row->resolved_count)->toBe(4);
    expect((int) $row->breached_count)->toBe(1);
    expect((int) $row->compliance_pct)->toBe(75);
});

it('devuelve NULL compliance cuando no hay tickets resueltos', function () {
    $dept = Department::factory()->create();
    $cat = Category::create([
        'department_id' => $dept->id,
        'name' => 'Sin tickets',
        'slug' => 'sintickets-'.uniqid(),
        'is_active' => true,
    ]);

    $row = buildCategoryWidgetQuery()->where('categories.id', $cat->id)->first();

    expect($row->compliance_pct)->toBeNull();
});

it('muestra el nombre jerárquico Padre › Hija para sub-categorías', function () {
    $dept = Department::factory()->create();
    $padre = Category::create([
        'department_id' => $dept->id,
        'name' => 'Soporte',
        'slug' => 'sop-'.uniqid(),
        'is_active' => true,
    ]);
    $hija = Category::create([
        'department_id' => $dept->id,
        'parent_id' => $padre->id,
        'name' => 'Impresoras',
        'slug' => 'imp-'.uniqid(),
        'is_active' => true,
    ]);

    $row = buildCategoryWidgetQuery()->where('categories.id', $hija->id)->first();

    expect($row->full_name)->toBe('Soporte › Impresoras');
});

it('canView rechaza usuarios sin rol admin', function () {
    $user = User::factory()->create();
    $user->assignRole('agente_soporte');
    $this->actingAs($user);

    expect(SlaComplianceByCategoryWidget::canView())->toBeFalse();
});

it('canView acepta super_admin', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    expect(SlaComplianceByCategoryWidget::canView())->toBeTrue();
});
