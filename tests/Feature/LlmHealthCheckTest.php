<?php

use App\Services\LlmService;
use Illuminate\Support\Facades\Http;

/**
 * Diagnóstico del servicio de IA, usado por el botón
 * "Probar conexión con la IA" en Métricas del chatbot.
 *
 * Contexto: el 2026-09-16 el chatbot dejó de responder porque
 * OpenRouter descontinuó el modelo llama-3.1-8b-instruct:free.
 * La API devolvía 404 y el único rastro era un "unexpected error"
 * genérico en el log. Este health check existe para que el
 * diagnóstico no requiera entrar al servidor.
 */
it('reporta falta de API key sin llamar a la red', function () {
    Http::fake(); // si intentara llamar, fallaría el assertNothingSent

    config(['services.llm.api_key' => '']);

    $result = app(LlmService::class)->healthCheck();

    expect($result['ok'])->toBeFalse();
    expect($result['title'])->toBe('Falta la API key');
    Http::assertNothingSent();
});

it('reporta éxito cuando el modelo responde', function () {
    config([
        'services.llm.api_key' => 'test-key',
        'services.llm.provider' => 'openrouter',
    ]);

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => 'OK']]],
        ], 200),
    ]);

    $result = app(LlmService::class)->healthCheck();

    expect($result['ok'])->toBeTrue();
    expect($result['title'])->toBe('Conexión correcta');
    expect($result['detail'])->toContain('OK');
});

it('reporta fallo y menciona modelos descontinuados cuando el modelo no existe', function () {
    config([
        'services.llm.api_key' => 'test-key',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => 'modelo/que-ya-no-existe:free',
    ]);

    Http::fake([
        'openrouter.ai/*' => Http::response(['error' => 'No such model'], 404),
    ]);

    $result = app(LlmService::class)->healthCheck();

    expect($result['ok'])->toBeFalse();
    expect($result['title'])->toBe('El modelo no respondió');
    // El mensaje debe orientar al diagnóstico real.
    expect($result['detail'])->toContain('404');
    expect($result['detail'])->toContain('modelo/que-ya-no-existe:free');
});

it('sugiere modelos gratuitos vigentes cuando el configurado falla', function () {
    config([
        'services.llm.api_key' => 'test-key',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => 'modelo/descontinuado:free',
    ]);

    Http::fake([
        // La petición de chat falla (modelo inexistente).
        'openrouter.ai/api/v1/chat/*' => Http::response(['error' => 'No such model'], 404),
        // El catálogo sí responde con modelos vigentes.
        'openrouter.ai/api/v1/models' => Http::response([
            'data' => [
                [
                    'id' => 'vigente/modelo-grande:free',
                    'context_length' => 128000,
                    'pricing' => ['prompt' => '0', 'completion' => '0'],
                ],
                [
                    'id' => 'vigente/modelo-chico:free',
                    'context_length' => 8000,
                    'pricing' => ['prompt' => '0', 'completion' => '0'],
                ],
                [
                    // De pago: no debe sugerirse.
                    'id' => 'premium/modelo-pago',
                    'context_length' => 200000,
                    'pricing' => ['prompt' => '0.003', 'completion' => '0.015'],
                ],
            ],
        ], 200),
    ]);

    $result = app(LlmService::class)->healthCheck();

    expect($result['ok'])->toBeFalse();
    expect($result['detail'])->toContain('vigente/modelo-grande:free');
    expect($result['detail'])->toContain('vigente/modelo-chico:free');
    // Los de pago no se sugieren.
    expect($result['detail'])->not->toContain('premium/modelo-pago');
});

it('degrada elegantemente si el catálogo de modelos no responde', function () {
    config([
        'services.llm.api_key' => 'test-key',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => 'modelo/descontinuado:free',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/chat/*' => Http::response(['error' => 'No such model'], 404),
        'openrouter.ai/api/v1/models' => Http::response([], 500),
    ]);

    $result = app(LlmService::class)->healthCheck();

    expect($result['ok'])->toBeFalse();
    // Sin catálogo, cae al mensaje con la guía de códigos HTTP.
    expect($result['detail'])->toContain('404');
});

it('incluye siempre proveedor y modelo en el resultado', function () {
    config([
        'services.llm.api_key' => 'test-key',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => 'google/gemini-2.0-flash-exp:free',
    ]);

    Http::fake([
        'openrouter.ai/*' => Http::response([
            'choices' => [['message' => ['content' => 'OK']]],
        ], 200),
    ]);

    $result = app(LlmService::class)->healthCheck();

    expect($result['provider'])->toBe('openrouter');
    expect($result['model'])->toBe('google/gemini-2.0-flash-exp:free');
});
