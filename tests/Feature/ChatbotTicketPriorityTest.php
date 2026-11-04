<?php

use App\Enums\TicketImpact;
use App\Enums\TicketPriority;
use App\Enums\TicketUrgency;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\ChatbotService;
use App\Services\LlmService;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
    config(['services.llm.ai_ticket_priority' => true]);

    $this->user = User::factory()->create();
    $this->session = ChatSession::create(['user_id' => $this->user->id, 'status' => 'active', 'channel' => 'web']);
    ChatMessage::create(['chat_session_id' => $this->session->id, 'role' => 'user', 'content' => 'toda el área de mantenimiento se quedó sin internet']);
});

function fakePriority(array $answer): void
{
    test()->mock(LlmService::class, fn ($mock) => $mock->shouldReceive('chat')->andReturn(json_encode($answer)));
}

it('el ticket creado desde el asistente toma la prioridad que propone la IA', function () {
    fakePriority(['impact' => 'alto', 'urgency' => 'media', 'reason' => 'Un área completa sin internet.']);

    $ticket = app(ChatbotService::class)->escalateToTicket($this->session, $this->user, 'Sin internet en mantenimiento');

    expect($ticket->impact)->toBe(TicketImpact::Alto)
        ->and($ticket->urgency)->toBe(TicketUrgency::Media)
        ->and($ticket->priority)->toBe(TicketPriority::Alta)
        ->and($ticket->description)->toContain('Prioridad sugerida por la IA')
        ->and($ticket->description)->toContain('Un área completa sin internet.');
});

it('la IA nunca asigna prioridad Crítica: eso lo decide un agente', function () {
    fakePriority(['impact' => 'alto', 'urgency' => 'alta', 'reason' => 'urgentísimo']);

    $ticket = app(ChatbotService::class)->escalateToTicket($this->session, $this->user, 'URGENTE');

    expect($ticket->priority)->toBe(TicketPriority::Alta);
});

it('si la IA falla o responde algo inválido queda en Media', function (mixed $answer) {
    $this->mock(LlmService::class, fn ($mock) => $mock->shouldReceive('chat')->andReturn($answer));

    $ticket = app(ChatbotService::class)->escalateToTicket($this->session, $this->user, 'Algo');

    expect($ticket->priority)->toBe(TicketPriority::Media)
        ->and($ticket->description)->not->toContain('Prioridad sugerida por la IA');
})->with([
    'sin respuesta' => [null],
    'texto suelto' => ['no sé'],
    'valores inventados' => ['{"impact": "gigante", "urgency": "ya"}'],
]);

it('con la función apagada no consulta a la IA', function () {
    config(['services.llm.ai_ticket_priority' => false]);
    $this->mock(LlmService::class, fn ($mock) => $mock->shouldNotReceive('chat'));

    $ticket = app(ChatbotService::class)->escalateToTicket($this->session, $this->user, 'Algo');

    expect($ticket->priority)->toBe(TicketPriority::Media);
});
