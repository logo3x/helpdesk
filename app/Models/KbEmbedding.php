<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbEmbedding extends Model
{
    protected $fillable = ['kb_article_id', 'chunk_index', 'content', 'embedding', 'dimensions'];

    protected function casts(): array
    {
        return [
            'embedding' => 'array',
            'chunk_index' => 'integer',
            'dimensions' => 'integer',
        ];
    }

    /** @return BelongsTo<KbArticle, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'kb_article_id');
    }

    /**
     * Compute cosine similarity between this embedding and a query vector.
     *
     * @param  array<int, float>  $queryVector
     */
    public function cosineSimilarity(array $queryVector): float
    {
        $a = $this->embedding;
        $b = $queryVector;

        if (count($a) !== count($b) || count($a) === 0) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0, $len = count($a); $i < $len; $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $denominator = sqrt($normA) * sqrt($normB);

        return $denominator > 0 ? $dotProduct / $denominator : 0.0;
    }
}
