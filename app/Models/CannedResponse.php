<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CannedResponse extends Model
{
    protected $fillable = [
        'title',
        'body',
        'category_id',
        'created_by_id',
        'is_shared',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->where(fn ($q) => $q
            ->where('is_shared', true)
            ->orWhere('created_by_id', $user->id)
        )->where('is_active', true);
    }
}
