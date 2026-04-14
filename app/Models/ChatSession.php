<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatSession extends Model
{
    protected $fillable = ['user_id', 'status', 'escalated_ticket_id', 'channel'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Ticket, $this> */
    public function escalatedTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'escalated_ticket_id');
    }

    /** @return HasMany<ChatMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /** @return HasMany<ChatFlowStat, $this> */
    public function flowStats(): HasMany
    {
        return $this->hasMany(ChatFlowStat::class);
    }
}
