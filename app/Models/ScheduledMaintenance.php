<?php

namespace App\Models;

use App\Enums\MaintenanceFrequency;
use App\Enums\MaintenanceStatus;
use App\Observers\ScheduledMaintenanceObserver;
use Database\Factories\ScheduledMaintenanceFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Programación individual de un mantenimiento sobre un activo.
 *
 * Ciclo de vida:
 *   pendiente → (edición del agente) → cumplido | no_cumplido
 *
 * Al cerrar (cumplido/no_cumplido), el observer auto-genera la siguiente
 * ocurrencia según la frecuencia (cuatrimestral=120d, anual=365d) para
 * mantener el ciclo activo sin intervención manual.
 */
#[ObservedBy(ScheduledMaintenanceObserver::class)]
class ScheduledMaintenance extends Model
{
    /** @use HasFactory<ScheduledMaintenanceFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'agent_id',
        'created_by_id',
        'parent_id',
        'scheduled_at',
        'status',
        'progress_percent',
        'frequency',
        'observations',
        'not_completed_reason',
        'completed_at',
        'notified_agent_at',
        'notified_due_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'date',
            'completed_at' => 'datetime',
            'status' => MaintenanceStatus::class,
            'frequency' => MaintenanceFrequency::class,
            'progress_percent' => 'integer',
            'notified_agent_at' => 'datetime',
            'notified_due_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Días equivalentes a la frecuencia. */
    public function frequencyDays(): int
    {
        return $this->frequency?->days() ?? 0;
    }

    /** True si el mantenimiento ya venció (fecha pasada + status pendiente). */
    public function isOverdue(): bool
    {
        return $this->status === MaintenanceStatus::Pendiente
            && $this->scheduled_at->isPast();
    }
}
