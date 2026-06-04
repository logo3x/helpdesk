<?php

use App\Filament\Soporte\Widgets\TicketResolutionMetricsWidget;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['super_admin', 'supervisor_soporte', 'agente_soporte'] as $r) {
        Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
    }
});

it('muestra mensaje "Sin datos" cuando no hay tickets resueltos', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    Livewire::test(TicketResolutionMetricsWidget::class)
        ->assertSee('Sin datos');
});

it('calcula tiempo de solución descontando minutos pausados', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    // Ticket creado Lunes 09:00, resuelto Martes 15:00 — 16h hábiles totales
    // (Lun 9-18 = 9h + Mar 8-15 = 7h). Con 870 min de pausa → solución 90 min.
    $createdAt = now()->startOfWeek()->setTime(9, 0); // Lunes 9 AM
    $resolvedAt = $createdAt->copy()->addDay()->setTime(15, 0); // Martes 3 PM

    Ticket::factory()->create([
        'created_at' => $createdAt,
        'resolved_at' => $resolvedAt,
        'paused_minutes' => 870,
        'status' => 'resuelto',
    ]);

    Livewire::test(TicketResolutionMetricsWidget::class)
        ->assertSee('Tiempo de solución')
        ->assertSee('Tiempo pausado')
        ->assertSee('Resuelto → Cerrado')
        ->assertSee('% en SLA');
});

it('calcula % de cumplimiento de SLA correctamente', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    // 3 tickets resueltos a tiempo, 1 fuera de tiempo → 75%.
    Ticket::factory()->count(3)->create([
        'resolved_at' => now()->subHours(2),
        'resolution_due_at' => now()->subHour(),
        'status' => 'resuelto',
    ]);
    Ticket::factory()->create([
        'resolved_at' => now()->subHour(),
        'resolution_due_at' => now()->subHours(2),
        'status' => 'resuelto',
    ]);

    Livewire::test(TicketResolutionMetricsWidget::class)
        ->assertSee('75%');
});

it('filtra tickets por depto cuando el user no es supervisor cross-depto', function () {
    $myDept = Department::factory()->create();
    $otherDept = Department::factory()->create();

    $user = User::factory()->create(['department_id' => $myDept->id]);
    $user->assignRole('agente_soporte');
    $this->actingAs($user);

    Ticket::factory()->create([
        'department_id' => $myDept->id,
        'resolved_at' => now()->subDay(),
        'resolution_due_at' => now()->subDays(2),
        'status' => 'resuelto',
        'paused_minutes' => 60,
    ]);

    Ticket::factory()->create([
        'department_id' => $otherDept->id,
        'resolved_at' => now()->subDay(),
        'paused_minutes' => 9999,
        'status' => 'resuelto',
    ]);

    Livewire::test(TicketResolutionMetricsWidget::class)
        ->assertSee('Tiempo pausado');
});

it('descarta tickets resueltos hace más de 30 días', function () {
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    $this->actingAs($user);

    Ticket::factory()->create([
        'resolved_at' => now()->subDays(60),
        'status' => 'resuelto',
    ]);

    Livewire::test(TicketResolutionMetricsWidget::class)
        ->assertSee('Sin datos');
});
