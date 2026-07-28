<?php

namespace App\Console\Commands;

use App\Models\Asset;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class RegenerateAcceptedHandoverPdfs extends Command
{
    protected $signature = 'assets:regenerate-accepted-pdfs {--dry-run : Mostrar activos afectados sin generar PDFs}';

    protected $description = 'Regenera el PDF con sello de aceptación para activos aceptados vía portal que no tienen accepted_pdf_path';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $assets = Asset::query()
            ->whereNotNull('accepted_at')
            ->whereDoesntHave('handovers', fn ($q) => $q->whereNotNull('accepted_pdf_path'))
            ->with(['handovers' => fn ($q) => $q->latest('delivered_at')])
            ->get();

        if ($assets->isEmpty()) {
            $this->info('No hay activos pendientes de regeneración.');

            return self::SUCCESS;
        }

        $this->info("Activos a procesar: {$assets->count()}");

        foreach ($assets as $asset) {
            $handover = $asset->handovers->first();

            if (! $handover) {
                $this->warn("  Activo #{$asset->id} ({$asset->hostname}): sin handover asociado, omitido.");

                continue;
            }

            if ($dryRun) {
                $this->line("  [dry-run] Activo #{$asset->id} ({$asset->hostname}) — Acta #{$handover->acta_number}");

                continue;
            }

            $handover->load(['receivedBy', 'deliveredBy', 'project']);

            $pdf = Pdf::loadView('pdfs.asset-handover', [
                'handover' => $handover,
                'acceptedAt' => $asset->accepted_at->toDateTimeString(),
            ])->setPaper('letter', 'portrait');

            $path = sprintf(
                'actas/%d_acta_%s_aceptada.pdf',
                $handover->acta_number,
                preg_replace('/[^A-Za-z0-9_-]/', '_', strtoupper($handover->receivedBy?->name ?? 'custodio')),
            );

            Storage::disk('local')->put($path, $pdf->output());
            $handover->forceFill(['accepted_pdf_path' => $path])->save();

            $this->info("  Activo #{$asset->id} ({$asset->hostname}) — Acta #{$handover->acta_number} → {$path}");
        }

        if (! $dryRun) {
            $this->info('Regeneración completada.');
        }

        return self::SUCCESS;
    }
}
