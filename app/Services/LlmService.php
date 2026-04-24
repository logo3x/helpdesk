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
        $this->model = config('services.llm.model', 'meta-llama/llama-3.1-8b-instruct:free');
        $this->apiKey = config('services.llm.api_key', '');
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
        $payload = [
            'model' => $this->model,
            'messages' => $this->prependSystem($messages, $systemPrompt),
            'max_tokens' => 1024,
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
