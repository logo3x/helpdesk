<?php

namespace App\Models;

use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Identificación
        'asset_tag',
        'hostname',
        'serial_number',
        'sap_code',
        'type',
        'manufacturer',
        'model',
        // Asignación
        'user_id',
        'department_id',
        'project_id',
        'field',
        'location_zone',
        'management_area',
        // Sistema operativo y hardware
        'os_name',
        'os_version',
        'os_architecture',
        'cpu_cores',
        'cpu_model',
        'ram_mb',
        'disk_total_gb',
        'gpu_info',
        // Red
        'ip_address',
        'mac_address',
        'phone_line',
        'imei',
        // Estado y notas
        'status',
        'notes',
        'last_scan_at',
        // Mantenimiento
        'last_maintenance_at',
        'maintenance_interval_days',
        'next_maintenance_at',
        'maintenance_responsible_id',
        // Compra y garantía
        'purchased_at',
        'purchase_cost',
        'purchase_currency',
        'purchase_order',
        'supplier',
        'warranty_expires_at',
    ];

    protected function casts(): array
    {
        return [
            'cpu_cores' => 'integer',
            'ram_mb' => 'integer',
            'disk_total_gb' => 'integer',
            'last_scan_at' => 'datetime',
            'last_maintenance_at' => 'date',
            'next_maintenance_at' => 'date',
            'maintenance_interval_days' => 'integer',
            'purchased_at' => 'date',
            'warranty_expires_at' => 'date',
            'purchase_cost' => 'decimal:2',
        ];
    }

    /**
     * Hook para que `next_maintenance_at` se mantenga sincronizado con
     * `last_maintenance_at + maintenance_interval_days` automáticamente
     * sin que IT tenga que recordar calcularlo a mano. Si IT lo edita
     * manualmente, respetamos su valor (se aplica solo cuando viene null).
     */
    protected static function booted(): void
    {
        static::saving(function (self $asset): void {
            if (
                $asset->last_maintenance_at
                && $asset->maintenance_interval_days
                && $asset->isDirty(['last_maintenance_at', 'maintenance_interval_days'])
            ) {
                $asset->next_maintenance_at = $asset->last_maintenance_at
                    ->copy()
                    ->addDays($asset->maintenance_interval_days);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function maintenanceResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'maintenance_responsible_id');
    }

    /** @return HasMany<AssetHandover, $this> */
    public function handovers(): HasMany
    {
        return $this->hasMany(AssetHandover::class)->latest('delivered_at');
    }

    /** @return HasMany<AssetSoftware, $this> */
    public function software(): HasMany
    {
        return $this->hasMany(AssetSoftware::class);
    }

    /** @return HasMany<AssetComponent, $this> */
    public function components(): HasMany
    {
        return $this->hasMany(AssetComponent::class);
    }

    /** @return HasMany<AssetHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(AssetHistory::class);
    }

    /** @return HasMany<AssetScan, $this> */
    public function scans(): HasMany
    {
        return $this->hasMany(AssetScan::class);
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    /**
     * Activos cuyo mantenimiento está vencido o vence en los próximos
     * 30 días. Útil para el widget de "Mantenimientos pendientes".
     *
     * @param  Builder<self>  $query
     */
    public function scopeMaintenanceDue(Builder $query, int $daysAhead = 30): void
    {
        $query->whereNotNull('next_maintenance_at')
            ->where('next_maintenance_at', '<=', now()->addDays($daysAhead));
    }

    /**
     * Estado del mantenimiento legible:
     *   "vigente" | "por vencer" | "vencido" | null (sin plan)
     */
    public function getMaintenanceStatusAttribute(): ?string
    {
        if (! $this->next_maintenance_at) {
            return null;
        }

        $today = now()->startOfDay();

        if ($this->next_maintenance_at->lessThan($today)) {
            return 'vencido';
        }

        if ($this->next_maintenance_at->lessThanOrEqualTo($today->copy()->addDays(30))) {
            return 'por vencer';
        }

        return 'vigente';
    }

    /**
     * Find or create an asset by hostname, falling back to creating a new one.
     */
    public static function findOrCreateByHostname(string $hostname): self
    {
        return static::firstOrCreate(
            ['hostname' => $hostname],
            ['type' => 'desktop', 'status' => 'active'],
        );
    }
}
