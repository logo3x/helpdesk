<?php

namespace App\Filament\Concerns;

use Illuminate\Support\HtmlString;

/**
 * Etiquetas con un ícono (i) que muestra una ayuda al pasar el cursor.
 * Sirve para encabezados de columnas y títulos de Stats, que aceptan
 * Htmlable como label.
 */
trait HasHelpLabels
{
    public static function helpLabel(string $label, string $help): HtmlString
    {
        $icon = svg('heroicon-o-information-circle', '', [
            'style' => 'display:inline-block;width:0.95rem;height:0.95rem;vertical-align:-0.15rem;margin-left:0.2rem;opacity:0.6;cursor:help;',
            'x-tooltip.raw' => $help,
            'aria-label' => $help,
        ])->toHtml();

        return new HtmlString(e($label).$icon);
    }
}
