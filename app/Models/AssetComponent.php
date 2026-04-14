<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetComponent extends Model
{
    protected $fillable = [
        'asset_id',
        'type',
        'name',
        'serial_number',
        'specs',
    ];

    protected function casts(): array
    {
        return [
            'specs' => 'array',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
