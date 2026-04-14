<?php

namespace App\Services;

use App\Models\KbEmbedding;
use Illuminate\Support\Collection;

/**
 * Retrieval Augmented Generation — finds the most relevant KB chunks
 * for a given user query using cosine similarity on embeddings.
 */
class RagService
{
    public function __construct(
        protected LlmService $llm,
    ) {}

    /**
     * Search for the top-N most relevant KB chunks for a query.
     *
     * @return Collection<int, array{content: string, similarity: float, article_id: int}>
     */
    public function search(string $query, int $topN = 3, float $threshold = 0.5): Collection
    {
        $queryVector = $this->llm->embed($query);

        if ($queryVector === null) {
            return collect();
        }

        $embeddings = KbEmbedding::with('article:id,title,slug')->get();

        return $embeddings
            ->map(function (KbEmbedding $emb) use ($queryVector) {
                return [
                    'content' => $emb->content,
                    'similarity' => $emb->cosineSimilarity($queryVector),
                    'article_id' => $emb->kb_article_id,
                    'article_title' => $emb->article?->title,
                ];
            })
            ->filter(fn (array $item) => $item['similarity'] >= $threshold)
            ->sortByDesc('similarity')
            ->take($topN)
            ->values();
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

        return $results->map(function (array $item, int $index) {
            $num = $index + 1;

            return "[Artículo: {$item['article_title']}]\n{$item['content']}";
        })->implode("\n\n---\n\n");
    }
}
