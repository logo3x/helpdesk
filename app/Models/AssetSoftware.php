<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetSoftware extends Model
{
    protected $table = 'asset_software';

    protected $fillable = [
        'asset_id',
        'name',
        'version',
        'publisher',
        'install_date',
    ];

    protected function casts(): array
    {
        return [
            'install_date' => 'date',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
