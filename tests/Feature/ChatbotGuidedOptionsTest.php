<?php

use App\Livewire\Portal\Chatbot;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\KbArticle;
use App\Models\User;
use App\Services\ChatbotService;
use App\Services\LlmService;
use Livewire\Livewire;

beforeEach(function () {
    config(['services.llm.personalize_kb_answers' => true]);

    $this->user = User::factory()->create();
    $this->session = ChatSession::create([
        'user_id' => $this->user->id,
        'status' => 'active',
        'channel' => 'web',
    ]);

    $this->article = KbArticle::factory()->create([
        'title' => 'Instalar Windows App para ingreso a SAP',
        'slug' => 'instalar-windows-app-para-ingreso-a-sap',
        'body' => "## Requisitos\nEl usuario de SAP empieza por cp_.\n\n## Paso 1\nInstala **Windows App** desde Microsoft Store.",
        'status' => 'published',
        'published_at' => now(),
    ]);
});

it('separa la línea OPCIONES del texto de la IA', function () {
    [$text, $options] = ChatbotService::extractOptions(
        "Entiendo, no logras entrar a SAP. ¿Qué te pasa?\nOPCIONES: No tengo Windows App | Me pide usuario | **Me sale un error** | Otra cosa | Sobra"
    );

    expect($text)->toBe('Entiendo, no logras entrar a SAP. ¿Qué te pasa?')
        ->and($options)->toBe(['No tengo Windows App', 'Me pide usuario', 'Me sale un error', 'Otra cosa']);
});

it('sin línea OPCIONES no hay respuestas sugeridas', function () {
    expect(ChatbotService::extractOptions('Listo, ya quedó.'))->toBe(['Listo, ya quedó.', []]);
});

it('guarda las opciones y continúa con el mismo artículo cuando el usuario elige una', function () {
    $calls = 0;

    $this->mock(LlmService::class, function ($mock) use (&$calls) {
        $mock->shouldReceive('embed')->andReturn(null);
        $mock->shouldReceive('chat')->andReturnUsing(function (array $history, string $prompt) use (&$calls) {
            $calls++;

            expect($prompt)->toContain('El usuario de SAP empieza por cp_');

            return $calls === 1
                ? "Entiendo, no logras entrar a SAP. ¿Qué te pasa?\nOPCIONES: No tengo Windows App | No sé mi usuario"
                : 'Tranquilo: tu usuario de SAP empieza por **cp_**. ¿Te funcionó?';
        });
    });

    $service = app(ChatbotService::class);

    [$first, $meta] = $service->handleMessageWithMeta($this->session, 'no me abre sap windows app');

    expect($meta['options'])->toBe(['No tengo Windows App', 'No sé mi usuario'])
        ->and($first)->not->toContain('OPCIONES')
        ->and(ChatMessage::where('role', 'assistant')->latest('id')->first()->metadata['options'])->toHaveCount(2);

    // "No sé mi usuario" por sí solo no encuentra el artículo en la KB,
    // pero la conversación sigue con el mismo artículo.
    [$second, $secondMeta] = $service->handleMessageWithMeta($this->session, 'No sé mi usuario');

    expect($second)->toContain('cp_')
        ->and($secondMeta['kb_article_id'])->toBe($this->article->id)
        ->and($calls)->toBe(2);
});

it('después de varias preguntas seguidas le pide a la IA que dé la solución', function () {
    $prompts = [];

    $this->mock(LlmService::class, function ($mock) use (&$prompts) {
        $mock->shouldReceive('embed')->andReturn(null);
        $mock->shouldReceive('chat')->andReturnUsing(function (array $history, string $prompt) use (&$prompts) {
            $prompts[] = $prompt;

            return "¿Y ahora qué ves?\nOPCIONES: Opción A | Opción B";
        });
    });

    $service = app(ChatbotService::class);
    $service->handleMessageWithMeta($this->session, 'no me abre sap windows app');
    $service->handleMessageWithMeta($this->session, 'Opción A');
    $service->handleMessageWithMeta($this->session, 'Opción B');

    expect($prompts[0])->not->toContain('NO preguntes más')
        ->and($prompts[2])->toContain('NO preguntes más');
});

it('el portal muestra las opciones como botones y al tocarlas las envía', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('embed')->andReturn(null);
        $mock->shouldReceive('chat')->andReturn(
            "Entiendo. ¿Qué te pasa?\nOPCIONES: No tengo Windows App | No sé mi usuario",
            'Tu usuario de SAP empieza por **cp_**.',
        );
    });

    $this->actingAs($this->user);

    Livewire::test(Chatbot::class)
        ->set('message', 'no me abre sap windows app')
        ->call('send')
        ->assertSee('No tengo Windows App')
        ->assertSee('Hablar con un agente')
        ->call('chooseOption', 'No sé mi usuario')
        ->assertSee('cp_');
});

it('ignora opciones que no se ofrecieron', function () {
    $this->actingAs($this->user);

    Livewire::test(Chatbot::class)
        ->call('chooseOption', 'texto inventado')
        ->assertDontSee('texto inventado');
});
