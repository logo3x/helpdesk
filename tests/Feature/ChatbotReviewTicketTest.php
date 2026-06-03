<?php

use App\Filament\Pages\ChatbotMetrics;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\Ticket;
use App\Models\User;

it('crea un ticket interno cuando se marca una respuesta IA como incorrecta', function () {
    $supervisor = User::factory()->create(['department_id' => Department::create(['name' => 'TI', 'slug' => 'ti'])->id]);
    $this->actingAs($supervisor);

    $session = ChatSession::create(['user_id' => $supervisor->id]);
    ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => '¿Cómo reseteo mi VPN?']);
    $assistantMsg = ChatMessage::create([
        'chat_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Reinicia tu PC.',
        'helpful' => false,
        'feedback_at' => now(),
    ]);

    (new ChatbotMetrics)->createReviewTicket($assistantMsg->id);

    $ticket = Ticket::query()->where('subject', 'like', '%Revisar respuesta IA%')->first();

    expect($ticket)->not->toBeNull()
        ->and($ticket->description)->toContain('¿Cómo reseteo mi VPN?')
        ->and($ticket->description)->toContain('Reinicia tu PC.')
        ->and($ticket->requester_id)->toBe($supervisor->id);
});

it('ignora mensajes que no están marcados como incorrectos', function () {
    $supervisor = User::factory()->create(['department_id' => Department::create(['name' => 'TI', 'slug' => 'ti'])->id]);
    $this->actingAs($supervisor);

    $session = ChatSession::create(['user_id' => $supervisor->id]);
    $assistantMsg = ChatMessage::create([
        'chat_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Una respuesta',
        'helpful' => true,
    ]);

    (new ChatbotMetrics)->createReviewTicket($assistantMsg->id);

    expect(Ticket::query()->where('subject', 'like', '%Revisar respuesta IA%')->count())->toBe(0);
});

it('incluye el link al KB consultado cuando hay kb_article_id', function () {
    $supervisor = User::factory()->create(['department_id' => Department::create(['name' => 'TI', 'slug' => 'ti'])->id]);
    $this->actingAs($supervisor);

    $article = KbArticle::factory()->create(['title' => 'Manual VPN']);
    $session = ChatSession::create(['user_id' => $supervisor->id]);
    ChatMessage::create(['chat_session_id' => $session->id, 'role' => 'user', 'content' => 'VPN?']);
    $assistantMsg = ChatMessage::create([
        'chat_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Respuesta',
        'helpful' => false,
        'kb_article_id' => $article->id,
    ]);

    (new ChatbotMetrics)->createReviewTicket($assistantMsg->id);

    $ticket = Ticket::query()->where('subject', 'like', '%Revisar respuesta IA%')->first();

    expect($ticket->description)->toContain('Manual VPN')
        ->and($ticket->description)->toContain("/admin/kb-articles/{$article->id}/edit");
});
