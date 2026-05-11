<?php

namespace App\Console\Commands;

use App\Services\InventoryTemplateService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:export-template
    {--output= : Ruta de salida del .xlsx (default: storage/app/plantilla-inventario.xlsx)}')]
#[Description('Genera el .xlsx plantilla oficial para inventory:import-from-xlsx.')]
class InventoryExportTemplate extends Command
{
    public function handle(InventoryTemplateService $service): int
    {
        $output = (string) ($this->option('output') ?: storage_path('app/plantilla-inventario.xlsx'));

        $service->saveTo($output);

        $this->info('Plantilla generada en:');
        $this->line('  '.$output);

        return self::SUCCESS;
    }
}
