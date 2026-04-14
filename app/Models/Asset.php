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
        'asset_tag',
        'hostname',
        'serial_number',
        'type',
        'manufacturer',
        'model',
        'user_id',
        'department_id',
        'os_name',
        'os_version',
        'os_architecture',
        'cpu_cores',
        'cpu_model',
        'ram_mb',
        'disk_total_gb',
        'gpu_info',
        'ip_address',
        'mac_address',
        'status',
        'notes',
        'last_scan_at',
    ];

    protected function casts(): array
    {
        return [
            'cpu_cores' => 'integer',
            'ram_mb' => 'integer',
            'disk_total_gb' => 'integer',
            'last_scan_at' => 'datetime',
        ];
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

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
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
