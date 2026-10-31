<?php

namespace App\Services;

use App\Models\ChatFlowStat;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Log;

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
     *
     * Devuelve solo el string para retrocompatibilidad con el componente
     * Livewire; el ChatMessage del asistente queda persistido con
     * `source_kind`, `kb_article_id` y `similarity` para análisis posterior.
     */
    public function handleMessage(ChatSession $session, string $userMessage): string
    {
        // Store user message
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        [$response, $meta] = $this->generateResponseWithMeta($session, $userMessage);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $response,
            'source_kind' => $meta['source_kind'] ?? null,
            'kb_article_id' => $meta['kb_article_id'] ?? null,
            'similarity' => $meta['similarity'] ?? null,
            'metadata' => filled($meta['options'] ?? null) ? ['options' => $meta['options'], 'guided' => true] : (($meta['guided'] ?? false) ? ['guided' => true] : null),
        ]);

        return $response;
    }

    /**
     * Variante para tests / debugging que expone el metadata completo.
     *
     * @return array{0: string, 1: array{source_kind: string, kb_article_id: ?int, similarity: ?float}}
     */
    public function handleMessageWithMeta(ChatSession $session, string $userMessage): array
    {
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $userMessage,
        ]);

        [$response, $meta] = $this->generateResponseWithMeta($session, $userMessage);

        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $response,
            'source_kind' => $meta['source_kind'],
            'kb_article_id' => $meta['kb_article_id'],
            'similarity' => $meta['similarity'],
            'metadata' => filled($meta['options'] ?? null) ? ['options' => $meta['options'], 'guided' => true] : (($meta['guided'] ?? false) ? ['guided' => true] : null),
        ]);

        return [$response, $meta];
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
        return $this->generateResponseWithMeta($session, $userMessage)[0];
    }

    /**
     * Variante que también devuelve metadata para tracking (source_kind,
     * artículo KB usado, score de similitud). El método "string" original
     * delega aquí para no duplicar la cadena de decisión.
     *
     * @return array{0: string, 1: array{source_kind: string, kb_article_id: ?int, similarity: ?float}}
     */
    protected function generateResponseWithMeta(ChatSession $session, string $userMessage): array
    {
        // 1. Check if user wants to escalate.
        if ($this->wantsEscalation($userMessage)) {
            $this->abandonActiveFlow($session);

            return [
                "Claro, te ayudo a crear un ticket. 🎫\n\n"
                .'Cuéntame **brevemente** de qué se trata tu problema o solicitud '
                .'(ej: "Mi impresora no imprime" o "Necesito resetear mi correo").',
                $this->meta('system'),
            ];
        }

        // 2. Comando explícito para cancelar el flow en curso.
        if ($this->wantsCancel($userMessage)) {
            $abandoned = $this->abandonActiveFlow($session);

            return [
                $abandoned
                    ? 'Listo, cancelé el flujo en curso. ¿En qué más puedo ayudarte?'
                    : 'No hay ningún flujo activo para cancelar. ¿En qué puedo ayudarte?',
                $this->meta('system'),
            ];
        }

        // 3. KB de alta confianza.
        //    Umbrales calibrados para la búsqueda con stemming en español:
        //      - >= 0.55 (alta) → responde directo con el artículo, abandona flujo.
        //      - >= 0.30 (media) → responde con el artículo si no hay flujo activo.
        //    Con stemming, un query como "instalo teams en mi pc" devuelve
        //    similarity ~0.66 contra "Cómo instalar Microsoft Teams" — antes
        //    daba 0.50 (sin stem) y caía bajo el threshold viejo de 0.55.
        $ragResults = $this->rag->search($userMessage, topN: 1, threshold: 0.5);
        $topKb = $ragResults->first();
        $topSimilarity = $topKb['similarity'] ?? 0;

        // Conversación guiada en curso: si el último mensaje del asistente
        // salió de un artículo KB y el usuario está respondiendo (o tocó
        // una opción), seguimos con ese mismo artículo salvo que la nueva
        // consulta apunte con mucha confianza a OTRO artículo.
        $guidedArticle = $this->guidedConversationArticle($session);

        if ($topKb !== null && $topSimilarity >= 0.55
            && ($guidedArticle === null
                || $guidedArticle->id === ($topKb['article_id'] ?? null)
                || ! $this->isSuggestedOption($session, $userMessage))) {
            $this->abandonActiveFlow($session);

            return $this->kbAnswer($session, $topKb, 'kb_high', $topSimilarity);
        }

        if ($guidedArticle !== null) {
            return $this->kbAnswer($session, [
                'content' => (string) $guidedArticle->body,
                'similarity' => 0.0,
                'article_id' => $guidedArticle->id,
                'article_title' => $guidedArticle->title,
            ], 'kb_medium', null);
        }

        // 4. Continuar flujo activo si lo hay.
        $activeFlow = $this->flowEngine->getActiveFlow($session);

        if ($activeFlow !== null) {
            $next = $this->flowEngine->advance($session, $activeFlow, $userMessage);

            return [
                $next ?? '¡Listo! El flujo se completó. ¿Hay algo más en lo que pueda ayudarte?',
                $this->meta('flow'),
            ];
        }

        // 5. KB con confianza media.
        if ($topKb !== null && $topSimilarity >= 0.42) {
            return $this->kbAnswer($session, $topKb, 'kb_medium', $topSimilarity);
        }

        // 6. Clasificar intent y lanzar flujo si aplica.
        $intent = $this->classifier->classify($userMessage);

        if ($intent['flow'] !== null) {
            $firstStep = $this->flowEngine->start($session, $intent['flow']);

            return [$firstStep, $this->meta('flow')];
        }

        // 7. LLM con contexto KB (solo si hay artículo con algo de relevancia).
        //    Si no hay ningún resultado KB, NO llamamos al LLM para evitar
        //    que el modelo alucine información de Confipetrol con datos
        //    genéricos de su entrenamiento.
        if ($topKb === null) {
            return [
                "No encontré información sobre ese tema en nuestra base de conocimiento. 🔍\n\nSi necesitas ayuda, puedes:\n- Escribir **\"crear ticket\"** para que un agente te atienda\n- Consultar el [Centro de ayuda](/portal/kb) con los artículos publicados",
                $this->meta('fallback'),
            ];
        }

        $context = "[Artículo: {$topKb['article_title']}]\n{$topKb['content']}";
        $systemPrompt = $this->buildSystemPrompt($context);

        $llmResponse = $this->llm->chat($this->recentHistory($session), $systemPrompt);

        if (filled($llmResponse)) {
            return [
                $llmResponse,
                $this->meta(
                    'llm',
                    $topKb['article_id'] ?? null,
                    $topSimilarity,
                ),
            ];
        }

        // 8. Fallback final — llegamos aquí SOLO si el LLM se llamó y
        //    devolvió vacío (rate limit, API key inválida, timeout...).
        //
        //    Se marca con source_kind = 'llm_failed' (distinto de
        //    'fallback') para poder separar en métricas dos situaciones
        //    que se ven iguales para el usuario pero requieren acciones
        //    muy distintas:
        //      - fallback   → la KB no tenía nada. Normal, hay que
        //                     escribir el artículo que falta.
        //      - llm_failed → el servicio de IA está caído o sin cuota.
        //                     Es una alerta operativa, no un hueco de KB.
        Log::warning('[CHATBOT] El LLM no devolvió respuesta', [
            'session_id' => $session->id,
            'query' => mb_substr($userMessage, 0, 200),
            'kb_article_id' => $topKb['article_id'] ?? null,
            'kb_similarity' => $topSimilarity,
        ]);

        return [
            'No encontré información específica para tu consulta. '
            .'Puedo ayudarte a crear un ticket de soporte — solo escribe **"crear ticket"** '
            .'o **"hablar con un agente"** y te conecto con alguien del equipo.',
            $this->meta(
                'llm_failed',
                $topKb['article_id'] ?? null,
                $topSimilarity,
            ),
        ];
    }

    /**
     * @param  array<int, string>  $options
     * @return array{source_kind: string, kb_article_id: ?int, similarity: ?float, options: array<int, string>, guided: bool}
     */
    protected function meta(string $sourceKind, ?int $kbArticleId = null, ?float $similarity = null, array $options = [], bool $guided = false): array
    {
        return [
            'source_kind' => $sourceKind,
            'kb_article_id' => $kbArticleId,
            'similarity' => $similarity,
            'options' => $options,
            'guided' => $guided,
        ];
    }

    /**
     * @param  array{content: string, similarity: float, article_id: int, article_title: string|null}  $kb
     * @return array{0: string, 1: array{source_kind: string, kb_article_id: ?int, similarity: ?float, options: array<int, string>, guided: bool}}
     */
    protected function kbAnswer(ChatSession $session, array $kb, string $sourceKind, ?float $similarity): array
    {
        [$text, $options, $guided] = $this->answerFromKb($session, $kb);

        return [$text, $this->meta($sourceKind, $kb['article_id'] ?? null, $similarity, $options, $guided)];
    }

    /**
     * Artículo de la conversación guiada en curso: el del último mensaje
     * del asistente, si lo redactó la IA desde la KB hace menos de 30 min.
     */
    protected function guidedConversationArticle(ChatSession $session): ?KbArticle
    {
        if (! config('services.llm.personalize_kb_answers')) {
            return null;
        }

        $last = $session->messages()
            ->where('role', 'assistant')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($last === null
            || $last->kb_article_id === null
            || ! ($last->metadata['guided'] ?? false)
            || $last->created_at->lt(now()->subMinutes(30))) {
            return null;
        }

        return KbArticle::find($last->kb_article_id);
    }

    /**
     * El usuario respondió con una de las opciones que le sugerimos en el
     * último mensaje (botón o escrita igual).
     */
    protected function isSuggestedOption(ChatSession $session, string $userMessage): bool
    {
        $options = $session->messages()
            ->where('role', 'assistant')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('metadata')['options'] ?? [];

        $normalized = mb_strtolower(trim($userMessage));

        return collect($options)->contains(fn (string $option): bool => mb_strtolower($option) === $normalized);
    }

    /**
     * Separa la línea "OPCIONES: a | b | c" que agrega la IA y devuelve
     * el texto limpio + la lista de respuestas sugeridas (máx. 4).
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public static function extractOptions(string $text): array
    {
        if (! preg_match('/^[ \t>*_-]*OPCIONES\s*:\s*(.+)$/mi', $text, $match)) {
            return [trim($text), []];
        }

        $options = collect(explode('|', $match[1]))
            ->map(fn (string $option): string => trim(strip_tags($option), " \t*_\"'`[]"))
            ->filter(fn (string $option): bool => $option !== '' && mb_strlen($option) <= 80)
            ->unique()
            ->take(4)
            ->values()
            ->all();

        return [trim(str_replace($match[0], '', $text)), $options];
    }

    /**
     * Responde usando el artículo KB encontrado, pero redactado por la IA
     * para la pregunta concreta del usuario: tono cercano, solo los pasos
     * que necesita y en lenguaje sencillo. El artículo es la única fuente.
     *
     * Si la IA no responde (o está apagada con CHATBOT_PERSONALIZE_KB=false)
     * se devuelve el artículo tal cual, como antes.
     *
     * @param  array{content: string, similarity: float, article_id: int, article_title: string|null}  $kb
     * @return array{0: string, 1: array<int, string>, 2: bool} texto, respuestas sugeridas y si lo redactó la IA
     */
    protected function answerFromKb(ChatSession $session, array $kb): array
    {
        if (! config('services.llm.personalize_kb_answers')) {
            return [$this->formatKbResponse($kb), [], false];
        }

        $article = KbArticle::find($kb['article_id'] ?? null);
        $title = $kb['article_title'] ?? $article?->title ?? 'Artículo de la base de conocimiento';
        $source = mb_substr((string) ($article?->body ?: $kb['content']), 0, 6000);

        $response = $this->llm->chat(
            $this->recentHistory($session),
            $this->buildPersonalizedKbPrompt("[Artículo: {$title}]\n{$source}", $this->recentQuestionsAsked($session)),
        );

        if (! filled($response)) {
            Log::info('[CHATBOT] Sin respuesta de la IA al personalizar KB; se envía el artículo tal cual', [
                'session_id' => $session->id,
                'kb_article_id' => $kb['article_id'] ?? null,
            ]);

            return [$this->formatKbResponse($kb), [], false];
        }

        [$response, $options] = self::extractOptions($response);

        $link = $article?->slug ? route('portal.kb.show', $article->slug, false) : null;

        return [
            $response.($link ? "\n\n📄 Guía completa: [{$title}]({$link})" : ''),
            $options,
            true,
        ];
    }

    /**
     * Cuántos de los últimos mensajes del asistente fueron preguntas con
     * opciones, para que la IA no interrogue de más al usuario.
     */
    protected function recentQuestionsAsked(ChatSession $session): int
    {
        return $session->messages()
            ->where('role', 'assistant')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(4)
            ->get(['metadata'])
            ->takeWhile(fn ($m) => filled($m->metadata['options'] ?? null))
            ->count();
    }

    /**
     * Últimos mensajes de la conversación en formato chat del LLM.
     *
     * @return array<int, array{role: string, content: string}>
     */
    protected function recentHistory(ChatSession $session): array
    {
        return $session->messages()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }

    protected function buildPersonalizedKbPrompt(string $kbContext, int $questionsAsked = 0): string
    {
        $questionRule = $questionsAsked >= 2
            ? 'Ya le hiciste varias preguntas seguidas: NO preguntes más, da ya la solución completa con lo que sabes.'
            : 'Haz como máximo UNA pregunta por mensaje, y solo si de verdad la necesitas.';

        return <<<PROMPT
Eres el asistente de soporte de Confipetrol. Hablas en español neutro, tuteando
(nunca uses voseo), con un tono cálido y cercano, como un compañero de TI paciente.

QUIÉN TE ESCRIBE
Un empleado que NO sabe de tecnología ni de procesos internos. No quiere leer un
manual: quiere resolver su problema rápido. Puede escribir con errores o de forma vaga.

CÓMO RESPONDER
1. Primero muestra que entendiste: una frase corta que conecte con lo que pidió
   (ej: "Entiendo, necesitas entrar a SAP desde tu equipo y no sabes por dónde empezar.").
2. Da la solución adaptada a SU caso. Del artículo usa SOLO lo que necesita para lo que
   preguntó; omite secciones que no aplican. Si pregunta algo puntual, responde eso.
3. Pasos numerados, cortos y en lenguaje sencillo, una acción por paso. Pon en **negritas**
   botones, apps y datos importantes. Explica términos técnicos en pocas palabras.
4. Si falta un dato para ayudarle mejor (ej: qué equipo usa o qué error ve), da los pasos
   generales y pregúntale ese dato al final.
5. Cierra con una pregunta breve ("¿Te funcionó?" / "¿Dónde te quedaste?").

GUÍA PASO A PASO (como un técnico que acompaña)
- Si la consulta es vaga y el artículo tiene caminos distintos (no lo tiene instalado / no
  recuerda su usuario / le sale un error...), NO sueltes todo: pregunta primero cuál es su caso.
- Si el procedimiento es largo, da un bloque de máximo 4 pasos y pregunta cómo le fue
  antes de seguir con el siguiente.
- Si el usuario ya respondió una pregunta tuya, continúa desde ahí sin repetir lo anterior.
- {$questionRule}

RESPUESTAS SUGERIDAS
Cuando termines con una pregunta, agrega como ÚLTIMA línea, exactamente con este formato:
OPCIONES: respuesta 1 | respuesta 2 | respuesta 3
- De 2 a 4 respuestas, de máximo 6 palabras cada una, escritas como las diría el usuario
  ("Ya lo instalé", "Me sale un error", "No sé mi usuario").
- No incluyas "hablar con un agente" ni "crear ticket": el sistema ya muestra ese botón.
- Si no terminas con una pregunta, no agregues la línea OPCIONES.

REGLAS
- La ÚNICA fuente es el artículo de abajo. No inventes pasos, enlaces, correos, teléfonos
  ni políticas que no estén ahí. Si el artículo no cubre lo que pregunta, dilo y sugiere
  crear un ticket.
- No copies el artículo completo ni repitas su título como encabezado.
- Máximo 180 palabras salvo que los pasos realmente lo requieran.
- Markdown simple (listas, negritas, párrafos cortos). Sin tablas ni HTML.

ARTÍCULO DE LA BASE DE CONOCIMIENTO
---
{$kbContext}
---
PROMPT;
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
