<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Unified LLM interface. Supports two providers via LLM_PROVIDER env:
 *
 * - "openrouter" (default) — routes to any model via OpenRouter API
 * - "anthropic" — direct Anthropic Claude API
 *
 * Switch provider by changing LLM_PROVIDER and the corresponding API key.
 */
class LlmService
{
    protected string $provider;

    protected string $model;

    protected string $apiKey;

    public function __construct()
    {
        $this->provider = config('services.llm.provider', 'openrouter');
        $this->model = config('services.llm.model', 'google/gemma-4-31b:free');
        $this->apiKey = config('services.llm.api_key', '');
    }

    /**
     * Prueba la conexión con el proveedor y devuelve un diagnóstico
     * legible. Pensado para el botón "Probar conexión" de la página
     * de Métricas del chatbot.
     *
     * No lanza excepciones: siempre devuelve el array de resultado.
     *
     * @return array{ok: bool, title: string, detail: string, provider: string, model: string}
     */
    public function healthCheck(): array
    {
        $base = [
            'provider' => $this->provider,
            'model' => $this->model,
        ];

        if (blank($this->apiKey)) {
            return $base + [
                'ok' => false,
                'title' => 'Falta la API key',
                'detail' => 'La variable LLM_API_KEY está vacía en el archivo .env del servidor. '
                    .'Sin ella el asistente no puede generar respuestas.',
            ];
        }

        $start = microtime(true);

        try {
            $reply = $this->chat(
                [['role' => 'user', 'content' => 'Responde únicamente con la palabra: OK']],
                'Eres un servicio de verificación. Responde solo lo que se te pide.',
            );
        } catch (\Throwable $e) {
            return $base + [
                'ok' => false,
                'title' => 'Error inesperado',
                'detail' => mb_substr($e->getMessage(), 0, 300),
            ];
        }

        $ms = (int) round((microtime(true) - $start) * 1000);

        if (blank($reply)) {
            // Al fallar, consultamos el catálogo real del proveedor en
            // vez de adivinar: así el mensaje sugiere modelos que SÍ
            // existen hoy, no los que existían cuando se escribió esto.
            $suggestions = $this->suggestAvailableModels();

            $detail = "El proveedor rechazó la petición o el modelo «{$this->model}» ya no existe. ";

            if ($suggestions !== []) {
                $detail .= "\n\nModelos gratuitos disponibles ahora mismo en tu cuenta:\n· "
                    .implode("\n· ", $suggestions)
                    ."\n\nCopia uno de estos a LLM_MODEL en el .env del servidor, "
                    .'luego ejecuta config:clear y config:cache.';
            } else {
                $detail .= 'No se pudo obtener el catálogo de modelos del proveedor. '
                    .'Revisa storage/logs/laravel.log (busca «LlmService») para ver el código HTTP: '
                    .'404 = modelo inexistente · 401 = API key inválida · 429 = sin cuota.';
            }

            return $base + [
                'ok' => false,
                'title' => 'El modelo no respondió',
                'detail' => $detail,
            ];
        }

        return $base + [
            'ok' => true,
            'title' => 'Conexión correcta',
            'detail' => "El modelo respondió en {$ms} ms. Respuesta recibida: «".mb_substr(trim($reply), 0, 80).'»',
        ];
    }

    /**
     * Consulta el catálogo de OpenRouter y devuelve hasta 6 ids de
     * modelos gratuitos vigentes, ordenados por context window.
     *
     * Devuelve [] si el proveedor no es OpenRouter o si la consulta
     * falla — el caller degrada el mensaje elegantemente.
     *
     * @return array<int, string>
     */
    protected function suggestAvailableModels(): array
    {
        if ($this->provider !== 'openrouter') {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
            ])
                ->timeout(15)
                ->get('https://openrouter.ai/api/v1/models');

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('data', []))
                // Solo los gratuitos: prompt y completion a costo cero.
                ->filter(function (array $m): bool {
                    $pricing = $m['pricing'] ?? [];

                    return ((float) ($pricing['prompt'] ?? 1)) === 0.0
                        && ((float) ($pricing['completion'] ?? 1)) === 0.0;
                })
                // Solo modelos de CHAT que reciben y devuelven texto.
                // OpenRouter mezcla en el mismo endpoint modelos de audio
                // (lyria, tts, whisper), de imagen y de embeddings; sin
                // este filtro el diagnóstico sugería modelos de música.
                ->filter(function (array $m): bool {
                    $arch = $m['architecture'] ?? [];
                    $inputs = $arch['input_modalities'] ?? [];
                    $outputs = $arch['output_modalities'] ?? [];

                    // Si el proveedor no declara modalidades, no podemos
                    // garantizar que sea de texto — mejor descartarlo.
                    if ($inputs === [] || $outputs === []) {
                        return false;
                    }

                    return in_array('text', $inputs, true)
                        && in_array('text', $outputs, true);
                })
                // Descartamos por nombre lo que la modalidad no captura:
                // embeddings y rerankers declaran texto→texto pero no
                // sirven para conversar. Los "thinking" gastan tokens
                // razonando antes de responder, lo que añade latencia
                // sin valor para un chatbot de KB.
                ->reject(function (array $m): bool {
                    $id = mb_strtolower((string) ($m['id'] ?? ''));

                    foreach (['thinking', 'embed', 'rerank', 'tts', 'whisper', 'guard', 'safety'] as $needle) {
                        if (str_contains($id, $needle)) {
                            return true;
                        }
                    }

                    return false;
                })
                ->sortByDesc(fn (array $m): int => (int) ($m['context_length'] ?? 0))
                ->take(6)
                ->pluck('id')
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::warning('LlmService: no se pudo obtener el catálogo de modelos', [
                'error' => mb_substr($e->getMessage(), 0, 200),
            ]);

            return [];
        }
    }

    /**
     * Generate a chat completion.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function chat(array $messages, ?string $systemPrompt = null): ?string
    {
        if (blank($this->apiKey)) {
            Log::warning('LlmService: No API key configured. Set LLM_API_KEY in .env');

            return null;
        }

        return match ($this->provider) {
            'anthropic' => $this->chatAnthropic($messages, $systemPrompt),
            default => $this->chatOpenRouter($messages, $systemPrompt),
        };
    }

    /**
     * Generate embeddings for a text string.
     *
     * @return array<int, float>|null
     */
    public function embed(string $text): ?array
    {
        if (blank($this->apiKey)) {
            return null;
        }

        $embeddingModel = config('services.llm.embedding_model', 'nomic-ai/nomic-embed-text-v1.5');

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
            ])
                ->timeout(30)
                ->post('https://openrouter.ai/api/v1/embeddings', [
                    'model' => $embeddingModel,
                    'input' => $text,
                ]);

            if ($response->successful()) {
                return $response->json('data.0.embedding');
            }

            Log::warning('LlmService embed failed', ['status' => $response->status(), 'body' => $response->body()]);
        } catch (ConnectionException $e) {
            Log::error('LlmService embed connection error', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * OpenRouter — OpenAI-compatible API.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    protected function chatOpenRouter(array $messages, ?string $systemPrompt): ?string
    {
        // 2048 tokens da margen para modelos "reasoning" (que gastan tokens
        // en <thinking>...</thinking> antes del response real). Para
        // modelos normales 1024 era suficiente, pero subir el techo evita
        // que un usuario que elija un modelo reasoning por error reciba
        // null porque el modelo agotó el budget pensando.
        $payload = [
            'model' => $this->model,
            'messages' => $this->prependSystem($messages, $systemPrompt),
            'max_tokens' => 2048,
            'temperature' => 0.3,
        ];

        try {
            // Retry up to 3 times with 2s backoff on 429 (rate limit) or 5xx errors
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ])
                ->timeout(60)
                ->retry(3, 2000, function ($exception, $request) {
                    if (isset($exception->response)) {
                        $status = $exception->response->status();

                        return $status === 429 || $status >= 500;
                    }

                    return true;
                })
                ->post('https://openrouter.ai/api/v1/chat/completions', $payload);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            Log::warning('LlmService OpenRouter failed', [
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 300),
            ]);
        } catch (ConnectionException $e) {
            Log::error('LlmService OpenRouter error', ['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            // Captura excepciones inesperadas: modelo inexistente (404),
            // API key revocada (401), rate limit con retries agotados, o
            // RequestException que Http::retry() lanza cuando agota
            // intentos. Devolvemos null para que el caller (asistente IA,
            // RAG fallback) muestre un mensaje amigable en lugar de
            // tirarle 500 al usuario.
            //
            // El 404 merece un mensaje propio porque es el caso más
            // confuso de diagnosticar: el servicio "funciona", la API key
            // es válida, pero el modelo configurado fue descontinuado por
            // el proveedor. Pasó el 2026-09-16 con llama-3.1-8b:free.
            $status = method_exists($e, 'response') && $e->response
                ? $e->response->status()
                : null;

            if ($status === 404) {
                Log::error('LlmService: el modelo configurado ya no existe en el proveedor', [
                    'model' => $this->model,
                    'provider' => $this->provider,
                    'hint' => 'Los modelos ":free" de OpenRouter se descontinúan sin aviso. '
                        .'Actualizá LLM_MODEL en el .env con un modelo vigente.',
                ]);
            } else {
                Log::error('LlmService OpenRouter unexpected error', [
                    'error' => $e->getMessage(),
                    'class' => $e::class,
                    'status' => $status,
                    'model' => $this->model,
                ]);
            }
        }

        return null;
    }

    /**
     * Direct Anthropic Claude API.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    protected function chatAnthropic(array $messages, ?string $systemPrompt): ?string
    {
        // Filter out system messages — Anthropic uses a separate `system` param
        $filtered = array_values(array_filter($messages, fn ($m) => $m['role'] !== 'system'));

        $payload = [
            'model' => $this->model,
            'max_tokens' => 1024,
            'messages' => $filtered,
        ];

        if (filled($systemPrompt)) {
            $payload['system'] = $systemPrompt;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
                ->timeout(60)
                ->post('https://api.anthropic.com/v1/messages', $payload);

            if ($response->successful()) {
                return $response->json('content.0.text');
            }

            Log::warning('LlmService Anthropic failed', ['status' => $response->status()]);
        } catch (ConnectionException $e) {
            Log::error('LlmService Anthropic error', ['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('LlmService Anthropic unexpected error', [
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
        }

        return null;
    }

    /**
     * Redactar un artículo KB a partir de una descripción en lenguaje
     * natural. Retorna un array con `title` y `body` (Markdown) o null
     * si el LLM falla.
     *
     * @return array{title: string, body: string}|null
     */
    public function draftKbArticle(string $naturalLanguageInput, string $tone = 'formal', ?string $departmentName = null): ?array
    {
        $toneDescription = match ($tone) {
            'amigable' => 'amigable y cercano, usa "tú"',
            'tecnico' => 'técnico y preciso, asumiendo audiencia técnica',
            default => 'profesional y claro, apto para cualquier empleado',
        };

        $departmentLine = $departmentName
            ? "El artículo pertenece al departamento **{$departmentName}**."
            : '';

        $systemPrompt = <<<PROMPT
Eres un redactor técnico de la Base de Conocimiento interna de Confipetrol.

Recibirás una descripción en lenguaje natural de un agente de soporte.
Debes convertirla en un artículo KB bien estructurado en Markdown.

{$departmentLine}

TONO: {$toneDescription}

REGLAS DE SALIDA (IMPORTANTE):
Responde EXCLUSIVAMENTE con un objeto JSON válido con esta estructura exacta:

{
  "title": "Título corto y descriptivo (máx 80 chars, optimizado para búsqueda)",
  "body": "Contenido en Markdown con la estructura indicada abajo"
}

NO uses bloques de código alrededor del JSON. NO agregues texto antes o después.

ESTRUCTURA DEL BODY (Markdown):
1. Párrafo inicial de 1-2 líneas que resume el problema o el objetivo.
2. Sección ## Síntomas o ## Cuándo aplica (si es un problema).
3. Sección ## Pasos a seguir (si es una guía) con **lista numerada**.
4. Sección ## Si el problema persiste (si es diagnóstico) con qué hacer si no funciona.
5. Sección ## Requisitos / Contactos / Notas adicionales (cuando aplique).

CONVENCIONES:
- Pon en **negrita** nombres de apps, botones, rutas, emails, teléfonos.
- Usa listas numeradas para secuencias; viñetas para listas no ordenadas.
- Usa `código inline` para paths, comandos, hostnames.
- Líneas en blanco entre secciones para que respire el texto.
- NO uses tablas ni HTML crudo, solo Markdown.
- NO inventes datos específicos (emails, teléfonos, extensiones, URLs internas).
  Si el agente no los proveyó, usa placeholders como [correo@confipetrol.com] o
  indica "consulta con tu supervisor".
- Idioma: español neutro.

EJEMPLO DE BODY BIEN FORMATEADO:
Para recibir correos corporativos en tu teléfono necesitas Microsoft Outlook.

## Requisitos
- Correo @confipetrol.com activo
- Dispositivo iOS o Android reciente
- Contraseña de Windows

## Pasos a seguir (iOS)
1. Descarga **Microsoft Outlook** desde la App Store.
2. Abre la app y toca **"Agregar cuenta"**.
3. Ingresa tu correo `tu_usuario@confipetrol.com`.
4. Ingresa tu contraseña de Windows.
5. Acepta los permisos de MDM.

## Si el problema persiste
Crea un ticket en la categoría **TI - Correo y Teams** indicando modelo del
teléfono y sistema operativo.
PROMPT;

        $response = $this->chat(
            [['role' => 'user', 'content' => $naturalLanguageInput]],
            $systemPrompt,
        );

        if (blank($response)) {
            return null;
        }

        // Limpiar code fences por si el modelo igual los agregó
        $cleaned = trim($response);
        $cleaned = preg_replace('/^```(?:json)?\s*/m', '', $cleaned);
        $cleaned = preg_replace('/\s*```\s*$/m', '', $cleaned);

        $parsed = json_decode($cleaned, true);

        if (! is_array($parsed) || ! isset($parsed['title'], $parsed['body'])) {
            Log::warning('LlmService draftKbArticle: respuesta no parseable', [
                'raw' => mb_substr($response, 0, 500),
            ]);

            return null;
        }

        return [
            'title' => (string) $parsed['title'],
            'body' => (string) $parsed['body'],
        ];
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array<int, array{role: string, content: string}>
     */
    protected function prependSystem(array $messages, ?string $systemPrompt): array
    {
        if (blank($systemPrompt)) {
            return $messages;
        }

        return [['role' => 'system', 'content' => $systemPrompt], ...$messages];
    }
}
