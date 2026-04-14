<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KbCategory extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'parent_id', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /** @return BelongsTo<KbCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'parent_id');
    }

    /** @return HasMany<KbCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(KbCategory::class, 'parent_id');
    }

    /** @return HasMany<KbArticle, $this> */
    public function articles(): HasMany
    {
        return $this->hasMany(KbArticle::class);
    }
}
