<?php

namespace App\Services;

use App\Models\ChatFlowStat;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Department;
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
     *
     * Builds a human-readable description from the chat history so a
     * support agent can read the conversation as a formatted transcript
     * instead of raw "[role] content" dumps.
     */
    public function escalateToTicket(ChatSession $session, User $requester, string $subject, ?int $departmentId = null): Ticket
    {
        $messages = $session->messages()
            ->with('session.user:id,name')
            ->orderBy('created_at')
            ->get(['id', 'chat_session_id', 'role', 'content', 'created_at']);

        $transcript = $messages->map(function ($m) use ($requester) {
            $who = match ($m->role) {
                'user' => "👤 **{$requester->name}**",
                'assistant' => '🤖 **Asistente virtual**',
                default => ucfirst($m->role),
            };

            $time = $m->created_at->format('H:i');
            $body = trim($m->content);

            return "**{$who}** · _{$time}_\n\n{$body}";
        })->implode("\n\n---\n\n");

        $departmentName = $departmentId
            ? Department::find($departmentId)?->name
            : null;

        $header = "# Ticket escalado desde el asistente virtual\n\n"
            ."**Solicitante:** {$requester->name} ({$requester->email})\n"
            ."**Sesión de chat:** #{$session->id}\n"
            ."**Resumen del usuario:** {$subject}\n"
            .($departmentName ? "**Departamento destino:** {$departmentName}\n" : '')
            .'**Duración:** desde '.$session->created_at->format('d/m/Y H:i')
            .' hasta '.now()->format('d/m/Y H:i')."\n"
            .'**Total mensajes:** '.$messages->count()."\n\n"
            ."---\n\n## Transcripción de la conversación\n\n";

        $payload = [
            'subject' => $subject ?: 'Escalación desde chatbot',
            'description' => $header.$transcript,
        ];

        if ($departmentId) {
            $payload['department_id'] = $departmentId;
        }

        $ticket = app(TicketService::class)->create($requester, $payload);

        $session->update([
            'status' => 'escalated',
            'escalated_ticket_id' => $ticket->id,
        ]);

        return $ticket;
    }

    protected function generateResponse(ChatSession $session, string $userMessage): string
    {
        // 1. Check if user wants to escalate.
        //    El estado de escalación (awaiting_subject / awaiting_department)
        //    se maneja en el componente Livewire (Portal\Chatbot). Aquí solo
        //    detectamos el disparador inicial.
        if ($this->wantsEscalation($userMessage)) {
            $this->abandonActiveFlow($session);

            return "Claro, te ayudo a crear un ticket. 🎫\n\n"
                .'Cuéntame **brevemente** de qué se trata tu problema o solicitud '
                .'(ej: "Mi impresora no imprime" o "Necesito resetear mi correo").';
        }

        // 2. Comando explícito para cancelar el flow en curso.
        if ($this->wantsCancel($userMessage)) {
            $abandoned = $this->abandonActiveFlow($session);

            return $abandoned
                ? 'Listo, cancelé el flujo en curso. ¿En qué más puedo ayudarte?'
                : 'No hay ningún flujo activo para cancelar. ¿En qué puedo ayudarte?';
        }

        // 3. Búsqueda KB de alta confianza — si la nueva pregunta tiene match
        //    fuerte con un artículo (>= 0.70), se interpreta como cambio de
        //    tema y se abandona cualquier flujo en curso para servir la KB.
        $ragResults = $this->rag->search($userMessage, topN: 1, threshold: 0.5);
        $topKb = $ragResults->first();
        $topSimilarity = $topKb['similarity'] ?? 0;

        if ($topKb !== null && $topSimilarity >= 0.70) {
            $this->abandonActiveFlow($session);

            return $this->formatKbResponse($topKb);
        }

        // 4. Check if there's an active flow in progress — continuar el
        //    flujo solo si la nueva entrada no fue reconocida como una
        //    nueva consulta (paso 3 ya habría salido).
        $activeFlow = $this->flowEngine->getActiveFlow($session);

        if ($activeFlow !== null) {
            $next = $this->flowEngine->advance($session, $activeFlow, $userMessage);

            if ($next === null) {
                return '¡Listo! El flujo se completó. ¿Hay algo más en lo que pueda ayudarte?';
            }

            return $next;
        }

        // 5. KB con confianza media (0.55-0.70) como respuesta principal
        //    cuando no hay flujo activo.
        if ($topKb !== null && $topSimilarity >= 0.55) {
            return $this->formatKbResponse($topKb);
        }

        // 6. Si la KB no tiene match fuerte, clasificar intent y lanzar flujo.
        $intent = $this->classifier->classify($userMessage);

        if ($intent['flow'] !== null) {
            $firstStep = $this->flowEngine->start($session, $intent['flow']);

            return $firstStep;
        }

        // 5. LLM con contexto KB (si hay API key). Se reutiliza el top-match
        //    aunque no haya llegado al umbral de respuesta directa.
        $context = $topKb !== null
            ? "[Artículo: {$topKb['article_title']}]\n{$topKb['content']}"
            : '';
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

        // 6. Fallback final
        return 'No encontré información específica para tu consulta. '
            .'Puedo ayudarte a crear un ticket de soporte — solo escribe **"crear ticket"** '
            .'o **"hablar con un agente"** y te conecto con alguien del equipo.';
    }

    /**
     * Formatea un artículo KB como respuesta del asistente.
     *
     * @param  array{content: string, similarity: float, article_id: int, article_title: string|null}  $kb
     */
    protected function formatKbResponse(array $kb): string
    {
        $title = $kb['article_title'] ?? 'Artículo de la base de conocimiento';

        return "## {$title}\n\n{$kb['content']}\n\n---\n\n¿Te sirvió esta información? Si necesitas más ayuda, escribe **\"crear ticket\"** y te conecto con un agente.";
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

    /**
     * El usuario quiere salir del flujo en curso.
     */
    protected function wantsCancel(string $message): bool
    {
        $message = mb_strtolower(trim($message));
        $triggers = ['cancelar', 'reiniciar', 'salir del flujo', 'otro tema', 'reset chat'];

        foreach ($triggers as $trigger) {
            if (str_contains($message, $trigger)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Marca el flujo activo (si lo hay) como completado. Devuelve true si
     * había un flujo que se canceló.
     */
    protected function abandonActiveFlow(ChatSession $session): bool
    {
        $affected = ChatFlowStat::where('chat_session_id', $session->id)
            ->where('completed', false)
            ->update(['completed' => true]);

        return $affected > 0;
    }

    protected function buildSystemPrompt(string $kbContext): string
    {
        $base = <<<'PROMPT'
Eres el asistente virtual de soporte de Confipetrol. Respondes en español.

TU ROL
Ayudas a empleados con consultas de TI, RRHH, seguridad y procesos internos.

FORMATO DE RESPUESTA (IMPORTANTE)
Usa Markdown para hacer las respuestas fáciles de leer:

- Empieza con una **frase introductoria** de 1 línea que responda directamente.
- Usa **encabezados** con `##` para separar secciones cuando haya varios pasos o temas.
- Usa **listas numeradas** (`1.`, `2.`) para pasos secuenciales.
- Usa **viñetas** (`-`) para opciones alternativas o información suelta.
- Pon en **negritas** los elementos importantes: nombres de apps, botones, rutas, emails, teléfonos.
- Deja **líneas en blanco entre secciones** para que respire el texto.
- NO uses tablas ni HTML crudo, solo Markdown básico.

EJEMPLO DE FORMATO
```
Para reportar un correo sospechoso tienes **dos opciones**.

## Opción 1: botón Reportar phishing

1. Abre el mensaje en **Outlook**.
2. Haz clic en **Reportar → Phishing**.
3. El correo se envía automáticamente a **seguridad@confipetrol.com**.

## Qué NO hacer

- No hagas clic en enlaces.
- No descargues adjuntos.
- No respondas al remitente.

¿Necesitas ayuda con algo más?
```

REGLAS
- Responde con información de la base de conocimiento cuando esté disponible.
- Si no sabes algo, dilo honestamente y sugiere crear un ticket.
- No inventes datos sobre Confipetrol, políticas o procedimientos.
- Sé conciso: máximo 250 palabras salvo que el tema requiera más detalle.
- Cierra con una pregunta breve invitando a continuar la conversación.
PROMPT;

        if (filled($kbContext)) {
            $base .= "\n\nINFORMACIÓN DE LA BASE DE CONOCIMIENTO:\n---\n{$kbContext}\n---\n\nUsa esta información como fuente principal para tu respuesta.";
        }

        return $base;
    }
}
