<?php

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\KbArticle;
use App\Models\User;
use App\Services\ChatbotService;
use App\Services\LlmService;

/**
 * Cubre el tracking de calidad de respuestas (#6c):
 *  - Cada respuesta del asistente queda persistida con source_kind.
 *  - Cuando la respuesta viene de la KB, se almacena kb_article_id +
 *    similarity para el reporte de métricas.
 *  - Los fallback genéricos quedan marcados como tales y pueden contarse
 *    como "gaps de KB" en el reporte.
 */
beforeEach(function () {
    $this->user = User::factory()->create();

    // Forzamos al LLM a devolver null para que el fallback path sea
    // determinístico independientemente de si la .env tiene API key.
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('chat')->andReturn(null);
        $mock->shouldReceive('embed')->andReturn(null);
    });

    $this->service = app(ChatbotService::class);
});

it('tags KB-based responses with source_kind=kb_high and the article id', function () {
    // Insertar un artículo KB sobre VPN que el RAG keyword search va a
    // matchear cuando preguntemos exactamente por VPN.
    $article = KbArticle::factory()->create([
        'title' => 'Configurar VPN corporativa',
        'body' => 'Para conectarte a la VPN corporativa Confipetrol abre Cisco AnyConnect, ingresa vpn.confipetrol.com y autentica con tu correo.',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $session = ChatSession::create([
        'user_id' => $this->user->id,
        'status' => 'active',
        'channel' => 'web',
    ]);

    $this->service->handleMessage($session, '¿cómo configurar VPN corporativa Confipetrol AnyConnect?');

    $assistant = ChatMessage::query()
        ->where('chat_session_id', $session->id)
        ->where('role', 'assistant')
        ->latest()
        ->first();

    expect($assistant)->not->toBeNull();
    expect($assistant->source_kind)->toBeIn(['kb_high', 'kb_medium']);
    expect($assistant->kb_article_id)->toBe($article->id);
    expect($assistant->similarity)->toBeGreaterThan(0.0);
});

it('tags responses without any KB match as fallback', function () {
    // Sin KB articles publicados, el bot cae a fallback (no hay LLM key
    // en entorno de test, así que llm->chat devuelve null).
    $session = ChatSession::create([
        'user_id' => $this->user->id,
        'status' => 'active',
        'channel' => 'web',
    ]);

    $this->service->handleMessage($session, 'kjasdf qwerty zzz consulta sin sentido');

    $assistant = ChatMessage::query()
        ->where('chat_session_id', $session->id)
        ->where('role', 'assistant')
        ->latest()
        ->first();

    expect($assistant)->not->toBeNull();
    expect($assistant->source_kind)->toBe('fallback');
    expect($assistant->kb_article_id)->toBeNull();
});

it('tags ticket escalation prompts as source_kind=system', function () {
    $session = ChatSession::create([
        'user_id' => $this->user->id,
        'status' => 'active',
        'channel' => 'web',
    ]);

    $this->service->handleMessage($session, 'crear ticket');

    $assistant = ChatMessage::query()
        ->where('chat_session_id', $session->id)
        ->where('role', 'assistant')
        ->latest()
        ->first();

    expect($assistant->source_kind)->toBe('system');
});

it('persists user feedback on an assistant message', function () {
    $session = ChatSession::create([
        'user_id' => $this->user->id,
        'status' => 'active',
        'channel' => 'web',
    ]);

    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Ejemplo',
        'source_kind' => 'kb_high',
    ]);

    $message->update([
        'helpful' => true,
        'feedback_at' => now(),
    ]);

    $fresh = $message->fresh();
    expect($fresh->helpful)->toBeTrue();
    expect($fresh->feedback_at)->not->toBeNull();
});
