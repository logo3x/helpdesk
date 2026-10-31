<?php

use App\Filament\Soporte\Resources\KbArticles\Pages\EditKbArticle;
use App\Jobs\GenerateKbSuggestionsJob;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\User;
use App\Notifications\KbSuggestionCreatedNotification;
use App\Services\KbSuggestionService;
use App\Services\LlmService;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ShieldPermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([RoleSeeder::class, ShieldPermissionSeeder::class]);
    Notification::fake();
    config(['services.llm.kb_suggestions_enabled' => true]);

    $this->ti = Department::factory()->create(['name' => 'Tecnología de la Información']);
    $this->rrhh = Department::factory()->create(['name' => 'Recursos Humanos']);

    $this->supervisorTi = User::factory()->create(['department_id' => $this->ti->id]);
    $this->supervisorTi->assignRole('supervisor_soporte');
    $this->supervisorRrhh = User::factory()->create(['department_id' => $this->rrhh->id]);
    $this->supervisorRrhh->assignRole('supervisor_soporte');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $session = ChatSession::create(['user_id' => User::factory()->create()->id, 'status' => 'active', 'channel' => 'web']);

    foreach (['como conecto la impresora del piso 3', 'no imprime la impresora del piso 3'] as $query) {
        ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => $query]);
        $this->gaps[] = ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'assistant', 'content' => 'No encontré información', 'source_kind' => 'fallback']);
    }
});

function fakeSuggestionLlm(): void
{
    test()->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('embed')->andReturn(null);
        $mock->shouldReceive('chat')->andReturn(json_encode([[
            'title' => 'Conectar la impresora del piso 3',
            'department' => 'Tecnología de la Información',
            'reason' => 'Dos personas preguntaron y no hay artículo.',
            'queries' => ['como conecto la impresora del piso 3', 'no imprime la impresora del piso 3'],
            'description' => 'Cómo agregar la impresora del piso 3 en Windows.',
        ]]));
        $mock->shouldReceive('draftKbArticle')->andReturn([
            'title' => 'Cómo conectar la impresora del piso 3',
            'body' => "## Pasos\n1. Abre **Configuración**.",
        ]);
    });
}

it('crea un borrador sugerido con autor sistema y pendiente de revisión', function () {
    fakeSuggestionLlm();

    $created = app(KbSuggestionService::class)->run();

    expect($created)->toHaveCount(1);

    $article = $created->first()->fresh();

    expect($article->status)->toBe('draft')
        ->and($article->is_ai_suggestion)->toBeTrue()
        ->and($article->department_id)->toBe($this->ti->id)
        ->and($article->author->email)->toBe(KbSuggestionService::SYSTEM_USER_EMAIL)
        ->and($article->pending_review_at)->not->toBeNull()
        ->and($article->suggestion_context['queries'])->toContain('no imprime la impresora del piso 3');
});

it('notifica a los supervisores del departamento y a los administradores', function () {
    fakeSuggestionLlm();

    app(KbSuggestionService::class)->run();

    Notification::assertSentTo([$this->supervisorTi, $this->admin], KbSuggestionCreatedNotification::class);
    Notification::assertNotSentTo($this->supervisorRrhh, KbSuggestionCreatedNotification::class);
});

it('no vuelve a analizar las mismas consultas', function () {
    fakeSuggestionLlm();
    $service = app(KbSuggestionService::class);

    $service->run();

    expect($service->pendingGaps())->toBeEmpty()
        ->and($service->run())->toBeEmpty();
});

it('no crea sugerencias si ya existe un artículo con ese tema', function () {
    fakeSuggestionLlm();
    KbArticle::factory()->create([
        'title' => 'Conectar la impresora del piso 3',
        'slug' => 'conectar-la-impresora-del-piso-3',
        'status' => 'published',
        'published_at' => now(),
    ]);

    expect(app(KbSuggestionService::class)->run())->toBeEmpty();
});

it('si la IA dice que nada amerita artículo no crea nada', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('chat')->andReturn('[]');
        $mock->shouldNotReceive('draftKbArticle');
    });

    expect(app(KbSuggestionService::class)->run())->toBeEmpty();
    Notification::assertNothingSent();
});

it('el job no hace nada si la función está apagada', function () {
    config(['services.llm.kb_suggestions_enabled' => false]);
    $this->mock(LlmService::class, fn ($mock) => $mock->shouldNotReceive('chat'));

    (new GenerateKbSuggestionsJob)->handle(app(KbSuggestionService::class));

    expect(KbArticle::count())->toBe(0);
});

it('el formulario muestra el motivo y las preguntas de la sugerencia', function () {
    fakeSuggestionLlm();
    $article = app(KbSuggestionService::class)->run()->first();

    Filament::setCurrentPanel(Filament::getPanel('soporte'));
    $this->actingAs($this->supervisorTi);

    Livewire::test(EditKbArticle::class, ['record' => $article->getRouteKey()])
        ->assertSee('Sugerencia generada por la IA')
        ->assertSee('Dos personas preguntaron y no hay artículo.')
        ->assertSee('no imprime la impresora del piso 3')
        ->assertActionVisible('approveAndPublish');
});

it('el usuario de sistema no recibe correos', function () {
    $system = app(KbSuggestionService::class)->systemUser();

    expect($system->routeNotificationForMail())->toBeNull()
        ->and($system->roles)->toBeEmpty();
});
