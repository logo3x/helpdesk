<?php

namespace App\Filament\Concerns;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Js;

/**
 * Etiquetas con un "?" que muestra una ayuda al pasar el cursor (o al
 * enfocarlo con el teclado). Es el único estilo de ayuda de los paneles
 * /admin y /soporte: encabezados de columnas, títulos de Stats y labels
 * de entradas de infolist, que aceptan Htmlable como label.
 *
 * Usa `x-tooltip` (Tippy.js, incluido en Filament) en lugar de `title`:
 * aparece al instante, respeta el modo oscuro y se ve igual en todos los
 * paneles. Las clases Tailwind las compila el tema compartido
 * (resources/css/filament/admin/theme.css), que escanea app/Filament.
 */
trait HasHelpLabels
{
    public static function helpLabel(string $label, string $help): HtmlString
    {
        $tooltip = '{ content: '.Js::from($help).", theme: \$store.theme, placement: 'top', maxWidth: 320 }";

        return new HtmlString(
            '<span class="fi-help-label inline-flex items-center gap-1.5">'
            .e($label)
            .'<span x-data x-tooltip="'.e($tooltip).'" role="img" tabindex="0" aria-label="'.e($help).'" '
            .'class="fi-help-icon inline-flex size-4 shrink-0 cursor-help items-center justify-center rounded-full '
            .'bg-gray-200 text-[0.65rem] font-bold leading-none text-gray-600 transition '
            .'hover:bg-gray-700 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 '
            .'dark:bg-white/10 dark:text-gray-300 dark:hover:bg-white/30 dark:hover:text-white">?</span>'
            .'</span>'
        );
    }
}
