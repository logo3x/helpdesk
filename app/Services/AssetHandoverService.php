<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetHandover;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * Genera actas de entrega de equipos IT (formato IT-ADM1-F-5 v3).
 *
 * Crea el registro en `asset_handovers` con snapshot de los datos al
 * momento de la entrega, genera el PDF con dompdf desde el template
 * Blade y opcionalmente lo guarda en disco para descarga posterior.
 */
class AssetHandoverService
{
    /**
     * Crea un acta de entrega + PDF y devuelve el modelo persistido.
     *
     * Si `$asset->user_id` no coincide con `$receivedBy`, también
     * actualiza la asignación del activo (es la entrega real).
     *
     * @param  array<string, mixed>  $extra  Datos extra para el acta:
     *                                       reference, condition_at_delivery,
     *                                       observations.
     */
    public function generate(
        Asset $asset,
        User $receivedBy,
        ?User $deliveredBy = null,
        array $extra = [],
    ): AssetHandover {
        $deliveredBy ??= auth()->user() instanceof User ? auth()->user() : null;

        // Snapshot de datos del activo al momento de entrega.
        $handover = AssetHandover::create([
            'acta_number' => AssetHandover::nextActaNumber(),
            'asset_id' => $asset->id,
            'delivered_by_user_id' => $deliveredBy?->id,
            'received_by_user_id' => $receivedBy->id,
            'delivered_at' => now(),

            'asset_tag_snapshot' => $asset->asset_tag,
            'asset_type_snapshot' => $asset->type,
            'manufacturer_snapshot' => $asset->manufacturer,
            'model_snapshot' => $asset->model,
            'serial_snapshot' => $asset->serial_number,
            'sap_code_snapshot' => $asset->sap_code,
            'field_snapshot' => $asset->field,
            'project_id_snapshot' => $asset->project_id,

            'condition_at_delivery' => $extra['condition_at_delivery'] ?? 'bueno',
            'reference' => $extra['reference'] ?? null,
            'observations' => $extra['observations'] ?? null,
            'template_version' => 'IT-ADM1-F-5_v3',
        ]);

        // Si el receptor cambió, actualizar la asignación del activo.
        if ($asset->user_id !== $receivedBy->id) {
            $asset->forceFill([
                'user_id' => $receivedBy->id,
                'department_id' => $receivedBy->department_id ?? $asset->department_id,
            ])->save();
        }

        // Renderizar y guardar el PDF.
        $handover->load(['receivedBy', 'deliveredBy', 'project']);
        $pdf = Pdf::loadView('pdfs.asset-handover', ['handover' => $handover])
            ->setPaper('letter', 'portrait');

        $filename = sprintf(
            'actas/%d_acta_%s.pdf',
            $handover->acta_number,
            preg_replace('/[^A-Za-z0-9_-]/', '_', strtoupper($receivedBy->name ?? 'desconocido')),
        );

        Storage::disk('local')->put($filename, $pdf->output());

        $handover->forceFill(['pdf_path' => $filename])->save();

        return $handover;
    }
}
