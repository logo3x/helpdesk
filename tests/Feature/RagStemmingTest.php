<?php

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\KbArticle;
use App\Models\User;
use App\Services\ChatbotService;
use App\Services\LlmService;
use App\Services\RagService;

/**
 * Cubre el stemmer español + búsqueda keyword del RAG.
 *
 * Caso real reportado: un usuario escribió "hola como instalo teams
 * en mi pc" y el bot respondió "no encontré información", aún cuando
 * existía el artículo "Cómo instalar Microsoft Teams". El problema
 * era que str_contains exacto no matchea "instalo" con "instalar".
 */
beforeEach(function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('chat')->andReturn(null);
        $mock->shouldReceive('embed')->andReturn(null);
    });

    $this->user = User::factory()->create();
    $this->service = app(ChatbotService::class);

    KbArticle::factory()->create([
        'title' => 'Cómo instalar Microsoft Teams',
        'slug' => 'como-instalar-microsoft-teams',
        'body' => "Teams es la herramienta oficial de Confipetrol para reuniones y chat.\n\n"
            ."## Descarga oficial\n\nSolo descarga desde https://teams.microsoft.com/downloads\n\n"
            .'## Instalación Windows\n\n1. Ejecuta el instalador.\n2. Inicia sesión con tu correo corporativo.',
        'status' => 'published',
        'published_at' => now(),
    ]);
});

it('matches natural-language queries against the KB title (the reported bug)', function () {
    $rag = app(RagService::class);
    $results = $rag->search('hola como instalo teams en mi pc', topN: 3);

    expect($results)->not->toBeEmpty();
    expect($results->first()['article_title'])->toBe('Cómo instalar Microsoft Teams');
    // Con stemming el matching es fuerte: "instalo"→"instal" matches
    // "instalar"→"instal"; "teams"→"team" matches "teams"→"team".
    expect($results->first()['similarity'])->toBeGreaterThan(0.5);
});

it('matches verb conjugations (instalo / instalando / instalaron)', function () {
    $rag = app(RagService::class);

    foreach (['instalo teams', 'estoy instalando teams', 'instalaron teams en mi pc'] as $query) {
        $results = $rag->search($query, topN: 1);
        expect($results)->not->toBeEmpty("Query falló: '{$query}'");
        expect($results->first()['article_title'])->toBe('Cómo instalar Microsoft Teams');
    }
});

it('matches plurals (contraseñas → contraseña)', function () {
    KbArticle::factory()->create([
        'title' => 'Resetear contraseña',
        'slug' => 'resetear-contrasena',
        'body' => 'Para cambiar tu contraseña en el directorio activo.',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $rag = app(RagService::class);
    $results = $rag->search('reset de mis contraseñas', topN: 1);

    expect($results)->not->toBeEmpty();
    expect($results->first()['article_title'])->toBe('Resetear contraseña');
});

it('handles the reported natural language query end-to-end via ChatbotService', function () {
    $session = ChatSession::create([
        'user_id' => $this->user->id,
        'status' => 'active',
        'channel' => 'web',
    ]);

    $this->service->handleMessage($session, 'hola como instalo teams en mi pc');

    $assistant = ChatMessage::query()
        ->where('chat_session_id', $session->id)
        ->where('role', 'assistant')
        ->latest()
        ->first();

    expect($assistant->source_kind)->toBeIn(['kb_high', 'kb_medium']);
    expect($assistant->content)->toContain('Cómo instalar Microsoft Teams');
});

it('still returns fallback for queries with zero overlap (no false positives)', function () {
    $session = ChatSession::create([
        'user_id' => $this->user->id,
        'status' => 'active',
        'channel' => 'web',
    ]);

    $this->service->handleMessage($session, 'qwertyuiop zxcvbnm asdfghjkl');

    $assistant = ChatMessage::query()
        ->where('chat_session_id', $session->id)
        ->where('role', 'assistant')
        ->latest()
        ->first();

    expect($assistant->source_kind)->toBe('fallback');
});

it('title hits score higher than body-only hits', function () {
    KbArticle::factory()->create([
        'title' => 'Política de uso aceptable',
        'slug' => 'politica-uso-aceptable',
        'body' => 'En Confipetrol el uso de Microsoft Teams para reuniones está autorizado.',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $rag = app(RagService::class);
    $results = $rag->search('como instalar teams', topN: 5);

    expect($results)->not->toBeEmpty();
    // El artículo cuyo TÍTULO menciona "instalar teams" debe estar arriba
    // del que solo lo menciona en el body.
    expect($results->first()['article_title'])->toBe('Cómo instalar Microsoft Teams');
});
