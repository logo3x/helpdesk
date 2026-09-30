<?php

use App\Enums\TicketPriority;
use App\Filament\Pages\SlaReport as AdminSlaReport;
use App\Filament\Resources\SlaConfigs\Pages\CreateSlaConfig;
use App\Filament\Resources\SlaConfigs\Pages\EditSlaConfig;
use App\Filament\Resources\SlaConfigs\Pages\ListSlaConfigs;
use App\Filament\Resources\SlaConfigs\SlaConfigResource;
use App\Filament\Soporte\Pages\SlaReport as SoporteSlaReport;
use App\Models\Department;
use App\Models\SlaConfig;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    Queue::fake();
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
    $this->actingAs($this->admin);

    $this->department = Department::factory()->create(['name' => 'Soporte TI', 'is_active' => true]);
});

it('el reporte SLA muestra las políticas vigentes con enlaces para editar y crear', function () {
    $config = SlaConfig::factory()->create([
        'department_id' => $this->department->id,
        'priority' => TicketPriority::Alta,
        'first_response_minutes' => 60,
        'resolution_minutes' => 480,
    ]);

    Livewire::test(AdminSlaReport::class)
        ->assertSee('Políticas SLA vigentes por departamento')
        ->assertSee('8 h')
        ->assertSee('Sin SLA')
        ->assertSee(SlaConfigResource::getUrl('edit', ['record' => $config], panel: 'admin'), false)
        ->assertSee(SlaConfigResource::getUrl('create', ['department_id' => $this->department->id, 'priority' => 'critica'], panel: 'admin'));
});

it('un supervisor ve las políticas en soporte pero sin enlaces de edición', function () {
    $supervisor = User::factory()->create(['department_id' => $this->department->id]);
    $supervisor->assignRole('supervisor_soporte');
    $this->actingAs($supervisor);
    Filament::setCurrentPanel(Filament::getPanel('soporte'));

    SlaConfig::factory()->create(['department_id' => $this->department->id, 'priority' => TicketPriority::Alta]);

    Livewire::test(SoporteSlaReport::class)
        ->assertSee('Políticas SLA vigentes por departamento')
        ->assertSee('Solo un administrador puede modificarlas.')
        ->assertDontSee('Administrar todas las políticas');

    expect(SlaConfigResource::canAccess())->toBeFalse();
});

it('lista, crea y edita políticas SLA', function () {
    $config = SlaConfig::factory()->create([
        'department_id' => $this->department->id,
        'priority' => TicketPriority::Media,
        'resolution_minutes' => 1200,
    ]);

    Livewire::test(ListSlaConfigs::class)->assertCanSeeTableRecords([$config]);

    Livewire::test(CreateSlaConfig::class)
        ->fillForm([
            'department_id' => $this->department->id,
            'priority' => TicketPriority::Critica->value,
            'first_response_minutes' => 30,
            'resolution_minutes' => 240,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SlaConfig::where('department_id', $this->department->id)->where('priority', 'critica')->value('resolution_minutes'))->toBe(240);

    Livewire::test(EditSlaConfig::class, ['record' => $config->getRouteKey()])
        ->fillForm(['resolution_minutes' => 900])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($config->fresh()->resolution_minutes)->toBe(900);
});

it('no permite duplicar departamento + prioridad ni resolución menor a la primera respuesta', function () {
    SlaConfig::factory()->create(['department_id' => $this->department->id, 'priority' => TicketPriority::Alta]);

    Livewire::test(CreateSlaConfig::class)
        ->fillForm([
            'department_id' => $this->department->id,
            'priority' => TicketPriority::Alta->value,
            'first_response_minutes' => 120,
            'resolution_minutes' => 60,
        ])
        ->call('create')
        ->assertHasFormErrors(['priority' => 'unique', 'resolution_minutes']);
});

it('precarga departamento y prioridad desde el enlace del reporte', function () {
    $this->get(SlaConfigResource::getUrl('create', ['department_id' => $this->department->id, 'priority' => 'baja'], panel: 'admin'))
        ->assertOk();

    Livewire::withQueryParams(['department_id' => $this->department->id, 'priority' => 'baja'])
        ->test(CreateSlaConfig::class)
        ->assertSchemaStateSet(['department_id' => $this->department->id, 'priority' => TicketPriority::Baja]);
});
