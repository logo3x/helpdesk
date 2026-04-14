<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KbArticleVersion extends Model
{
    protected $fillable = [
        'kb_article_id', 'editor_id', 'version_number',
        'title', 'body', 'change_summary',
    ];

    protected function casts(): array
    {
        return ['version_number' => 'integer'];
    }

    /** @return BelongsTo<KbArticle, $this> */
    public function article(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'kb_article_id');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
