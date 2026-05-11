<?php

namespace App\Filament\Pages;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\KbArticle;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Métricas del chatbot vs base de conocimiento.
 *
 * Responde tres preguntas operativas:
 *  1. ¿Está sirviendo el asistente? — ratio 👍/👎 sobre mensajes con feedback.
 *  2. ¿De dónde salen las respuestas? — distribución por source_kind
 *     (kb_high, kb_medium, llm, flow, fallback, system).
 *  3. ¿Qué artículos están funcionando vs cuáles fallan? — top KB con
 *     más votos negativos (para que editores los reescriban).
 *  4. ¿Qué consultas terminan en fallback? — gaps de KB sin cubrir.
 *
 * Solo super_admin/admin. La página es de solo lectura.
 */
class ChatbotMetrics extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $navigationLabel = 'Métricas del chatbot';

    protected static ?string $title = 'Métricas del chatbot — IA vs KB';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = 51;

    protected string $view = 'filament.pages.chatbot-metrics';

    public string $window = '30';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function getViewData(): array
    {
        $since = now()->subDays((int) $this->window);

        return [
            'window' => (int) $this->window,
            'summary' => $this->summary($since),
            'sourceBreakdown' => $this->sourceBreakdown($since),
            'topUnhelpful' => $this->topUnhelpful($since),
            'fallbackQuestions' => $this->fallbackQuestions($since),
            'recentNegatives' => $this->recentNegatives($since),
        ];
    }

    /**
     * Conteos generales del periodo.
     *
     * @return array{
     *     sessions: int,
     *     assistant_messages: int,
     *     rated: int,
     *     helpful: int,
     *     not_helpful: int,
     *     csat_pct: ?float,
     *     auto_resolution_pct: ?float
     * }
     */
    protected function summary(CarbonInterface $since): array
    {
        $sessions = ChatSession::query()->where('created_at', '>=', $since)->count();
        $assistant = $this->assistantQuery($since)->count();
        $rated = $this->assistantQuery($since)->whereNotNull('helpful')->count();
        $helpful = $this->assistantQuery($since)->where('helpful', true)->count();
        $notHelpful = $this->assistantQuery($since)->where('helpful', false)->count();

        // Tasa de auto-resolución: sesiones que NO terminaron escalando.
        $escalated = ChatSession::query()
            ->where('created_at', '>=', $since)
            ->whereNotNull('escalated_ticket_id')
            ->count();
        $autoResolution = $sessions > 0
            ? round((($sessions - $escalated) / $sessions) * 100, 1)
            : null;

        return [
            'sessions' => $sessions,
            'assistant_messages' => $assistant,
            'rated' => $rated,
            'helpful' => $helpful,
            'not_helpful' => $notHelpful,
            'csat_pct' => $rated > 0 ? round(($helpful / $rated) * 100, 1) : null,
            'auto_resolution_pct' => $autoResolution,
        ];
    }

    /**
     * Distribución de respuestas por origen (kb_high, llm, flow, etc).
     *
     * @return array<int, array{source_kind: ?string, total: int, helpful: int, not_helpful: int}>
     */
    protected function sourceBreakdown(CarbonInterface $since): array
    {
        return $this->assistantQuery($since)
            ->select('source_kind', DB::raw('COUNT(*) as total'))
            ->selectRaw('SUM(CASE WHEN helpful = 1 THEN 1 ELSE 0 END) as helpful')
            ->selectRaw('SUM(CASE WHEN helpful = 0 THEN 1 ELSE 0 END) as not_helpful')
            ->groupBy('source_kind')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'source_kind' => $row->source_kind,
                'total' => (int) $row->total,
                'helpful' => (int) $row->helpful,
                'not_helpful' => (int) $row->not_helpful,
            ])
            ->all();
    }

    /**
     * Artículos KB con más votos negativos en el periodo, junto con su
     * ratio para que los editores prioricen qué reescribir primero.
     *
     * @return array<int, array{article_id: int, title: ?string, total: int, helpful: int, not_helpful: int, csat: ?float}>
     */
    protected function topUnhelpful(CarbonInterface $since): array
    {
        return $this->assistantQuery($since)
            ->whereNotNull('kb_article_id')
            ->whereNotNull('helpful')
            ->select('kb_article_id', DB::raw('COUNT(*) as total'))
            ->selectRaw('SUM(CASE WHEN helpful = 1 THEN 1 ELSE 0 END) as helpful')
            ->selectRaw('SUM(CASE WHEN helpful = 0 THEN 1 ELSE 0 END) as not_helpful')
            ->groupBy('kb_article_id')
            ->orderByDesc('not_helpful')
            ->limit(10)
            ->get()
            ->map(function ($row) {
                $article = KbArticle::query()->find($row->kb_article_id, ['id', 'title']);
                $total = (int) $row->total;
                $helpful = (int) $row->helpful;

                return [
                    'article_id' => (int) $row->kb_article_id,
                    'title' => $article?->title,
                    'total' => $total,
                    'helpful' => $helpful,
                    'not_helpful' => (int) $row->not_helpful,
                    'csat' => $total > 0 ? round(($helpful / $total) * 100, 1) : null,
                ];
            })
            ->all();
    }

    /**
     * Preguntas del usuario que recibieron una respuesta `fallback` o
     * sin match KB — son gaps que la KB debería cubrir.
     *
     * @return array<int, array{question: string, count: int}>
     */
    protected function fallbackQuestions(CarbonInterface $since): array
    {
        // Tomamos los mensajes 'user' cuyo mensaje siguiente del assistant
        // fue un fallback. Truncamos la pregunta a 80 chars para evitar
        // ruido en el agrupamiento.
        $fallbackAssistantIds = ChatMessage::query()
            ->where('role', 'assistant')
            ->where('source_kind', 'fallback')
            ->where('created_at', '>=', $since)
            ->pluck('id', 'chat_session_id');

        if ($fallbackAssistantIds->isEmpty()) {
            return [];
        }

        // Para cada respuesta fallback, recuperar el último mensaje del
        // usuario en esa sesión anterior a la respuesta.
        $questions = collect();

        foreach ($fallbackAssistantIds as $sessionId => $assistantId) {
            $assistantMsg = ChatMessage::find($assistantId, ['id', 'created_at']);
            if (! $assistantMsg) {
                continue;
            }

            $userQuestion = ChatMessage::query()
                ->where('chat_session_id', $sessionId)
                ->where('role', 'user')
                ->where('created_at', '<=', $assistantMsg->created_at)
                ->orderByDesc('created_at')
                ->value('content');

            if ($userQuestion) {
                $questions->push(mb_strtolower(mb_strimwidth(trim((string) $userQuestion), 0, 80)));
            }
        }

        return $questions
            ->countBy()
            ->sortDesc()
            ->take(10)
            ->map(fn ($count, $question) => ['question' => $question, 'count' => $count])
            ->values()
            ->all();
    }

    /**
     * Últimos comentarios/respuestas negativos para drill-down rápido.
     *
     * @return array<int, array{id: int, content: string, kb_article_id: ?int, kb_title: ?string, created_at: CarbonInterface}>
     */
    protected function recentNegatives(CarbonInterface $since): array
    {
        return $this->assistantQuery($since)
            ->where('helpful', false)
            ->orderByDesc('feedback_at')
            ->limit(10)
            ->get(['id', 'content', 'kb_article_id', 'feedback_at'])
            ->map(function ($row) {
                $article = $row->kb_article_id
                    ? KbArticle::query()->find($row->kb_article_id, ['id', 'title'])
                    : null;

                return [
                    'id' => $row->id,
                    'content' => mb_strimwidth((string) $row->content, 0, 240, '…'),
                    'kb_article_id' => $row->kb_article_id,
                    'kb_title' => $article?->title,
                    'created_at' => $row->feedback_at ?? $row->created_at,
                ];
            })
            ->all();
    }

    /**
     * @return Builder<ChatMessage>
     */
    protected function assistantQuery(CarbonInterface $since): Builder
    {
        return ChatMessage::query()
            ->where('role', 'assistant')
            ->where('created_at', '>=', $since);
    }
}
