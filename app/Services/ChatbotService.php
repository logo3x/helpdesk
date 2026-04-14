<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Ticket;
use App\Models\User;

/**
 * Orchestrates the chatbot pipeline:
 * 1. Check if there's an active flow → continue it via FlowEngine
 * 2. Classify intent → if a flow matches, start it
 * 3. (Fase 5) RAG search → if KB articles found, summarize
 * 4. (Fase 5) LLM fallback → generate response with Claude
 * 5. Fallback → suggest creating a ticket
 */
class ChatbotService
{
    public function __construct(
        protected IntentClassifier $classifier,
        protected FlowEngine $flowEngine,
    ) {}

    /**
     * Process a user message and return the assistant's response.
     */
    public function handleMessage(ChatSession $session, string $userMessage): string
    {
        // Store user message
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        $response = $this->generateResponse($session, $userMessage);

        // Store assistant response
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $response,
        ]);

        return $response;
    }

    /**
     * Create or resume a chat session for a user.
     */
    public function getOrCreateSession(User $user): ChatSession
    {
        // Resume last active session if it exists and is recent (< 30 min)
        $session = ChatSession::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('updated_at', '>=', now()->subMinutes(30))
            ->latest()
            ->first();

        if ($session !== null) {
            return $session;
        }

        return ChatSession::create([
            'user_id' => $user->id,
            'status' => 'active',
            'channel' => 'web',
        ]);
    }

    /**
     * Escalate the current chat session to a ticket.
     */
    public function escalateToTicket(ChatSession $session, User $requester, string $subject): Ticket
    {
        // Collect chat history as ticket description
        $messages = $session->messages()
            ->orderBy('created_at')
            ->get(['role', 'content'])
            ->map(fn ($m) => "[{$m->role}] {$m->content}")
            ->implode("\n\n");

        $ticket = app(TicketService::class)->create($requester, [
            'subject' => $subject ?: 'Escalación desde chatbot',
            'description' => "Conversación de chatbot:\n\n{$messages}",
        ]);

        $session->update([
            'status' => 'escalated',
            'escalated_ticket_id' => $ticket->id,
        ]);

        return $ticket;
    }

    protected function generateResponse(ChatSession $session, string $userMessage): string
    {
        // 1. Check if user wants to escalate
        if ($this->wantsEscalation($userMessage)) {
            return 'Entendido. ¿Podrías darme un breve resumen del problema para crear el ticket? '
                .'Escribe: **escalar: [tu resumen]**';
        }

        // 2. Check if there's an active flow in progress
        $activeFlow = $this->flowEngine->getActiveFlow($session);

        if ($activeFlow !== null) {
            $next = $this->flowEngine->advance($session, $activeFlow, $userMessage);

            if ($next === null) {
                return '¡Listo! El flujo se completó. ¿Hay algo más en lo que pueda ayudarte?';
            }

            return $next;
        }

        // 3. Classify intent and try to match a flow
        $intent = $this->classifier->classify($userMessage);

        if ($intent['flow'] !== null) {
            $firstStep = $this->flowEngine->start($session, $intent['flow']);

            return $firstStep;
        }

        // 4. (Fase 5) RAG search + LLM — placeholder for now

        // 5. Fallback
        return 'No encontré un flujo específico para tu consulta. '
            .'Puedo ayudarte a crear un ticket de soporte — solo escribe **"crear ticket"** '
            .'o **"hablar con un agente"** y te conecto con alguien del equipo.';
    }

    protected function wantsEscalation(string $message): bool
    {
        $message = mb_strtolower(trim($message));
        $triggers = ['crear ticket', 'hablar con un agente', 'agente humano', 'escalar', 'persona real'];

        foreach ($triggers as $trigger) {
            if (str_contains($message, $trigger)) {
                return true;
            }
        }

        return false;
    }
}
