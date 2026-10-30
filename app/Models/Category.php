<?php

namespace App\Models;

use App\Models\Concerns\AuditsActivity;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use AuditsActivity, HasFactory;

    /** Módulo con el que aparece en la bitácora. */
    protected string $auditLogName = 'configuracion';

    protected $fillable = [
        'department_id',
        'parent_id',
        'name',
        'slug',
        'description',
        'icon',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<Category, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /** @return HasMany<Ticket, $this> */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Solo categorías raíz (sin padre). Útil para selects de "categoría padre"
     * y para listados jerárquicos.
     *
     * @param  Builder<self>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * Devuelve el nombre completo "Padre › Hija" o solo "Padre" si es raíz.
     * Útil para selects en portal y reports donde una categoría hija sin
     * su contexto se lee huérfana.
     */
    public function qualifiedName(): string
    {
        if ($this->parent_id && $this->relationLoaded('parent') && $this->parent) {
            return $this->parent->name.' › '.$this->name;
        }

        if ($this->parent_id) {
            $parent = $this->parent()->first();
            if ($parent) {
                return $parent->name.' › '.$this->name;
            }
        }

        return $this->name;
    }

    /**
     * Impide ciclos: solo 2 niveles. Si esta categoría tiene hijas, no puede
     * tener padre. Si tiene padre, no puede tener hijas.
     */
    public function canBecomeChild(): bool
    {
        return $this->children()->doesntExist();
    }
}
