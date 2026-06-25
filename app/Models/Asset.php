<?php

namespace App\Models;

use App\Notifications\AssetMaintenanceAssignedNotification;
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

    /**
     * Campos cuyos cambios se registran automáticamente en la hoja de
     * vida del activo. Excluye campos volátiles (last_scan_at, ip_address,
     * mac_address, agent_version, last_scan_status) que los scans del
     * agente sobrescriben cada hora y generarían ruido.
     */
    public const TRACKED_FIELDS = [
        'asset_tag', 'hostname', 'serial_number', 'sap_code', 'type',
        'manufacturer', 'model',
        'user_id', 'custodian_name', 'department_id', 'project_id', 'field', 'location_zone',
        'management_area',
        'status', 'notes',
        'last_maintenance_at', 'maintenance_interval_days', 'maintenance_responsible_id',
        'purchased_at', 'purchase_cost', 'warranty_expires_at',
    ];

    /**
     * Bandera de instancia para que las acciones que ya crean histories
     * manuales (transferCustodian, markMaintenance, generateHandover,
     * etc.) eviten que el observer registre un evento duplicado.
     */
    public bool $skipAutoHistory = false;

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
        'custodian_name',
        'department_id',
        'project_id',
        'field',
        'location_zone',
        'management_area',
        // Registro
        'created_by_user_id',
        'registration_source',
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
        'agent_version',
        'last_scan_status',
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
        static::creating(function (self $asset): void {
            if (! $asset->created_by_user_id && auth()->id()) {
                $asset->created_by_user_id = auth()->id();
            }
            if (! $asset->registration_source) {
                $asset->registration_source = 'manual';
            }
        });

        static::saving(function (self $asset): void {
            // Recalcula next_maintenance_at siempre que tengamos ambos campos,
            // ya sea porque cambiaron ahora o porque next_maintenance_at está vacío
            // (para reparar activos que tenían la fecha sin calcular).
            if (
                $asset->last_maintenance_at
                && $asset->maintenance_interval_days
                && (
                    $asset->isDirty(['last_maintenance_at', 'maintenance_interval_days'])
                    || ! $asset->next_maintenance_at
                )
            ) {
                $asset->next_maintenance_at = $asset->last_maintenance_at
                    ->copy()
                    ->addDays($asset->maintenance_interval_days);
            }
        });

        // Auto-tracking de cambios en la hoja de vida. Se ejecuta tras
        // updated() para tener acceso a getOriginal() y getChanges().
        // Las acciones que ya crean historias específicas (con label
        // amigable como "Custodio asignado") setean skipAutoHistory=true
        // para no duplicar.
        static::updated(function (self $asset): void {
            // Notificar al responsable de mantenimiento si fue asignado/cambiado
            if ($asset->wasChanged('maintenance_responsible_id') && $asset->maintenance_responsible_id) {
                $responsible = User::find($asset->maintenance_responsible_id);
                $assignedBy = auth()->user();
                if ($responsible && $assignedBy && $responsible->id !== $assignedBy->id) {
                    try {
                        $responsible->notify(new AssetMaintenanceAssignedNotification(
                            asset: $asset,
                            assignedBy: $assignedBy,
                        ));
                    } catch (\Throwable) {
                        // No bloquear el guardado si falla la notificación
                    }
                }
            }

            if ($asset->skipAutoHistory) {
                return;
            }

            $tracked = array_intersect_key(
                $asset->getChanges(),
                array_flip(self::TRACKED_FIELDS),
            );

            foreach ($tracked as $field => $newValue) {
                $original = $asset->getOriginal($field);

                $asset->histories()->create([
                    'user_id' => auth()->id(),
                    'action' => 'updated',
                    'field' => $field,
                    'old_value' => $original !== null ? (string) $original : null,
                    'new_value' => $newValue !== null ? (string) $newValue : null,
                ]);
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
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
     * Equipos con agente que han dejado de reportar (last_scan_at más
     * viejo que el umbral, o nunca reportaron). Útil para el widget
     * "PCs sin reportar" — implica que el agente está caído, el equipo
     * apagado por días, o se perdió conectividad.
     *
     * Solo aplica a activos que YA han reportado al menos una vez —
     * si nunca reportaron es que aún no se instaló el agente.
     *
     * @param  Builder<self>  $query
     */
    public function scopeStaleAgent(Builder $query, int $daysSilent = 14): void
    {
        $query->whereNotNull('last_scan_at')
            ->where('last_scan_at', '<', now()->subDays($daysSilent))
            ->where('status', '!=', 'retired');
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
        // Incluir soft-deleted para restaurar si el activo fue borrado y
        // el agente lo vuelve a detectar.
        $existing = static::withTrashed()->where('hostname', $hostname)->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        return static::create(['hostname' => $hostname, 'type' => 'desktop', 'status' => 'active', 'registration_source' => 'scan_agent']);
    }
}
