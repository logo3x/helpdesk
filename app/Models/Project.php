<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Proyecto / contrato al que se cargan los activos del inventario.
 * Ej: 499015105 PERENCO CARUPANA · 62010905100 GRANTIERRA VMM.
 */
class Project extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code',
        'name',
        'client',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Etiqueta "código · nombre" para usar en selects del inventario.
     */
    public function getLabelAttribute(): string
    {
        return "{$this->code} · {$this->name}";
    }
}
