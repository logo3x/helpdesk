<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbArticleFeedback extends Model
{
    protected $table = 'kb_article_feedback';

    protected $fillable = ['kb_article_id', 'user_id', 'is_helpful', 'comment'];

    protected function casts(): array
    {
        return ['is_helpful' => 'boolean'];
    }

    /** @return BelongsTo<KbArticle, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'kb_article_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
