<?php

use App\Filament\Soporte\Pages\MaintenancesReport;
use App\Models\ScheduledMaintenance;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);

    Filament::setCurrentPanel(Filament::getPanel('soporte'));

    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $this->actingAs($admin);
});

it('muestra las gráficas de estado y de razones como torta', function () {
    ScheduledMaintenance::factory()->completed()->create(['scheduled_at' => now()->subDays(5)]);
    ScheduledMaintenance::factory()->notCompleted('Usuario no disponible')->create(['scheduled_at' => now()->subDays(3)]);

    Livewire::test(MaintenancesReport::class)
        ->assertOk()
        ->assertSee('Distribución por estado')
        ->assertSee('mr-status-pie', false)
        ->assertSee('mr-reasons-pie', false)
        ->assertSee("type: 'pie'", false)
        ->assertDontSee("type: 'bar'", false)
        ->assertSee('Usuario no disponible');
});

it('no dibuja gráficas cuando no hay mantenimientos en la ventana', function () {
    Livewire::test(MaintenancesReport::class)
        ->assertOk()
        ->assertSee('No hay datos en esta ventana.')
        ->assertDontSee('mr-status-pie', false);
});
