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
