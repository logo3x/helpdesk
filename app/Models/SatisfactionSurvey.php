<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SatisfactionSurvey extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'rating',
        'comment',
        'token',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $survey) {
            if (blank($survey->token)) {
                $survey->token = Str::random(64);
            }
        });
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->responded_at === null;
    }
}
