<?php

use Illuminate\Support\Facades\File;

/**
 * Evita vistas guardadas con doble codificación (p. ej. "PregÃºntame"
 * en lugar de "Pregúntame") o con BOM al inicio del archivo.
 */
it('ninguna vista tiene texto con codificación dañada ni BOM', function () {
    $offenders = collect(File::allFiles(resource_path('views')))
        ->filter(function (SplFileInfo $file) {
            $content = File::get($file->getPathname());

            return str_starts_with($content, "\xEF\xBB\xBF")
                || preg_match('/Ã[¡©­³º±‰“š‘]|Â¿|Â¡/u', $content) === 1;
        })
        ->map(fn (SplFileInfo $file) => str_replace(resource_path('views').DIRECTORY_SEPARATOR, '', $file->getPathname()))
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});
