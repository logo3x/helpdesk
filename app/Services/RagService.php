<?php

namespace App\Services;

use App\Models\KbArticle;
use App\Models\KbEmbedding;
use Illuminate\Support\Collection;

/**
 * Retrieval Augmented Generation.
 *
 * Primary mode: cosine similarity on vector embeddings (requires
 * IndexKbArticleJob to have run + LLM with embeddings API).
 *
 * Fallback mode: keyword overlap search on KbArticle title+body.
 * Used when no embeddings exist yet or the LLM provider does not
 * support the /embeddings endpoint (e.g. most OpenRouter free tier).
 */
class RagService
{
    public function __construct(
        protected LlmService $llm,
    ) {}

    /**
     * Search for the top-N most relevant KB chunks for a query.
     * Falls back to keyword search if vector embeddings are unavailable.
     *
     * @return Collection<int, array{content: string, similarity: float, article_id: int, article_title: string|null}>
     */
    public function search(string $query, int $topN = 3, float $threshold = 0.4): Collection
    {
        // Prefer vector search when embeddings exist for at least one article
        if (KbEmbedding::query()->exists()) {
            $vectorResults = $this->vectorSearch($query, $topN, $threshold);

            if ($vectorResults->isNotEmpty()) {
                return $vectorResults;
            }
        }

        return $this->keywordSearch($query, $topN);
    }

    /**
     * Build a context string from the top search results for the LLM prompt.
     */
    public function buildContext(string $query, int $topN = 3): string
    {
        $results = $this->search($query, $topN);

        if ($results->isEmpty()) {
            return '';
        }

        return $results->map(fn (array $item) => "[Artículo: {$item['article_title']}]\n{$item['content']}")
            ->implode("\n\n---\n\n");
    }

    /**
     * Cosine similarity vector search.
     *
     * @return Collection<int, array{content: string, similarity: float, article_id: int, article_title: string|null}>
     */
    protected function vectorSearch(string $query, int $topN, float $threshold): Collection
    {
        $queryVector = $this->llm->embed($query);

        if ($queryVector === null) {
            return collect();
        }

        // Chunk para no cargar miles de embeddings en memoria. Cada chunk
        // calcula similitud y acumula los top-N. Escalable a decenas de
        // miles de embeddings sin sobrecargar la RAM.
        $heap = collect();

        KbEmbedding::with('article:id,title,slug')
            ->chunk(500, function ($embeddings) use (&$heap, $queryVector, $threshold, $topN): void {
                foreach ($embeddings as $emb) {
                    $sim = $emb->cosineSimilarity($queryVector);

                    if ($sim < $threshold) {
                        continue;
                    }

                    $heap->push([
                        'content' => $emb->content,
                        'similarity' => $sim,
                        'article_id' => $emb->kb_article_id,
                        'article_title' => $emb->article?->title,
                    ]);

                    // Mantener solo los top-N en memoria mientras iteramos.
                    if ($heap->count() > $topN * 4) {
                        $heap = $heap->sortByDesc('similarity')->take($topN * 2)->values();
                    }
                }
            });

        return $heap->sortByDesc('similarity')->take($topN)->values();
    }

    /**
     * Simple keyword overlap search — counts how many query tokens appear
     * in each article's title + body. Good enough for <100 articles.
     *
     * @return Collection<int, array{content: string, similarity: float, article_id: int, article_title: string|null}>
     */
    protected function keywordSearch(string $query, int $topN): Collection
    {
        // Filler words que no aportan información; se excluyen para que no
        // penalicen el score de búsqueda (ej: "como puedo cambiar mi X").
        $stopwords = [
            'como', 'cómo', 'puedo', 'que', 'qué', 'donde', 'dónde', 'cuando',
            'cuándo', 'para', 'por', 'mi', 'mis', 'tu', 'tus', 'los', 'las',
            'una', 'uno', 'del', 'con', 'sin', 'sobre', 'esta', 'este', 'eso',
            'aquí', 'allá', 'hola', 'hay', 'necesito', 'quiero', 'ayuda',
            'ayudar', 'favor', 'por favor',
        ];

        $normalized = $this->stripAccentsAndPunctuation(mb_strtolower($query));

        $tokens = collect(preg_split('/\s+/', $normalized))
            ->filter(fn ($t) => mb_strlen($t) >= 3)
            ->reject(fn ($t) => in_array($t, $stopwords, true))
            ->values();

        if ($tokens->isEmpty()) {
            return collect();
        }

        $articles = KbArticle::query()->published()->get(['id', 'title', 'body']);

        return $articles
            ->map(function (KbArticle $article) use ($tokens) {
                $haystack = $this->stripAccentsAndPunctuation(mb_strtolower($article->title.' '.$article->body));
                $hits = $tokens->filter(fn ($t) => str_contains($haystack, $t))->count();

                return [
                    'content' => mb_substr($article->body, 0, 1500),
                    'similarity' => $tokens->count() > 0 ? $hits / $tokens->count() : 0,
                    'article_id' => $article->id,
                    'article_title' => $article->title,
                ];
            })
            ->filter(fn (array $item) => $item['similarity'] > 0)
            ->sortByDesc('similarity')
            ->take($topN)
            ->values();
    }

    /**
     * Normaliza acentos y quita puntuación para matching robusto:
     *   "Cómo configurar tu cuenta?" → "como configurar tu cuenta"
     */
    protected function stripAccentsAndPunctuation(string $text): string
    {
        $accents = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u'];
        $text = strtr($text, $accents);

        return preg_replace('/[¿?¡!.,;:()\[\]{}"\']/', ' ', $text);
    }
}
