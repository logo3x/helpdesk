<?php

namespace App\Console\Commands;

use App\Services\InventoryImportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:import-from-xlsx
    {file : Ruta absoluta al archivo .xlsx con el inventario}
    {--dry-run : Simula el import sin persistir cambios}
    {--fail-on-errors : Devuelve exit 1 si hay filas con errores}')]
#[Description('Carga masiva de activos desde un .xlsx con la estructura del inventario Confipetrol.')]
class InventoryImportFromXlsx extends Command
{
    public function handle(InventoryImportService $service): int
    {
        $file = (string) $this->argument('file');
        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($file)) {
            $this->error("No se encontró el archivo: {$file}");

            return self::FAILURE;
        }

        $this->info('Leyendo '.$file);

        if ($dryRun) {
            $this->warn('Modo DRY-RUN: nada se guardará en la BD.');
        }

        $report = $service->importFromFile($file, $dryRun);

        $this->newLine();
        $this->line('<options=bold>Resumen del import</>');
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Total filas', (string) $report['total']],
                ['Creadas', (string) $report['created']],
                ['Actualizadas', (string) $report['updated']],
                ['Saltadas (vacías)', (string) $report['skipped']],
                ['Con error', (string) count($report['errors'])],
                ['Proyectos creados', (string) $report['entities_created']['projects']],
                ['Usuarios creados', (string) $report['entities_created']['users']],
                ['Departamentos creados', (string) $report['entities_created']['departments']],
            ],
        );

        if ($report['errors'] !== []) {
            $this->newLine();
            $this->line('<fg=red;options=bold>Filas con error:</>');

            $this->table(
                ['Fila', 'TAG', 'Mensaje'],
                array_map(
                    fn (array $err) => [(string) $err['row'], $err['tag'] ?? '—', $err['message']],
                    $report['errors'],
                ),
            );
        }

        if ($this->option('fail-on-errors') && $report['errors'] !== []) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
