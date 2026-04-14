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
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ])
                ->timeout(60)
                ->post('https://openrouter.ai/api/v1/chat/completions', $payload);

            if ($response->successful()) {
                return $response->json('choices.0.message.content');
            }

            Log::warning('LlmService OpenRouter failed', ['status' => $response->status()]);
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
