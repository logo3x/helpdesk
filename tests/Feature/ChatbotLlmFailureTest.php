<?php

use App\Models\ChatSession;
use App\Models\KbArticle;
use App\Models\User;
use App\Services\ChatbotService;
use App\Services\LlmService;

/**
 * Distingue dos situaciones que antes se guardaban ambas como
 * source_kind = 'fallback' y por eso eran indistinguibles en las
 * métricas del chatbot:
 *
 *   - 'fallback'   → la KB no devolvió NADA. El LLM nunca se llamó.
 *                    Acción: escribir el artículo que falta.
 *   - 'llm_failed' → la KB sí devolvió algo, se llamó al LLM y este
 *                    falló (rate limit, API key, timeout).
 *                    Acción: revisar el servicio de IA.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->session = ChatSession::create([
        'user_id' => $this->user->id,
        'status' => 'active',
        'channel' => 'web',
    ]);
});

it('marca llm_failed cuando hay contexto de KB pero el LLM devuelve vacío', function () {
    $this->mock(LlmService::class, function ($mock) {
        // Simula rate limit / API key inválida: chat() devuelve null.
        $mock->shouldReceive('chat')->andReturn(null);
        $mock->shouldReceive('embed')->andReturn(null);
    });

    // Artículo que matchea parcialmente (solo en el cuerpo), suficiente
    // para pasar el guard de $topKb === null pero no para responder
    // literal. Eso fuerza el camino que llama al LLM.
    KbArticle::factory()->create([
        'title' => 'Configurar VPN FortiClient',
        'slug' => 'configurar-vpn-forticlient',
        'body' => 'Instala FortiClient en tu equipo. Si el equipo no conecta, '
            .'verifica que el certificado esté vigente y reinicia el servicio.',
        'status' => 'published',
        'published_at' => now(),
    ]);

    [$response, $meta] = app(ChatbotService::class)
        ->handleMessageWithMeta($this->session, 'el equipo no conecta al certificado');

    expect($meta['source_kind'])->toBe('llm_failed');
    // Guarda el artículo que sí tenía, para poder diagnosticar después.
    expect($meta['kb_article_id'])->not->toBeNull();
});

it('marca fallback (no llm_failed) cuando la KB no devuelve nada', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('chat')->andReturn(null);
        $mock->shouldReceive('embed')->andReturn(null);
    });

    // KB vacía: no hay ningún artículo publicado.
    [$response, $meta] = app(ChatbotService::class)
        ->handleMessageWithMeta($this->session, 'xyzzy plugh quux');

    expect($meta['source_kind'])->toBe('fallback');
    expect($meta['kb_article_id'])->toBeNull();
});

it('marca llm cuando el LLM sí responde', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('chat')->andReturn('Respuesta generada por el modelo.');
        $mock->shouldReceive('embed')->andReturn(null);
    });

    KbArticle::factory()->create([
        'title' => 'Configurar VPN FortiClient',
        'slug' => 'configurar-vpn-forticlient',
        'body' => 'Instala FortiClient en tu equipo. Si el equipo no conecta, '
            .'verifica que el certificado esté vigente y reinicia el servicio.',
        'status' => 'published',
        'published_at' => now(),
    ]);

    [$response, $meta] = app(ChatbotService::class)
        ->handleMessageWithMeta($this->session, 'el equipo no conecta al certificado');

    expect($meta['source_kind'])->toBe('llm');
    expect($response)->toBe('Respuesta generada por el modelo.');
});
