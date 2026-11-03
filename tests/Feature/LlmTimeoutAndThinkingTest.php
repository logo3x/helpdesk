<?php

use App\Services\LlmService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * 2026-11-03: openai/gpt-oss-20b dejó de responder en NVIDIA (timeout).
 * El chatbot reintentaba 3 veces × 60 s antes de probar el respaldo, y
 * el usuario esperaba minutos. Ahora un timeout pasa directo al modelo
 * de respaldo, y se puede apagar el "razonamiento" del modelo.
 */
beforeEach(function () {
    config([
        'services.llm.provider' => 'nvidia',
        'services.llm.api_key' => 'test-key',
        'services.llm.model' => 'principal/lento',
        'services.llm.fallback_model' => 'respaldo/rapido',
        'services.llm.disable_thinking' => false,
    ]);
});

it('si el modelo principal no contesta pasa al respaldo sin reintentar', function () {
    $calls = [];

    Http::fake(function (Request $request) use (&$calls) {
        $calls[] = $request['model'];

        if ($request['model'] === 'principal/lento') {
            throw new ConnectionException('cURL error 28: Operation timed out');
        }

        return Http::response(['choices' => [['message' => ['content' => 'Hola desde el respaldo']]]]);
    });

    expect(app(LlmService::class)->chat([['role' => 'user', 'content' => 'hola']]))->toBe('Hola desde el respaldo')
        ->and($calls)->toBe(['principal/lento', 'respaldo/rapido']);
});

it('con LLM_DISABLE_THINKING pide al modelo NVIDIA que no razone', function () {
    config(['services.llm.disable_thinking' => true]);
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);

    app(LlmService::class)->chat([['role' => 'user', 'content' => 'hola']]);

    Http::assertSent(fn (Request $request) => $request['chat_template_kwargs'] === ['enable_thinking' => false]
        && $request['max_tokens'] === 4096);
});

it('sin la opción no envía parámetros extra al proveedor', function () {
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);

    app(LlmService::class)->chat([['role' => 'user', 'content' => 'hola']]);

    Http::assertSent(fn (Request $request) => ! isset($request['chat_template_kwargs']));
});
