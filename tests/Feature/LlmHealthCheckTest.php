<?php

use App\Services\LlmService;
use Illuminate\Support\Facades\Http;

/**
 * Diagnóstico del servicio de IA, usado por el botón
 * "Probar conexión con la IA" en Métricas del chatbot.
 *
 * Contexto (2026-09-16): el chatbot dejó de responder. Tres intentos
 * de cambiar el modelo fallaron porque el health check culpaba al
 * modelo ante cualquier error — chat() devuelve null sin decir por
 * qué. La causa real era un 401: la API key estaba revocada.
 *
 * Por eso el health check ahora hace su propia petición y traduce el
 * status HTTP a un diagnóstico accionable.
 */
$freeTextModel = fn (string $id, int $ctx = 100000): array => [
    'id' => $id,
    'context_length' => $ctx,
    'pricing' => ['prompt' => '0', 'completion' => '0'],
    'architecture' => [
        'input_modalities' => ['text'],
        'output_modalities' => ['text'],
    ],
];

it('reporta falta de API key sin llamar a la red', function () {
    Http::fake();
    config(['services.llm.api_key' => '']);

    $result = app(LlmService::class)->healthCheck();

    expect($result['ok'])->toBeFalse();
    expect($result['title'])->toBe('Falta la API key');
    Http::assertNothingSent();
});

it('reporta API key inválida ante un 401 (la causa real del incidente)', function () {
    config([
        'services.llm.api_key' => 'clave-revocada',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => 'algun/modelo:free',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/chat/*' => Http::response([
            'error' => ['message' => 'User not found.', 'code' => 401],
        ], 401),
    ]);

    $result = app(LlmService::class)->healthCheck();

    expect($result['ok'])->toBeFalse();
    expect($result['title'])->toBe('La API key no es válida');
    // No debe culpar al modelo cuando el problema es la autenticación.
    expect($result['title'])->not->toContain('modelo');
    expect($result['detail'])->toContain('openrouter.ai/keys');
    expect($result['detail'])->toContain('User not found.');
});

it('reporta modelo inexistente ante un 404 y sugiere alternativas', function () use ($freeTextModel) {
    config([
        'services.llm.api_key' => 'clave-valida',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => 'modelo/inexistente:free',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/chat/*' => Http::response(['error' => ['message' => 'No such model']], 404),
        'openrouter.ai/api/v1/models' => Http::response([
            'data' => [$freeTextModel('vigente/chat:free')],
        ], 200),
    ]);

    $result = app(LlmService::class)->healthCheck();

    expect($result['ok'])->toBeFalse();
    expect($result['title'])->toBe('El modelo no existe');
    expect($result['detail'])->toContain('vigente/chat:free');
});

it('traduce cada status HTTP a un diagnóstico propio', function (int $status, string $expectedTitle) {
    config([
        'services.llm.api_key' => 'clave-valida',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => 'algun/modelo',
    ]);

    Http::fake(['openrouter.ai/api/v1/chat/*' => Http::response([], $status)]);

    expect(app(LlmService::class)->healthCheck()['title'])->toBe($expectedTitle);
})->with([
    'sin créditos' => [402, 'Sin créditos disponibles'],
    'límite alcanzado' => [429, 'Límite de peticiones alcanzado'],
    'prohibido' => [403, 'La API key no es válida'],
    'petición inválida' => [400, 'El proveedor rechazó la petición'],
]);

it('muestra el detalle del upstream cuando OpenRouter lo envuelve', function () {
    config([
        'services.llm.api_key' => 'clave-valida',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => 'google/gemma-4-31b-it:free',
    ]);

    // Forma real en que OpenRouter reporta un fallo del proveedor
    // upstream: mensaje genérico + detalle en error.metadata.
    Http::fake([
        'openrouter.ai/api/v1/chat/*' => Http::response([
            'error' => [
                'message' => 'Provider returned error',
                'code' => 429,
                'metadata' => [
                    'provider_name' => 'Google AI Studio',
                    'raw' => 'Resource has been exhausted (e.g. check quota).',
                ],
            ],
        ], 429),
        'openrouter.ai/api/v1/models' => Http::response(['data' => []], 200),
    ]);

    $result = app(LlmService::class)->healthCheck();

    expect($result['ok'])->toBeFalse();
    // El mensaje genérico no basta: debe verse el detalle real.
    expect($result['detail'])->toContain('Resource has been exhausted');
    // Y debe aclarar que la cuota puede ser del upstream, no de la cuenta.
    expect($result['detail'])->toContain('upstream');
});

it('reporta error del proveedor ante un 5xx', function () {
    config([
        'services.llm.api_key' => 'clave-valida',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => 'algun/modelo',
    ]);

    Http::fake(['openrouter.ai/api/v1/chat/*' => Http::response([], 503)]);

    $result = app(LlmService::class)->healthCheck();

    expect($result['title'])->toBe('El proveedor tuvo un error interno');
    expect($result['detail'])->toContain('503');
});

it('reporta éxito con la latencia medida', function () {
    config([
        'services.llm.api_key' => 'clave-valida',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => 'algun/modelo:free',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/chat/*' => Http::response([
            'choices' => [['message' => ['content' => 'OK']]],
        ], 200),
    ]);

    $result = app(LlmService::class)->healthCheck();

    expect($result['ok'])->toBeTrue();
    expect($result['title'])->toBe('Conexión correcta');
    expect($result['detail'])->toContain('OK');
    expect($result['detail'])->toContain('ms');
});

it('reporta modelo sin configurar y sugiere opciones vigentes', function () use ($freeTextModel) {
    config([
        'services.llm.api_key' => 'clave-valida',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => '',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/models' => Http::response([
            'data' => [$freeTextModel('vigente/chat:free')],
        ], 200),
    ]);

    $result = app(LlmService::class)->healthCheck();

    expect($result['title'])->toBe('Falta configurar el modelo');
    expect($result['detail'])->toContain('vigente/chat:free');
});

it('descarta modelos de audio aunque declaren texto en la salida', function () {
    config([
        'services.llm.api_key' => 'clave-valida',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => '',
    ]);

    Http::fake([
        'openrouter.ai/api/v1/models' => Http::response([
            'data' => [
                [
                    // Multimodal de ENTRADA: válido como chatbot.
                    // Estructura real de google/gemma-4-31b-it:free.
                    'id' => 'google/gemma-4-31b-it:free',
                    'context_length' => 262144,
                    'pricing' => ['prompt' => '0', 'completion' => '0'],
                    'architecture' => [
                        'input_modalities' => ['image', 'text', 'video'],
                        'output_modalities' => ['text'],
                    ],
                ],
                [
                    // Modelo de música: produce audio ADEMÁS de texto.
                    // Estructura real de google/lyria-3-pro-preview.
                    'id' => 'google/lyria-3-pro-preview',
                    'context_length' => 999999,
                    'pricing' => ['prompt' => '0', 'completion' => '0'],
                    'architecture' => [
                        'input_modalities' => ['text', 'image'],
                        'output_modalities' => ['text', 'audio'],
                    ],
                ],
            ],
        ], 200),
    ]);

    $detail = app(LlmService::class)->healthCheck()['detail'];

    expect($detail)->toContain('google/gemma-4-31b-it:free');
    expect($detail)->not->toContain('lyria');
});

it('descarta embeddings y rerankers por nombre', function () {
    config([
        'services.llm.api_key' => 'clave-valida',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => '',
    ]);

    $textOnly = [
        'input_modalities' => ['text'],
        'output_modalities' => ['text'],
    ];
    $free = ['prompt' => '0', 'completion' => '0'];

    Http::fake([
        'openrouter.ai/api/v1/models' => Http::response([
            'data' => [
                ['id' => 'bueno/chat:free', 'context_length' => 100000, 'pricing' => $free, 'architecture' => $textOnly],
                ['id' => 'nvidia/nemotron-3-embed-1b:free', 'context_length' => 500000, 'pricing' => $free, 'architecture' => $textOnly],
                ['id' => 'nvidia/llama-rerank-vl-1b:free', 'context_length' => 400000, 'pricing' => $free, 'architecture' => $textOnly],
            ],
        ], 200),
    ]);

    $detail = app(LlmService::class)->healthCheck()['detail'];

    expect($detail)->toContain('bueno/chat:free');
    expect($detail)->not->toContain('embed');
    expect($detail)->not->toContain('rerank');
});

it('no sugiere modelos de pago', function () {
    config([
        'services.llm.api_key' => 'clave-valida',
        'services.llm.provider' => 'openrouter',
        'services.llm.model' => '',
    ]);

    $textOnly = [
        'input_modalities' => ['text'],
        'output_modalities' => ['text'],
    ];

    Http::fake([
        'openrouter.ai/api/v1/models' => Http::response([
            'data' => [
                [
                    'id' => 'gratis/chat:free',
                    'context_length' => 100000,
                    'pricing' => ['prompt' => '0', 'completion' => '0'],
                    'architecture' => $textOnly,
                ],
                [
                    'id' => 'premium/modelo-pago',
                    'context_length' => 200000,
                    'pricing' => ['prompt' => '0.003', 'completion' => '0.015'],
                    'architecture' => $textOnly,
                ],
            ],
        ], 200),
    ]);

    $detail = app(LlmService::class)->healthCheck()['detail'];

    expect($detail)->toContain('gratis/chat:free');
    expect($detail)->not->toContain('premium/modelo-pago');
});
