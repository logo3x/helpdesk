<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Acta de entrega de un activo IT (formato IT-ADM1-F-5).
 *
 * Las columnas `*_snapshot` son fotos del estado del activo al momento
 * de generar el acta — no cambian si el activo se modifica después.
 * Esto preserva la fidelidad documental: si el modelo cambia "Latitude
 * 5440" por "Latitude 5450", el acta histórica sigue mostrando 5440.
 */
class AssetHandover extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'acta_number',
        'asset_id',
        'delivered_by_user_id',
        'received_by_user_id',
        'delivered_at',
        'asset_tag_snapshot',
        'asset_type_snapshot',
        'manufacturer_snapshot',
        'model_snapshot',
        'serial_snapshot',
        'sap_code_snapshot',
        'field_snapshot',
        'project_id_snapshot',
        'condition_at_delivery',
        'reference',
        'observations',
        'template_version',
        'pdf_path',
        'signed_pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'acta_number' => 'integer',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<User, $this> */
    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_user_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id_snapshot');
    }

    /**
     * Genera atómicamente el siguiente número de acta. Usa lockForUpdate
     * para que dos requests concurrentes no obtengan el mismo número.
     */
    public static function nextActaNumber(): int
    {
        return DB::transaction(function () {
            $last = static::query()
                ->lockForUpdate()
                ->max('acta_number') ?? 0;

            return $last + 1;
        });
    }
}
