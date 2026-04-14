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
        protected RagService $rag,
        protected LlmService $llm,
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

        // 4. RAG search + LLM
        $context = $this->rag->buildContext($userMessage);
        $chatHistory = $session->messages()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        $systemPrompt = $this->buildSystemPrompt($context);

        $llmResponse = $this->llm->chat($chatHistory, $systemPrompt);

        if (filled($llmResponse)) {
            return $llmResponse;
        }

        // 5. Fallback (no LLM key or LLM failed)
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

    protected function buildSystemPrompt(string $kbContext): string
    {
        $base = <<<'PROMPT'
Eres el asistente virtual de soporte de Confipetrol. Responde en español.
Tu rol es ayudar a los usuarios con problemas técnicos, preguntas de TI
y consultas sobre procesos internos.

Reglas:
- Responde de forma concisa y amable.
- Si tienes información de la base de conocimiento, úsala para responder.
- Si no tienes información suficiente, dilo honestamente y sugiere crear un ticket.
- No inventes datos sobre Confipetrol, políticas, o procedimientos.
- Si el usuario quiere hablar con un agente humano, invítalo a escribir "crear ticket".
PROMPT;

        if (filled($kbContext)) {
            $base .= "\n\nINFORMACIÓN DE LA BASE DE CONOCIMIENTO:\n---\n{$kbContext}\n---\n\nUsa esta información para responder si es relevante a la pregunta.";
        }

        return $base;
    }
}
