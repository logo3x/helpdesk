<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscalationLog extends Model
{
    protected $fillable = [
        'ticket_id',
        'type',
        'sla_minutes',
        'elapsed_minutes',
        'notified_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sla_minutes' => 'integer',
            'elapsed_minutes' => 'integer',
        ];
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function notifiedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'notified_user_id');
    }
}
