<?php

use App\Filament\Resources\Users\Tables\UsersTable as AdminUsersTable;
use App\Filament\Soporte\Resources\Assets\Pages\EditAsset;
use App\Filament\Soporte\Resources\Categories\Pages\CreateCategory;
use App\Filament\Soporte\Resources\Tickets\Pages\ViewTicket;
use App\Filament\Soporte\Resources\Users\Pages\EditUser;
use App\Filament\Soporte\Resources\Users\Pages\ListUsers;
use App\Filament\Soporte\Widgets\AgentRankingWidget;
use App\Filament\Soporte\Widgets\SlaComplianceWidget;
use App\Filament\Soporte\Widgets\TicketResolutionMetricsWidget;
use App\Filament\Support\SlaDefinitions;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);
    Filament::setCurrentPanel(Filament::getPanel('soporte'));

    $this->department = Department::factory()->create();

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    $this->supervisor = User::factory()->create(['department_id' => $this->department->id]);
    $this->supervisor->assignRole('supervisor_soporte');
});

describe('definiciones de SLA', function () {
    it('leen los plazos de la configuración y usan etiquetas legibles', function () {
        config([
            'tickets.auto_close_days' => 5,
            'tickets.awaiting_customer.reminder_hours' => [2, 6],
            'tickets.awaiting_customer.close_hours' => 9,
        ]);

        expect(SlaDefinitions::help('closed'))->toContain('5 días')->toContain('9 h hábiles')
            ->and(SlaDefinitions::help('paused'))->toContain('2 h y 6 h')->toContain('Pendiente cliente')
            ->and(SlaDefinitions::help('resolved_to_closed'))->toContain('5 días');

        foreach (SlaDefinitions::all() as $text) {
            expect($text)->not->toContain('pendiente_cliente')
                ->not->toContain('resolved_at')
                ->not->toContain('X días');
        }
    });

    it('usan el mismo semáforo de cumplimiento en todos los widgets', function () {
        expect(SlaDefinitions::complianceColor(null))->toBe('gray')
            ->and(SlaDefinitions::complianceColor(95))->toBe('success')
            ->and(SlaDefinitions::complianceColor(90))->toBe('success')
            ->and(SlaDefinitions::complianceColor(75))->toBe('warning')
            ->and(SlaDefinitions::complianceColor(70))->toBe('warning')
            ->and(SlaDefinitions::complianceColor(69))->toBe('danger');
    });

    it('fallan con una clave desconocida', function () {
        SlaDefinitions::help('no-existe');
    })->throws(InvalidArgumentException::class);
});

describe('ayudas "?" del escritorio de soporte', function () {
    it('TicketResolutionMetricsWidget usa el "?" y los textos de la ficha del ticket', function () {
        $this->actingAs($this->admin);
        Ticket::factory()->create([
            'created_at' => now()->subDays(2),
            'resolved_at' => now()->subDay(),
            'resolution_due_at' => now(),
            'status' => 'resuelto',
        ]);

        Livewire::test(TicketResolutionMetricsWidget::class)
            ->assertSeeHtml('fi-help-icon')
            ->assertSee(SlaDefinitions::help('solution_time'))
            ->assertSee(SlaDefinitions::help('paused'))
            ->assertSee(SlaDefinitions::help('resolved_to_closed'))
            ->assertSee(SlaDefinitions::help('sla_compliance'))
            ->assertDontSeeHtml('title="Minutos hábiles')
            ->assertDontSee('pendiente_cliente')
            ->assertDontSee('resolution_due_at');
    });

    it('TicketResolutionMetricsWidget formatea con la jornada de SlaService (sin "3d" para 24 h)', function () {
        $this->actingAs($this->admin);
        Ticket::factory()->create([
            'created_at' => now()->subDays(10),
            'resolved_at' => now()->subDay(),
            'paused_minutes' => 1440,
            'status' => 'resuelto',
        ]);

        Livewire::test(TicketResolutionMetricsWidget::class)
            ->assertSee('24 h')
            ->assertDontSee('3d');
    });

    it('SlaComplianceWidget está en español y con ayudas', function () {
        $this->actingAs($this->admin);
        Ticket::factory()->count(3)->create([
            'resolved_at' => now()->subDay(),
            'resolution_breached' => false,
            'status' => 'resuelto',
        ]);

        Livewire::test(SlaComplianceWidget::class)
            ->assertSee('Calidad del servicio (últimos 30 días)')
            ->assertSee('Cumplimiento SLA')
            ->assertSee('Primera respuesta (prom.)')
            ->assertSee('Satisfacción (CSAT)')
            ->assertSee(SlaDefinitions::help('sla_compliance'))
            ->assertSee(SlaDefinitions::help('first_response_avg'))
            ->assertSeeHtml('fi-help-icon')
            ->assertDontSee('SLA Compliance')
            ->assertDontSee('breach');
    });

    it('AgentRankingWidget explica sus columnas con el mismo "?"', function () {
        $this->actingAs($this->admin);

        Livewire::test(AgentRankingWidget::class)
            ->assertSeeHtml('fi-help-icon')
            ->assertSee(SlaDefinitions::help('sla_compliance'))
            ->assertSee(SlaDefinitions::help('reopened_rate'));
    });

    it('la ficha del ticket usa el mismo "?" y los mismos textos', function () {
        $agent = User::factory()->create(['department_id' => $this->department->id]);
        $agent->assignRole('agente_soporte');
        $ticket = Ticket::factory()->create([
            'department_id' => $this->department->id,
            'assigned_to_id' => $agent->id,
        ]);
        $this->actingAs($agent);

        Livewire::test(ViewTicket::class, ['record' => $ticket->getRouteKey()])
            ->assertSeeHtml('fi-help-icon')
            ->assertSee(SlaDefinitions::help('solution_time'))
            ->assertSee(SlaDefinitions::help('closed'))
            ->assertDontSee('auto-cierre del job programado tras X días');
    });
});

describe('pares admin/soporte', function () {
    it('categorías: el supervisor usa el form de admin con el departamento fijo', function () {
        $this->actingAs($this->supervisor);
        $parent = Category::factory()->create(['department_id' => $this->department->id, 'name' => 'Hardware']);

        Livewire::test(CreateCategory::class)
            ->assertFormFieldExists('parent_id')
            ->assertFormFieldExists('icon')
            ->assertDontSee('Departamento')
            ->fillForm([
                'name' => 'Impresoras',
                'slug' => 'impresoras-soporte',
                'parent_id' => $parent->id,
                'icon' => 'heroicon-o-printer',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $category = Category::where('slug', 'impresoras-soporte')->firstOrFail();

        expect($category->department_id)->toBe($this->department->id)
            ->and($category->parent_id)->toBe($parent->id)
            ->and($category->icon)->toBe('heroicon-o-printer');
    });

    it('categorías: el supervisor no puede colgar una categoría de otro departamento', function () {
        $this->actingAs($this->supervisor);
        $foreignParent = Category::factory()->create();

        Livewire::test(CreateCategory::class)
            ->fillForm([
                'name' => 'Intrusa',
                'slug' => 'intrusa',
                'parent_id' => $foreignParent->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['parent_id']);
    });

    it('usuarios: soporte muestra las mismas columnas que admin', function () {
        $this->actingAs($this->supervisor);

        $adminColumns = collect(AdminUsersTable::columns())->map->getName()->all();

        $table = Livewire::test(ListUsers::class)->instance()->getTable();

        expect(array_keys($table->getColumns()))->toBe($adminColumns);
    });

    it('usuarios: el form de soporte incluye los datos laborales de admin', function () {
        $this->actingAs($this->supervisor);
        $user = User::factory()->create(['department_id' => $this->department->id]);
        $user->assignRole('usuario_final');

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertFormFieldExists('identification')
            ->assertFormFieldExists('position')
            ->fillForm(['identification' => '1098765432', 'position' => 'Analista'])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($user->fresh())
            ->identification->toBe('1098765432')
            ->position->toBe('Analista');
    });

    it('activos: la edición en soporte hereda las acciones de actas de admin', function () {
        $this->department->forceFill(['can_access_inventory' => true])->save();
        $this->actingAs($this->supervisor->fresh());
        $asset = Asset::create([
            'asset_tag' => 'PAR-001',
            'status' => 'active',
            'department_id' => $this->department->id,
        ]);

        Livewire::test(EditAsset::class, ['record' => $asset->getRouteKey()])
            ->assertOk()
            ->assertActionExists('viewLifecycle')
            ->assertActionExists('generateHandover')
            ->assertActionVisible('delete')
            ->assertActionDoesNotExist('forceDelete')
            ->assertActionDoesNotExist('restore')
            ->assertActionDoesNotExist('prevAsset');
    });
});
