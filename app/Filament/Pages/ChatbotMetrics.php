<?php

namespace App\Filament\Pages;

use App\Exports\ChatbotMetricsExport;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Department;
use App\Models\KbArticle;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Métricas del chatbot vs base de conocimiento.
 *
 * Responde tres preguntas operativas:
 *  1. ¿Está sirviendo el asistente? — ratio 👍/👎 sobre mensajes con feedback,
 *     comparado con el periodo anterior (¿mejorando o empeorando?).
 *  2. ¿De dónde salen las respuestas? — distribución por source_kind
 *     (kb_high, kb_medium, llm, flow, fallback, system) en dona + tabla.
 *  3. ¿Qué artículos están funcionando vs cuáles fallan? — top KB con
 *     más votos negativos. Click en una fila abre los mensajes específicos.
 *  4. ¿Qué consultas terminan en fallback? — gaps de KB. Cada pregunta
 *     tiene botón "Crear artículo KB" que pre-rellena el form.
 *
 * Solo super_admin/admin. La página es de solo lectura salvo por el
 * shortcut a crear KB articles desde un gap.
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

    public ?string $departmentId = null;

    public ?int $drilldownArticleId = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function getViewData(): array
    {
        $days = (int) $this->window;
        $since = now()->subDays($days);
        $previousSince = now()->subDays($days * 2);
        $previousUntil = $since->copy();

        $sourceBreakdown = $this->sourceBreakdown($since);

        return [
            'window' => $days,
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'selectedDepartmentId' => $this->departmentId,
            'summary' => $this->summary($since),
            'summaryPrev' => $this->summary($previousSince, $previousUntil),
            'sourceBreakdown' => $sourceBreakdown,
            'csatTrend' => $this->csatTrendData(),
            'sourceDonutData' => $this->donutDataset($sourceBreakdown),
            'topUnhelpful' => $this->topUnhelpful($since),
            'fallbackQuestions' => $this->fallbackQuestions($since),
            'recentNegatives' => $this->recentNegatives($since),
            'drilldownArticleId' => $this->drilldownArticleId,
            'drilldownArticle' => $this->drilldownArticle(),
            'drilldownMessages' => $this->drilldownMessages($since),
        ];
    }

    /**
     * Dataset listo para Chart.js — convierte el sourceBreakdown a
     * arrays paralelos de labels, valores y colores para el doughnut.
     *
     * @param  array<int, array{source_kind: ?string, total: int, helpful: int, not_helpful: int}>  $rows
     * @return array{labels: array<int, string>, values: array<int, int>, colors: array<int, string>}
     */
    protected function donutDataset(array $rows): array
    {
        $palette = [
            'kb_high' => '#10b981',   // emerald
            'kb_medium' => '#84cc16', // lime
            'flow' => '#0ea5e9',      // sky
            'llm' => '#6366f1',       // indigo
            'fallback' => '#f43f5e',  // rose
            'system' => '#9ca3af',    // gray
        ];

        $labels = [];
        $values = [];
        $colors = [];

        foreach ($rows as $row) {
            $kind = $row['source_kind'] ?? 'unknown';
            $labels[] = match ($kind) {
                'kb_high' => 'KB alta',
                'kb_medium' => 'KB media',
                'flow' => 'Flujo',
                'llm' => 'LLM',
                'fallback' => 'Fallback',
                'system' => 'Sistema',
                default => 'Sin clasificar',
            };
            $values[] = $row['total'];
            $colors[] = $palette[$kind] ?? '#d1d5db';
        }

        return ['labels' => $labels, 'values' => $values, 'colors' => $colors];
    }

    /**
     * Resetea el drill-down activo cuando cambia el filtro o la ventana.
     */
    public function updatedWindow(): void
    {
        $this->drilldownArticleId = null;
    }

    public function updatedDepartmentId(): void
    {
        $this->drilldownArticleId = null;
    }

    /**
     * Conteos generales del periodo. Acepta `until` para soportar
     * comparativa con el periodo anterior (se reutiliza la misma función).
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
    protected function summary(CarbonInterface $since, ?CarbonInterface $until = null): array
    {
        $sessionsQuery = ChatSession::query()->where('created_at', '>=', $since);
        if ($until) {
            $sessionsQuery->where('created_at', '<', $until);
        }
        // Sessions no tienen department_id directo; filtramos por user-> department si se pidió.
        if ($this->departmentId) {
            $sessionsQuery->whereHas('user', fn ($q) => $q->where('department_id', $this->departmentId));
        }

        $sessions = (clone $sessionsQuery)->count();
        $escalated = (clone $sessionsQuery)->whereNotNull('escalated_ticket_id')->count();

        $messages = $this->assistantQuery($since, $until);

        $assistant = (clone $messages)->count();
        $rated = (clone $messages)->whereNotNull('helpful')->count();
        $helpful = (clone $messages)->where('helpful', true)->count();
        $notHelpful = (clone $messages)->where('helpful', false)->count();

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
     * Serie temporal del CSAT por día (para el chart de tendencia).
     * Devuelve labels (d/m) y un dataset de % CSAT.
     *
     * @return array{labels: array<int, string>, csat: array<int, ?float>, volume: array<int, int>}
     */
    public function csatTrendData(): array
    {
        $days = (int) $this->window;
        $labels = [];
        $csat = [];
        $volume = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $end = $day->copy()->endOfDay();

            $q = $this->assistantQuery($day, $end);

            $total = (clone $q)->count();
            $rated = (clone $q)->whereNotNull('helpful')->count();
            $helpful = (clone $q)->where('helpful', true)->count();

            $labels[] = $day->format('d/m');
            $csat[] = $rated > 0 ? round(($helpful / $rated) * 100, 1) : null;
            $volume[] = $total;
        }

        return ['labels' => $labels, 'csat' => $csat, 'volume' => $volume];
    }

    /**
     * Distribución de respuestas por origen.
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
     * Top artículos KB con más votos negativos en el periodo.
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
     * Preguntas del usuario que recibieron una respuesta `fallback`.
     *
     * @return array<int, array{question: string, count: int}>
     */
    protected function fallbackQuestions(CarbonInterface $since): array
    {
        $fallbackAssistantIds = $this->assistantQuery($since)
            ->where('source_kind', 'fallback')
            ->pluck('id', 'chat_session_id');

        if ($fallbackAssistantIds->isEmpty()) {
            return [];
        }

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
            ->get(['id', 'content', 'kb_article_id', 'feedback_at', 'created_at'])
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
     * Livewire action: abre drill-down para ver los mensajes 👎 de un
     * artículo KB específico (los que aparecen en topUnhelpful).
     */
    public function showDrilldown(int $articleId): void
    {
        $this->drilldownArticleId = $articleId;
    }

    public function closeDrilldown(): void
    {
        $this->drilldownArticleId = null;
    }

    /**
     * Artículo seleccionado para drill-down (o null si no hay).
     */
    protected function drilldownArticle(): ?KbArticle
    {
        if (! $this->drilldownArticleId) {
            return null;
        }

        return KbArticle::query()->find($this->drilldownArticleId, ['id', 'title', 'slug']);
    }

    /**
     * Mensajes 👎 del artículo seleccionado, con la pregunta del user
     * que originó cada uno (para que el editor entienda qué falló).
     *
     * @return array<int, array{id: int, question: ?string, answer: string, voted_at: ?CarbonInterface}>
     */
    protected function drilldownMessages(CarbonInterface $since): array
    {
        if (! $this->drilldownArticleId) {
            return [];
        }

        $rows = $this->assistantQuery($since)
            ->where('kb_article_id', $this->drilldownArticleId)
            ->where('helpful', false)
            ->orderByDesc('feedback_at')
            ->limit(50)
            ->get(['id', 'chat_session_id', 'content', 'created_at', 'feedback_at']);

        return $rows->map(function ($row) {
            $question = ChatMessage::query()
                ->where('chat_session_id', $row->chat_session_id)
                ->where('role', 'user')
                ->where('created_at', '<=', $row->created_at)
                ->orderByDesc('created_at')
                ->value('content');

            return [
                'id' => $row->id,
                'question' => $question ? mb_strimwidth((string) $question, 0, 200, '…') : null,
                'answer' => mb_strimwidth((string) $row->content, 0, 400, '…'),
                'voted_at' => $row->feedback_at,
            ];
        })->all();
    }

    /**
     * Query base de mensajes assistant en el rango, aplicando el filtro
     * de departamento si está seleccionado (vía session->user).
     *
     * @return Builder<ChatMessage>
     */
    protected function assistantQuery(CarbonInterface $since, ?CarbonInterface $until = null): Builder
    {
        $q = ChatMessage::query()
            ->where('role', 'assistant')
            ->where('created_at', '>=', $since);

        if ($until) {
            $q->where('created_at', '<', $until);
        }

        if ($this->departmentId) {
            $q->whereHas('session.user', fn ($qq) => $qq->where('department_id', $this->departmentId));
        }

        return $q;
    }

    /**
     * Calcula delta entre dos valores (current vs previous). Devuelve
     * `null` si previous es null o cero (no se puede calcular %).
     */
    public function delta(?float $current, ?float $previous): ?array
    {
        if ($previous === null || $previous == 0.0 || $current === null) {
            return null;
        }

        $diff = $current - $previous;
        $pct = round(($diff / $previous) * 100, 1);

        return [
            'absolute' => round($diff, 1),
            'pct' => $pct,
            'direction' => $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat'),
        ];
    }

    /**
     * URL para crear un artículo KB pre-rellenando el título con una
     * pregunta detectada como gap (fallback). El editor solo escribe
     * el cuerpo y publica.
     */
    public function createKbFromGapUrl(string $question): string
    {
        // El panel de KB de soporte acepta query params para pre-fill
        // del form (ver KbArticleResource\Pages\CreateKbArticle).
        $title = ucfirst(trim($question));
        // Recorto a 200 chars para que entre en la URL sin abusar.
        $title = mb_strimwidth($title, 0, 200);

        return route('filament.soporte.resources.kb-articles.create', [
            'title' => $title,
        ]);
    }

    /**
     * Acción exportable a Excel. Genera un .xlsx con varias hojas:
     * Resumen / Origen / Top KB fallidos / Gaps / Negativos recientes.
     *
     * Devuelve un BinaryFileResponse via Livewire para descarga directa.
     */
    public function exportExcel(): BinaryFileResponse
    {
        $days = (int) $this->window;
        $since = now()->subDays($days);

        return Excel::download(
            new ChatbotMetricsExport(
                window: $days,
                departmentId: $this->departmentId,
                summary: $this->summary($since),
                summaryPrev: $this->summary(now()->subDays($days * 2), $since->copy()),
                sourceBreakdown: $this->sourceBreakdown($since),
                topUnhelpful: $this->topUnhelpful($since),
                fallbackQuestions: $this->fallbackQuestions($since),
                recentNegatives: $this->recentNegatives($since),
            ),
            'chatbot-metrics-'.now()->format('Y-m-d').'.xlsx',
        );
    }
}
