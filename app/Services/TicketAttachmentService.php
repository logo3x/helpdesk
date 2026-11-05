<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketComment;
use Illuminate\Support\Number;
use Spatie\MediaLibrary\MediaCollections\File;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Reglas comunes de los adjuntos de tickets y comentarios.
 *
 * - Qué tipos se pueden subir (portal y paneles).
 * - Qué tipos nunca se aceptan aunque el formulario los deje pasar
 *   (HTML, SVG, JavaScript, ejecutables): se pueden ejecutar en el
 *   navegador o en el equipo de quien los abre.
 * - Cómo se sirve cada tipo (en línea o como descarga) y qué datos
 *   recibe el visor modal.
 */
class TicketAttachmentService
{
    /** Disco de los adjuntos nuevos: privado, solo se sirve por la ruta autenticada. */
    public const DISK = 'local';

    public const COLLECTION = 'attachments';

    /** Extensiones permitidas al subir (regla `mimes` de Laravel). */
    public const ALLOWED_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp',
        'pdf',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'txt', 'csv', 'log',
        'zip', 'rar',
    ];

    /** Tipos MIME permitidos en los FileUpload de Filament. */
    public const ACCEPTED_MIME_TYPES = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv',
        'application/zip', 'application/x-zip-compressed',
        'application/x-rar-compressed', 'application/vnd.rar',
    ];

    /** Extensiones que nunca se aceptan, aunque el MIME detectado parezca inofensivo. */
    public const BLOCKED_EXTENSIONS = [
        'html', 'htm', 'xhtml', 'shtml', 'svg', 'svgz', 'xml', 'xsl',
        'js', 'mjs', 'vbs', 'vbe', 'wsf', 'hta', 'ps1', 'bat', 'cmd', 'sh',
        'exe', 'msi', 'com', 'scr', 'dll', 'jar', 'php', 'phtml', 'phar',
    ];

    /** MIME detectados por contenido que nunca se aceptan. */
    public const BLOCKED_MIME_TYPES = [
        'text/html', 'application/xhtml+xml', 'image/svg+xml', 'text/xml', 'application/xml',
        'application/javascript', 'text/javascript', 'application/x-javascript',
        'application/x-dosexec', 'application/x-msdownload', 'application/x-msdos-program',
        'application/x-executable', 'application/x-sharedlib', 'application/x-sh',
        'application/x-php', 'text/x-php', 'application/java-archive',
    ];

    /** Imágenes que el navegador puede mostrar sin riesgo (sin SVG). */
    public const INLINE_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];

    /** Textos que se muestran en el visor como texto plano. */
    public const INLINE_TEXT_TYPES = ['text/plain', 'text/csv', 'application/csv'];

    /**
     * Regla de validación para cada archivo subido (portal).
     *
     * @return array<int, string>
     */
    public static function uploadRules(): array
    {
        $extensions = implode(',', self::ALLOWED_EXTENSIONS);

        // `mimes` revisa el contenido real y `extensions` el nombre del
        // archivo: así no pasa un .js o .html disfrazado de texto plano.
        return ['file', 'max:10240', 'mimes:'.$extensions, 'extensions:'.$extensions];
    }

    /**
     * Filtro de la colección de medialibrary: última barrera para
     * cualquier formulario que suba adjuntos.
     */
    public static function isAcceptable(File $file): bool
    {
        $extension = strtolower(pathinfo($file->name, PATHINFO_EXTENSION));

        return ! in_array($extension, self::BLOCKED_EXTENSIONS, true)
            && ! in_array(strtolower($file->mimeType), self::BLOCKED_MIME_TYPES, true);
    }

    /**
     * ¿Es un adjunto de ticket o de comentario?
     */
    public static function isTicketAttachment(Media $media): bool
    {
        return $media->collection_name === self::COLLECTION
            && in_array($media->model_type, [Ticket::class, TicketComment::class], true);
    }

    /**
     * Tipo de vista previa: image, pdf, text u other.
     */
    public static function kind(Media $media): string
    {
        $mime = strtolower((string) $media->mime_type);

        return match (true) {
            in_array($mime, self::INLINE_IMAGE_TYPES, true) => 'image',
            $mime === 'application/pdf' => 'pdf',
            in_array($mime, self::INLINE_TEXT_TYPES, true) => 'text',
            default => 'other',
        };
    }

    /**
     * Content-Type con el que se sirve el archivo en línea, o null si
     * solo se puede descargar.
     */
    public static function inlineContentType(Media $media): ?string
    {
        return match (self::kind($media)) {
            'image', 'pdf' => strtolower((string) $media->mime_type),
            'text' => 'text/plain; charset=UTF-8',
            default => null,
        };
    }

    public static function icon(Media $media): string
    {
        $extension = strtolower(pathinfo((string) $media->file_name, PATHINFO_EXTENSION));

        return match (true) {
            self::kind($media) === 'image' => '🖼️',
            $extension === 'pdf' => '📕',
            in_array($extension, ['doc', 'docx'], true) => '📘',
            in_array($extension, ['xls', 'xlsx', 'csv'], true) => '📗',
            in_array($extension, ['ppt', 'pptx'], true) => '📙',
            in_array($extension, ['txt', 'log'], true) => '📄',
            in_array($extension, ['zip', 'rar', '7z'], true) => '🗜️',
            default => '📎',
        };
    }

    /**
     * Datos que recibe el visor modal (componente x-attachment-viewer).
     *
     * @param  iterable<Media>  $media
     * @return array<int, array{id: int, name: string, kind: string, icon: string, size: string, url: string, download: string}>
     */
    public static function viewerItems(iterable $media): array
    {
        $items = [];

        foreach ($media as $file) {
            $items[] = [
                'id' => (int) $file->id,
                'name' => (string) $file->file_name,
                'kind' => self::kind($file),
                'icon' => self::icon($file),
                'size' => Number::fileSize((int) $file->size),
                'url' => route('tickets.attachments.show', $file),
                'download' => route('tickets.attachments.download', $file),
            ];
        }

        return $items;
    }
}
