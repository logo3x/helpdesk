<?php

use App\Models\ChatSession;
use App\Models\KbArticle;
use App\Models\User;
use App\Services\ChatbotService;
use App\Services\LlmService;

beforeEach(function () {
    config(['services.llm.personalize_kb_answers' => true]);

    $this->session = ChatSession::create([
        'user_id' => User::factory()->create()->id,
        'status' => 'active',
        'channel' => 'web',
    ]);

    $this->article = KbArticle::factory()->create([
        'title' => 'Cómo instalar Microsoft Teams',
        'slug' => 'como-instalar-microsoft-teams',
        'body' => "## Paso 1\nAbre **Microsoft Store** y busca Teams.\n\n## Paso 2\nHaz clic en **Instalar**.",
        'status' => 'published',
        'published_at' => now(),
    ]);
});

it('responde con IA basada en el artículo KB y enlaza la guía completa', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('embed')->andReturn(null);
        $mock->shouldReceive('chat')
            ->once()
            ->withArgs(function (array $history, string $systemPrompt): bool {
                return str_contains($systemPrompt, 'Abre **Microsoft Store** y busca Teams')
                    && str_contains($systemPrompt, 'NO sabe de tecnología')
                    && collect($history)->contains(fn ($m) => $m['role'] === 'user' && str_contains($m['content'], 'teams'));
            })
            ->andReturn("Entiendo, quieres tener Teams en tu equipo.\n\n1. Abre **Microsoft Store**.\n\n¿Te funcionó?");
    });

    [$response, $meta] = app(ChatbotService::class)
        ->handleMessageWithMeta($this->session, 'como instalo teams en mi pc');

    expect($meta['source_kind'])->toBeIn(['kb_high', 'kb_medium'])
        ->and($meta['kb_article_id'])->toBe($this->article->id)
        ->and($response)->toStartWith('Entiendo, quieres tener Teams')
        ->and($response)->toContain('/portal/kb/como-instalar-microsoft-teams');
});

it('si la IA no responde envía el artículo tal cual', function () {
    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('embed')->andReturn(null);
        $mock->shouldReceive('chat')->andReturn(null);
    });

    [$response, $meta] = app(ChatbotService::class)
        ->handleMessageWithMeta($this->session, 'como instalo teams en mi pc');

    expect($meta['source_kind'])->toBeIn(['kb_high', 'kb_medium'])
        ->and($response)->toContain('## Cómo instalar Microsoft Teams')
        ->and($response)->toContain('busca Teams');
});

it('con la personalización apagada no llama a la IA', function () {
    config(['services.llm.personalize_kb_answers' => false]);

    $this->mock(LlmService::class, function ($mock) {
        $mock->shouldReceive('embed')->andReturn(null);
        $mock->shouldNotReceive('chat');
    });

    [$response] = app(ChatbotService::class)
        ->handleMessageWithMeta($this->session, 'como instalo teams en mi pc');

    expect($response)->toContain('## Cómo instalar Microsoft Teams');
});
