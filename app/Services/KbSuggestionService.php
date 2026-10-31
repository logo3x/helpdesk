<?php

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\Department;
use App\Models\KbArticle;
use App\Models\User;
use App\Notifications\KbSuggestionCreatedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Detecta temas que la base de conocimiento no cubre y propone artículos.
 *
 * 1. Junta las consultas del chatbot que no tuvieron buena respuesta
 *    (sin artículo, IA caída o votadas 👎) y que aún no se analizaron.
 * 2. La IA decide cuáles ameritan un artículo (temas repetidos o
 *    claramente documentables que no existan ya) y a qué departamento van.
 * 3. Por cada tema crea un borrador "pendiente de revisión" cuyo autor es
 *    el usuario de sistema, y avisa a los supervisores del departamento y
 *    a los administradores.
 */
class KbSuggestionService
{
    public const SYSTEM_USER_EMAIL = 'asistente-ia@helpdesk.local';

    /** Máximo de borradores que se crean en una sola revisión. */
    public const MAX_SUGGESTIONS_PER_RUN = 3;

    public function __construct(
        protected LlmService $llm,
        protected RagService $rag,
    ) {}

    /**
     * @return Collection<int, KbArticle> artículos sugeridos creados
     */
    public function run(int $days = 7): Collection
    {
        $gaps = $this->pendingGaps($days);

        if ($gaps->isEmpty()) {
            return collect();
        }

        $queries = $gaps->pluck('query')->unique()->values();
        $suggestions = $this->analyze($queries);

        // Se marcan como revisadas aunque la IA falle o no sugiera nada,
        // para no reanalizar lo mismo cada hora.
        if ($suggestions !== null) {
            ChatMessage::whereIn('id', $gaps->pluck('id'))->get()->each(function (ChatMessage $message): void {
                $message->forceFill([
                    'metadata' => [...($message->metadata ?? []), 'kb_gap_reviewed_at' => now()->toIso8601String()],
                ])->saveQuietly();
            });
        }

        return collect($suggestions ?? [])
            ->take(self::MAX_SUGGESTIONS_PER_RUN)
            ->map(fn (array $suggestion): ?KbArticle => $this->createSuggestion($suggestion))
            ->filter()
            ->values();
    }

    /**
     * Respuestas del asistente que no resolvieron la consulta, con la
     * pregunta del usuario que las originó.
     *
     * @return Collection<int, array{id: int, query: string}>
     */
    public function pendingGaps(int $days = 7): Collection
    {
        return ChatMessage::query()
            ->where('role', 'assistant')
            ->where('created_at', '>=', now()->subDays($days))
            ->where(fn ($q) => $q->whereIn('source_kind', ['fallback', 'llm_failed'])->orWhere('helpful', false))
            ->orderBy('id')
            ->limit(60)
            ->get()
            ->reject(fn (ChatMessage $message): bool => isset($message->metadata['kb_gap_reviewed_at']))
            ->map(function (ChatMessage $message): ?array {
                $query = ChatMessage::query()
                    ->where('chat_session_id', $message->chat_session_id)
                    ->where('role', 'user')
                    ->where('id', '<', $message->id)
                    ->orderByDesc('id')
                    ->value('content');

                return filled($query) ? ['id' => $message->id, 'query' => mb_substr(trim($query), 0, 300)] : null;
            })
            ->filter()
            ->values();
    }

    /**
     * Pide a la IA que agrupe las consultas y decida qué temas ameritan
     * un artículo nuevo. Devuelve null si la IA no respondió.
     *
     * @param  Collection<int, string>  $queries
     * @return array<int, array{title: string, department: ?string, reason: string, queries: array<int, string>, description: string}>|null
     */
    public function analyze(Collection $queries): ?array
    {
        $departments = Department::where('is_active', true)->orderBy('name')->pluck('name')->implode("\n- ");
        $existing = KbArticle::query()->latest('id')->limit(150)->pluck('title')->implode("\n- ");
        $list = $queries->map(fn (string $q, int $i): string => ($i + 1).'. '.$q)->implode("\n");
        $max = self::MAX_SUGGESTIONS_PER_RUN;

        $system = <<<PROMPT
Eres analista de la mesa de ayuda de Confipetrol. Revisas preguntas que empleados
le hicieron al chatbot y que NO tuvieron una buena respuesta, para decidir qué
artículos nuevos de la base de conocimiento valen la pena.

Sugiere un artículo SOLO si:
- El tema se repite (2 o más preguntas parecidas) o es una duda claramente frecuente
  en una empresa (accesos, contraseñas, software, equipos, trámites internos).
- NO está cubierto por un artículo existente (lista abajo).
- Es una consulta de soporte real (ignora saludos, pruebas, insultos o mensajes sin sentido).

Máximo {$max} sugerencias, de la más útil a la menos útil. Si nada amerita, devuelve [].

DEPARTAMENTOS (usa el nombre exacto):
- {$departments}

ARTÍCULOS QUE YA EXISTEN:
- {$existing}

Responde EXCLUSIVAMENTE un arreglo JSON, sin texto adicional ni bloques de código:
[{"title": "Título del artículo", "department": "Nombre exacto del departamento",
  "reason": "Por qué hace falta, en una frase", "queries": ["pregunta 1", "pregunta 2"],
  "description": "Qué debe explicar el artículo, para que un redactor lo escriba"}]
PROMPT;

        $raw = $this->llm->chat([['role' => 'user', 'content' => "Preguntas sin buena respuesta:\n{$list}"]], $system);

        if (blank($raw)) {
            return null;
        }

        $clean = preg_replace(['/^```(?:json)?\s*/m', '/\s*```\s*$/m'], '', trim($raw));
        $start = strpos($clean, '[');
        $end = strrpos($clean, ']');
        $parsed = ($start !== false && $end !== false) ? json_decode(substr($clean, $start, $end - $start + 1), true) : null;

        if (! is_array($parsed)) {
            Log::warning('[KB-SUGERENCIAS] Respuesta de la IA no parseable', ['raw' => mb_substr($raw, 0, 500)]);

            return [];
        }

        return collect($parsed)
            ->filter(fn ($item): bool => is_array($item) && filled($item['title'] ?? null))
            ->map(fn (array $item): array => [
                'title' => mb_substr(trim((string) $item['title']), 0, 200),
                'department' => filled($item['department'] ?? null) ? (string) $item['department'] : null,
                'reason' => (string) ($item['reason'] ?? ''),
                'queries' => array_values(array_filter(array_map('strval', (array) ($item['queries'] ?? [])))),
                'description' => (string) ($item['description'] ?? $item['title']),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{title: string, department: ?string, reason: string, queries: array<int, string>, description: string}  $suggestion
     */
    public function createSuggestion(array $suggestion): ?KbArticle
    {
        $slug = Str::slug($suggestion['title']);

        // Evita duplicados: mismo slug o un artículo existente muy parecido.
        if ($slug === '' || KbArticle::withTrashed()->where('slug', $slug)->exists()) {
            return null;
        }

        $similar = $this->rag->search($suggestion['title'], topN: 1, threshold: 0.6)->first();

        if ($similar !== null) {
            return null;
        }

        $department = $suggestion['department']
            ? Department::where('name', $suggestion['department'])->first()
            : null;

        $draft = $this->llm->draftKbArticle(
            naturalLanguageInput: $suggestion['description']."\n\nPreguntas reales de los empleados:\n- ".implode("\n- ", $suggestion['queries']),
            tone: 'amigable',
            departmentName: $department?->name,
        );

        if ($draft === null) {
            return null;
        }

        $system = $this->systemUser();

        $article = KbArticle::create([
            'title' => $draft['title'] ?: $suggestion['title'],
            'slug' => $this->uniqueSlug($draft['title'] ?: $suggestion['title']),
            'body' => $draft['body'],
            'department_id' => $department?->id,
            'status' => 'draft',
        ]);

        $article->forceFill([
            'author_id' => $system->id,
            'is_ai_suggestion' => true,
            'suggestion_context' => [
                'reason' => $suggestion['reason'],
                'queries' => array_slice($suggestion['queries'], 0, 10),
                'generated_at' => now()->toIso8601String(),
            ],
            'pending_review_at' => now(),
            'pending_review_by_id' => $system->id,
        ])->save();

        $this->notifyReviewers($article);

        return $article;
    }

    /**
     * Supervisores del departamento del artículo + administradores.
     */
    public function notifyReviewers(KbArticle $article): void
    {
        User::query()
            ->where(fn ($q) => $q
                ->whereHas('roles', fn ($r) => $r->whereIn('name', ['admin', 'super_admin']))
                ->orWhere(fn ($s) => $s
                    ->whereHas('roles', fn ($r) => $r->where('name', 'supervisor_soporte'))
                    ->when($article->department_id, fn ($d) => $d->where('department_id', $article->department_id))))
            ->where('email', '!=', self::SYSTEM_USER_EMAIL)
            ->get()
            ->each(fn (User $user) => $user->notify(new KbSuggestionCreatedNotification($article)));
    }

    /**
     * Usuario "Asistente IA (sistema)" que figura como autor. No tiene
     * roles, así que no puede entrar a ningún panel.
     */
    public function systemUser(): User
    {
        return User::firstOrCreate(
            ['email' => self::SYSTEM_USER_EMAIL],
            ['name' => 'Asistente IA (sistema)', 'password' => Str::password(40)],
        );
    }

    protected function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'sugerencia-kb';
        $slug = $base;
        $i = 2;

        while (KbArticle::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
