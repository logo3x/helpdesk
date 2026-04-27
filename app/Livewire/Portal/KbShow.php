<?php

namespace App\Livewire\Portal;

use App\Models\KbArticle;
use App\Models\KbArticleFeedback;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detalle de un artículo del centro de ayuda. Solo muestra artículos
 * con `status = published`; un slug en Borrador o Archivado retorna
 * 404 desde mount() para no exponer contenido sin aprobar.
 *
 * Incrementa atómicamente `views_count` la primera vez que un usuario
 * abre el artículo en su sesión (evita inflar el contador con cada
 * navegación interna). Permite dar feedback útil/no-útil con UPSERT
 * sobre el índice único (kb_article_id, user_id).
 */
#[Layout('layouts.portal')]
class KbShow extends Component
{
    public KbArticle $article;

    /**
     * Voto actual del usuario para este artículo:
     *   true  → marcó "Sí, me ayudó"
     *   false → marcó "No, no me ayudó"
     *   null  → aún no ha votado
     */
    public ?bool $userVote = null;

    public function mount(string $slug): void
    {
        $article = KbArticle::query()
            ->published()
            ->where('slug', $slug)
            ->with('department:id,name', 'category:id,name', 'author:id,name')
            ->first();

        abort_if($article === null, 404, 'Artículo no encontrado o no publicado.');

        $this->article = $article;

        // Solo contar la primera vista por sesión: marcamos en sesión un
        // flag por artículo. Evita que un F5 repetido infle el contador.
        $sessionKey = "kb.viewed.{$article->id}";

        if (! session()->has($sessionKey)) {
            DB::table('kb_articles')
                ->where('id', $article->id)
                ->increment('views_count');

            session()->put($sessionKey, true);
        }

        // Cargar feedback previo del usuario (si existe) para mostrar
        // el voto actual en la UI.
        $previous = KbArticleFeedback::query()
            ->where('kb_article_id', $article->id)
            ->where('user_id', auth()->id())
            ->first();

        $this->userVote = $previous?->is_helpful;
    }

    public function vote(bool $isHelpful): void
    {
        $userId = auth()->id();
        $articleId = $this->article->id;

        // Si el voto no cambia, no hacemos nada (evita desbalancear contadores).
        if ($this->userVote === $isHelpful) {
            return;
        }

        DB::transaction(function () use ($userId, $articleId, $isHelpful) {
            $existing = KbArticleFeedback::query()
                ->where('kb_article_id', $articleId)
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                // Capturamos el voto anterior ANTES de actualizar para
                // saber qué counter decrementar.
                $previousIsHelpful = (bool) $existing->is_helpful;
                $existing->update(['is_helpful' => $isHelpful]);

                // Como un usuario solo tiene una fila por artículo
                // (índice único kb_article_id+user_id), al cambiar de
                // voto siempre hay exactamente uno para decrementar y
                // uno para incrementar.
                if ($previousIsHelpful) {
                    DB::table('kb_articles')->where('id', $articleId)->update([
                        'helpful_count' => DB::raw('helpful_count - 1'),
                        'not_helpful_count' => DB::raw('not_helpful_count + 1'),
                    ]);
                } else {
                    DB::table('kb_articles')->where('id', $articleId)->update([
                        'helpful_count' => DB::raw('helpful_count + 1'),
                        'not_helpful_count' => DB::raw('not_helpful_count - 1'),
                    ]);
                }
            } else {
                // Primer voto del usuario.
                KbArticleFeedback::create([
                    'kb_article_id' => $articleId,
                    'user_id' => $userId,
                    'is_helpful' => $isHelpful,
                ]);

                $column = $isHelpful ? 'helpful_count' : 'not_helpful_count';
                DB::table('kb_articles')->where('id', $articleId)->increment($column);
            }
        });

        $this->userVote = $isHelpful;
        $this->article->refresh();
    }

    public function render(): View
    {
        return view('livewire.portal.kb-show')
            ->title($this->article->title);
    }
}
