<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'chat_session_id',
        'role',
        'content',
        'metadata',
        'source_kind',
        'kb_article_id',
        'similarity',
        'helpful',
        'feedback_comment',
        'feedback_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'similarity' => 'float',
            'helpful' => 'boolean',
            'feedback_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ChatSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }

    /**
     * Artículo KB usado como fuente de la respuesta (si aplica). Sin FK
     * dura para preservar la trazabilidad histórica aunque el artículo
     * se borre — por eso usamos `withDefault` para no romper la vista.
     *
     * @return BelongsTo<KbArticle, $this>
     */
    public function kbArticle(): BelongsTo
    {
        return $this->belongsTo(KbArticle::class, 'kb_article_id');
    }
}
