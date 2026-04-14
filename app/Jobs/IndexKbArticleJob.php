<?php

namespace App\Jobs;

use App\Models\KbArticle;
use App\Models\KbEmbedding;
use App\Services\LlmService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Generates vector embeddings for a KB article when it is published
 * or updated. Splits the article into paragraph-level chunks and
 * embeds each one for granular RAG retrieval.
 */
class IndexKbArticleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public KbArticle $article,
    ) {}

    public function handle(LlmService $llm): void
    {
        // Remove old embeddings for this article
        KbEmbedding::where('kb_article_id', $this->article->id)->delete();

        $chunks = $this->splitIntoChunks($this->article->body);

        foreach ($chunks as $index => $chunk) {
            $vector = $llm->embed("{$this->article->title}\n\n{$chunk}");

            if ($vector === null) {
                Log::warning("IndexKbArticleJob: Failed to embed chunk {$index} for article {$this->article->id}");

                continue;
            }

            KbEmbedding::create([
                'kb_article_id' => $this->article->id,
                'chunk_index' => $index,
                'content' => $chunk,
                'embedding' => $vector,
                'dimensions' => count($vector),
            ]);
        }

        Log::info("IndexKbArticleJob: Indexed {$this->article->id} ({$this->article->title}) with ".count($chunks).' chunks.');
    }

    /**
     * Split article body into paragraph-sized chunks (~500 chars each).
     *
     * @return array<int, string>
     */
    protected function splitIntoChunks(string $body): array
    {
        // Split by double newline (paragraph boundaries)
        $paragraphs = preg_split('/\n{2,}/', trim($body));
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if (blank($paragraph)) {
                continue;
            }

            if (mb_strlen($current."\n\n".$paragraph) > 500 && filled($current)) {
                $chunks[] = trim($current);
                $current = $paragraph;
            } else {
                $current .= (filled($current) ? "\n\n" : '').$paragraph;
            }
        }

        if (filled($current)) {
            $chunks[] = trim($current);
        }

        // If there's only whitespace/empty result, embed the title at minimum
        if (empty($chunks)) {
            $chunks[] = $body;
        }

        return $chunks;
    }
}
