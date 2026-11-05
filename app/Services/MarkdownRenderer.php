<?php

namespace App\Services;

use Illuminate\Support\HtmlString;

/**
 * Markdown seguro para textos que escriben usuarios, agentes o la IA
 * (descripciones, comentarios, artículos de la base de conocimiento).
 *
 * El HTML incrustado se muestra como texto y los enlaces peligrosos
 * (javascript:, data:…) se descartan, para evitar XSS almacenado.
 */
class MarkdownRenderer
{
    public static function safe(?string $text): HtmlString
    {
        return str((string) $text)->markdown([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ])->toHtmlString();
    }
}
