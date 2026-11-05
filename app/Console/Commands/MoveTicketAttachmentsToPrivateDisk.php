<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\TicketAttachmentService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Mueve los adjuntos antiguos de tickets del disco "public" (accesibles
 * por /storage sin sesión) al disco privado. No se ejecuta solo: lo
 * corre un administrador cuando quiera, primero con --dry-run.
 */
class MoveTicketAttachmentsToPrivateDisk extends Command
{
    protected $signature = 'tickets:move-attachments-private
        {--dry-run : Solo muestra qué archivos se moverían}';

    protected $description = 'Mueve los adjuntos de tickets y comentarios del disco público al disco privado';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $target = TicketAttachmentService::DISK;
        $moved = 0;
        $missing = 0;

        Media::query()
            ->whereIn('model_type', [Ticket::class, TicketComment::class])
            ->where('collection_name', TicketAttachmentService::COLLECTION)
            ->where('disk', '!=', $target)
            ->orderBy('id')
            ->each(function (Media $media) use ($dryRun, $target, &$moved, &$missing): void {
                $source = Storage::disk($media->disk);
                $directory = dirname($media->getPathRelativeToRoot());

                if (! $source->exists($media->getPathRelativeToRoot())) {
                    $this->warn("Adjunto #{$media->id} ({$media->file_name}): no se encontró el archivo, se omite.");
                    $missing++;

                    return;
                }

                $this->line(($dryRun ? '[simulación] ' : '')."#{$media->id} {$media->file_name}: {$media->disk} → {$target}");

                if (! $dryRun) {
                    $destination = Storage::disk($target);

                    // Copia el archivo y sus conversiones (si las hubiera).
                    foreach ($source->allFiles($directory) as $file) {
                        $destination->writeStream($file, $source->readStream($file));
                    }

                    $media->disk = $target;
                    $media->conversions_disk = $target;
                    $media->saveQuietly();

                    $source->deleteDirectory($directory);
                }

                $moved++;
            });

        $this->info($dryRun
            ? "Se moverían {$moved} adjunto(s). Faltan {$missing} archivo(s)."
            : "Se movieron {$moved} adjunto(s) al disco privado. Faltan {$missing} archivo(s).");

        return self::SUCCESS;
    }
}
