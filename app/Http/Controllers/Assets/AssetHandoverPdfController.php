<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetHandoverPdfController extends Controller
{
    public function __invoke(Asset $asset): StreamedResponse|Response
    {
        // Solo el custodio asignado puede descargar su propia acta.
        if ((int) $asset->user_id !== (int) auth()->id()) {
            abort(403);
        }

        if (! $asset->accepted_at) {
            abort(404, 'El activo aún no ha sido aceptado.');
        }

        $handover = $asset->handovers()
            ->whereNotNull('accepted_pdf_path')
            ->latest('delivered_at')
            ->first();

        if (! $handover || ! Storage::disk('local')->exists($handover->accepted_pdf_path)) {
            abort(404, 'El PDF del acta no está disponible. Contacta al equipo de IT.');
        }

        $filename = "acta_{$handover->acta_number}_aceptada.pdf";

        return response()->streamDownload(
            function () use ($handover) {
                echo Storage::disk('local')->get($handover->accepted_pdf_path);
            },
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
