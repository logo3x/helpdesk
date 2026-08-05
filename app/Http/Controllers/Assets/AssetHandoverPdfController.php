<?php

namespace App\Http\Controllers\Assets;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetHandover;
use Barryvdh\DomPDF\Facade\Pdf;
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

        // Buscar primero el PDF ya generado con sello de aceptación.
        $handover = $asset->handovers()
            ->whereNotNull('accepted_pdf_path')
            ->latest('delivered_at')
            ->first();

        if ($handover && Storage::disk('local')->exists($handover->accepted_pdf_path)) {
            return $this->stream($handover, $handover->accepted_pdf_path);
        }

        // Fallback: si aceptó sin handover previo (registro manual), generar el PDF ahora.
        $handover = $asset->handovers()->latest('delivered_at')->first();

        if ($handover) {
            $handover->load(['receivedBy', 'deliveredBy', 'project']);
            $path = $this->buildAcceptedPdf($handover, $asset->accepted_at->toDateTimeString());
            $handover->forceFill(['accepted_pdf_path' => $path])->save();

            return $this->stream($handover, $path);
        }

        // No hay ningún handover: generar el acta oficial usando los datos del asset.
        $asset->load(['user', 'department', 'project']);
        $pdfContent = Pdf::loadView('pdfs.asset-handover-no-handover', [
            'asset'      => $asset,
            'acceptedAt' => $asset->accepted_at->toDateTimeString(),
        ])->setPaper('letter', 'portrait')->output();

        $filename = "acta_{$asset->id}_aceptada.pdf";

        return response()->streamDownload(
            fn () => print ($pdfContent),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    private function buildAcceptedPdf(AssetHandover $handover, string $acceptedAt): string
    {
        $pdf = Pdf::loadView('pdfs.asset-handover', [
            'handover' => $handover,
            'acceptedAt' => $acceptedAt,
        ])->setPaper('letter', 'portrait');

        $path = sprintf(
            'actas/%d_acta_%s_aceptada.pdf',
            $handover->acta_number,
            preg_replace('/[^A-Za-z0-9_-]/', '_', strtoupper($handover->receivedBy?->name ?? 'custodio')),
        );

        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    private function stream(AssetHandover $handover, string $path): StreamedResponse
    {
        $filename = "acta_{$handover->acta_number}_aceptada.pdf";

        return response()->streamDownload(
            fn () => print (Storage::disk('local')->get($path)),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
