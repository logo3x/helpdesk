<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatFlowStat extends Model
{
    protected $fillable = ['chat_flow_id', 'chat_session_id', 'completed', 'steps_completed', 'escalated'];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'steps_completed' => 'integer',
            'escalated' => 'boolean',
        ];
    }

    /** @return BelongsTo<ChatFlow, $this> */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(ChatFlow::class, 'chat_flow_id');
    }

    /** @return BelongsTo<ChatSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class, 'chat_session_id');
    }
}
