<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatFlow extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'triggers', 'steps', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'triggers' => 'array',
            'steps' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return HasMany<ChatFlowStat, $this> */
    public function stats(): HasMany
    {
        return $this->hasMany(ChatFlowStat::class);
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Check if this flow matches a given user message.
     */
    public function matchesTrigger(string $message): bool
    {
        $message = mb_strtolower(trim($message));

        foreach ($this->triggers as $trigger) {
            if (str_contains($message, mb_strtolower($trigger))) {
                return true;
            }
        }

        return false;
    }
}
