<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class MaintenanceSurvey extends Model
{
    protected $fillable = [
        'asset_id',
        'user_id',
        'token',
        'rating',
        'comment',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'responded_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $survey): void {
            if (! $survey->token) {
                $survey->token = Str::random(64);
            }
        });
    }

    public function isPending(): bool
    {
        return $this->responded_at === null;
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
