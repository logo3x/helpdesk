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

        return KbEmbedding::with('article:id,title,slug')
            ->get()
            ->map(fn (KbEmbedding $emb) => [
                'content' => $emb->content,
                'similarity' => $emb->cosineSimilarity($queryVector),
                'article_id' => $emb->kb_article_id,
                'article_title' => $emb->article?->title,
            ])
            ->filter(fn (array $item) => $item['similarity'] >= $threshold)
            ->sortByDesc('similarity')
            ->take($topN)
            ->values();
    }

    /**
     * Simple keyword overlap search — counts how many query tokens appear
     * in each article's title + body. Good enough for <100 articles.
     *
     * @return Collection<int, array{content: string, similarity: float, article_id: int, article_title: string|null}>
     */
    protected function keywordSearch(string $query, int $topN): Collection
    {
        $tokens = collect(preg_split('/\s+/', mb_strtolower($query)))
            ->filter(fn ($t) => mb_strlen($t) >= 3) // skip very short words
            ->values();

        if ($tokens->isEmpty()) {
            return collect();
        }

        $articles = KbArticle::query()->published()->get(['id', 'title', 'body']);

        return $articles
            ->map(function (KbArticle $article) use ($tokens) {
                $haystack = mb_strtolower($article->title.' '.$article->body);
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
}
