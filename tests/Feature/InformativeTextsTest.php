<?php

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Filament\Soporte\Resources\Tickets\Pages\ViewTicket as SoporteViewTicket;
use App\Livewire\Portal\ViewTicket as PortalViewTicket;
use App\Models\Asset;
use App\Models\Department;
use App\Models\MaintenanceSurvey;
use App\Models\SatisfactionSurvey;
use App\Models\SlaConfig;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\AssetMaintenanceSurveyNotification;
use App\Notifications\SatisfactionSurveyNotification;
use App\Notifications\TicketResolvedNotification;
use App\Services\LlmService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/**
 * Los textos informativos deben reflejar la configuración vigente
 * (plazos de config/tickets.php, sla_configs, proveedor de IA) y estar
 * en español neutro, sin voseo.
 */
it('el acuerdo de uso muestra los plazos del SLA configurados, incluida la prioridad Planificada', function () {
    $this->withoutVite();

    $times = [
        TicketPriority::Critica->value => [30, 240],
        TicketPriority::Alta->value => [60, 480],
        TicketPriority::Media->value => [120, 1200],
        TicketPriority::Baja->value => [240, 2400],
        TicketPriority::Planificada->value => [480, 6000],
    ];

    foreach (Department::factory()->count(2)->create() as $department) {
        foreach ($times as $priority => [$firstResponse, $resolution]) {
            SlaConfig::factory()->create([
                'department_id' => $department->id,
                'priority' => $priority,
                'first_response_minutes' => $firstResponse,
                'resolution_minutes' => $resolution,
            ]);
        }
    }

    $user = User::factory()->create(['asl_accepted_at' => null]);

    $this->actingAs($user)
        ->get(route('asl.show'))
        ->assertOk()
        ->assertSee('Crítica (30 min / 4 h)')
        ->assertSee('Media (2 h / 20 h)')
        ->assertSee('Planificada (8 h / 100 h)')
        ->assertSee('Escalamiento')
        ->assertDontSee('Escalonamiento')
        ->assertDontSee('Baja (72 h hábiles)')
        ->assertDontSee('se escala automáticamente');
});

it('el acuerdo de uso sigue funcionando sin plazos configurados', function () {
    $this->withoutVite();

    $user = User::factory()->create(['asl_accepted_at' => null]);

    $this->actingAs($user)
        ->get(route('asl.show'))
        ->assertOk()
        ->assertSee('Entiendo y acepto los tiempos de primera respuesta y solución');
});

it('la página de inicio usa el plazo de cierre automático vigente y no promete reabrir', function () {
    config(['tickets.auto_close_days' => 3]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('se cierra solo 3 días después')
        ->assertSee('crea el ticket por ti')
        ->assertDontSee('reabrir el caso')
        ->assertDontSee('se escala solo');
});

it('el aviso de ticket resuelto explica el cierre automático y no ofrece reabrir desde el portal', function () {
    config(['tickets.auto_close_days' => 2]);

    $requester = User::factory()->create(['name' => 'Ana Cliente']);
    $ticket = Ticket::factory()->create(['requester_id' => $requester->id]);

    $notification = new TicketResolvedNotification($ticket);
    $mailText = implode(' ', $notification->toMail($requester)->introLines)
        .' '.implode(' ', $notification->toMail($requester)->outroLines);
    $databaseBody = $notification->toDatabase($requester)['body'];

    expect($mailText)->toContain('se cerrará automáticamente 2 días después')
        ->toContain($ticket->number)
        ->not->toContain('reabrir')
        ->and($databaseBody)->toContain('2 días')
        ->not->toContain('reabrir');
});

it('las encuestas avisan la calificación automática con los días configurados', function () {
    config([
        'tickets.csat_auto_positive_days' => 3,
        'tickets.maintenance_csat_auto_positive_days' => 1,
    ]);

    $requester = User::factory()->make(['name' => 'Ana Cliente']);
    $ticket = Ticket::factory()->make(['number' => 'TK-0001', 'subject' => 'No abre SAP']);

    $ticketMail = (new SatisfactionSurveyNotification($ticket, (new SatisfactionSurvey)->forceFill(['token' => 'abc'])))
        ->toMail($requester);

    $maintenanceMail = (new AssetMaintenanceSurveyNotification(
        (new Asset)->forceFill(['id' => 1, 'hostname' => 'PC-01']),
        (new MaintenanceSurvey)->forceFill(['token' => 'def']),
    ))->toMail($requester);

    expect(implode(' ', $ticketMail->outroLines))->toContain('Si no respondes en 3 días')
        ->and(implode(' ', $maintenanceMail->outroLines))->toContain('Si no respondes en 1 día,');
});

it('el portal explica qué pasa con un ticket resuelto', function () {
    config(['tickets.auto_close_days' => 2]);

    $requester = User::factory()->create();
    $ticket = Ticket::factory()->create([
        'requester_id' => $requester->id,
        'status' => TicketStatus::Resuelto,
        'resolved_at' => now(),
    ]);

    Livewire::actingAs($requester)
        ->test(PortalViewTicket::class, ['ticket' => $ticket])
        ->assertSee('Se cerrará automáticamente 2 días después de resuelto')
        ->assertSee($ticket->number);
});

it('las acciones de trabajo del ticket describen el SLA y el cierre automático vigentes', function () {
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);
    Notification::fake();
    Filament::setCurrentPanel(Filament::getPanel('soporte'));
    config(['tickets.auto_close_days' => 2]);

    $department = Department::factory()->create();
    $agent = User::factory()->create(['department_id' => $department->id]);
    $agent->assignRole('agente_soporte');

    $ticket = Ticket::factory()->create([
        'department_id' => $department->id,
        'status' => TicketStatus::EnProgreso,
    ]);

    $this->actingAs($agent);

    Livewire::test(SoporteViewTicket::class, ['record' => $ticket->getRouteKey()])
        ->assertSee('cuenta como primera respuesta del SLA')
        ->assertDontSee('arranca el reloj de SLA')
        ->mountAction('resolve')
        ->assertMountedActionModalSee('el ticket se cierra solo 2 días después');
});

it('el error 402 del proveedor de IA apunta a la cuenta del proveedor vigente', function () {
    config([
        'services.llm.api_key' => 'clave-valida',
        'services.llm.provider' => 'nvidia',
        'services.llm.model' => 'algun/modelo',
    ]);

    Http::fake(['integrate.api.nvidia.com/v1/chat/*' => Http::response([], 402)]);

    $result = app(LlmService::class)->healthCheck();

    expect($result['title'])->toBe('Sin créditos disponibles')
        ->and($result['detail'])->toContain('build.nvidia.com')
        ->not->toContain('openrouter');
});

it('los textos de la aplicación no usan voseo', function () {
    $voseo = '/(?<!\p{L})(?:(?:seleccion|busc|record|filtr|aplic|empez|cheque|llam|verific|cambi|confirm|guard|precarg|ajust)á|(?:hac|ten|pod|quer|sab)és|corré|hacé|subí|seleccionás)(?!\p{L})/iu';

    $files = collect([app_path(), resource_path('views'), base_path('routes')])
        ->flatMap(fn (string $path): array => iterator_to_array(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)),
        ))
        ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'php');

    $offenders = $files
        ->filter(fn (SplFileInfo $file): bool => preg_match($voseo, file_get_contents($file->getPathname())) === 1)
        ->map(fn (SplFileInfo $file): string => $file->getPathname())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
