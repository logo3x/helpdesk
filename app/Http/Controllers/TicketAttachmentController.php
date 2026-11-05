<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketComment;
use App\Services\TicketAttachmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sirve los adjuntos de tickets y comentarios con sesión iniciada.
 *
 * - Solo los ve quien puede ver el ticket (TicketPolicy::viewAttachments):
 *   el solicitante dueño o el personal de soporte con acceso al ticket.
 * - Los adjuntos de notas internas no los ve el solicitante.
 * - Lee del disco que tenga cada archivo: los nuevos están en "local"
 *   (privado) y los antiguos pueden seguir en "public".
 * - Imágenes, PDF y texto se muestran en línea (para el visor modal);
 *   el resto siempre se descarga. Con nosniff y CSP para que un archivo
 *   subido no pueda ejecutar código en el Helpdesk.
 */
class TicketAttachmentController extends Controller
{
    public function show(Request $request, Media $media): StreamedResponse
    {
        $this->authorizeAccess($request, $media);

        $contentType = TicketAttachmentService::inlineContentType($media);

        if ($contentType === null) {
            return $this->respond($media, 'attachment', 'application/octet-stream');
        }

        return $this->respond($media, 'inline', $contentType);
    }

    public function download(Request $request, Media $media): StreamedResponse
    {
        $this->authorizeAccess($request, $media);

        return $this->respond($media, 'attachment', 'application/octet-stream');
    }

    protected function authorizeAccess(Request $request, Media $media): void
    {
        abort_unless(TicketAttachmentService::isTicketAttachment($media), 404);

        $owner = $media->model;
        $ticket = match (true) {
            $owner instanceof Ticket => $owner,
            $owner instanceof TicketComment => $owner->ticket,
            default => null,
        };

        abort_if($ticket === null, 404);

        $user = $request->user();

        abort_unless(Gate::forUser($user)->allows('viewAttachments', $ticket), 403, 'No tienes permiso para ver este adjunto.');

        // Adjunto de una nota interna: solo para el personal de soporte.
        if ($owner instanceof TicketComment && $owner->is_private) {
            abort_unless(Gate::forUser($user)->allows('view', $ticket), 404);
        }
    }

    protected function respond(Media $media, string $disposition, string $contentType): StreamedResponse
    {
        $disk = Storage::disk($media->disk);
        $path = $media->getPathRelativeToRoot();

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $media->file_name, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => $this->contentSecurityPolicy($media, $disposition),
            'Cache-Control' => 'private, max-age=600',
        ], $disposition);
    }

    /**
     * El PDF no lleva `sandbox`: Chrome se niega a mostrar un PDF en un
     * documento aislado. Su visor ya ejecuta el archivo aparte.
     */
    protected function contentSecurityPolicy(Media $media, string $disposition): string
    {
        if ($disposition === 'inline' && TicketAttachmentService::kind($media) === 'pdf') {
            return "frame-ancestors 'self'";
        }

        return "sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; frame-ancestors 'self'";
    }
}
