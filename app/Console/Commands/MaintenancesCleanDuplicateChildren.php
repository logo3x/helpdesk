<?php

namespace App\Console\Commands;

use App\Enums\MaintenanceStatus;
use App\Models\ScheduledMaintenance;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Limpia duplicados de "siguiente ocurrencia" que se generaron por el
 * bug del observer previo (donde re-editar un mtto cerrado disparaba
 * la creación de nuevo hijo aunque ya hubiera uno).
 *
 * Estrategia:
 *   1. Detecta padres con más de 1 hijo pendiente.
 *   2. Se queda con el hijo MÁS ANTIGUO (menor id) que esté pendiente.
 *   3. Force-delete de los demás hijos pendientes que no tengan a su
 *      vez descendientes (no rompemos cadenas ya construidas).
 *
 * Ejecución idempotente: correr varias veces solo cambia algo la
 * primera vez.
 */
#[Signature('maintenances:clean-duplicate-children {--dry-run : Solo listar sin borrar}')]
#[Description('Limpia mtto hijo duplicados del bug del observer previo')]
class MaintenancesCleanDuplicateChildren extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Group by parent_id where count > 1 (excluye parent_id NULL)
        $groups = ScheduledMaintenance::query()
            ->select('parent_id')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('parent_id')
            ->where('status', MaintenanceStatus::Pendiente->value)
            ->groupBy('parent_id')
            ->having('total', '>', 1)
            ->get();

        if ($groups->isEmpty()) {
            $this->info('No hay padres con hijos duplicados pendientes. Nada que hacer.');

            return self::SUCCESS;
        }

        $this->info("Padres con hijos duplicados pendientes: {$groups->count()}");

        $deletedTotal = 0;
        foreach ($groups as $g) {
            $children = ScheduledMaintenance::query()
                ->where('parent_id', $g->parent_id)
                ->where('status', MaintenanceStatus::Pendiente->value)
                ->orderBy('id')
                ->get();

            $keep = $children->first();
            $toDelete = $children->slice(1);

            $this->line("  Padre #{$g->parent_id}: mantengo #{$keep->id}, borro ".$toDelete->pluck('id')->implode(', '));

            foreach ($toDelete as $child) {
                // Solo borro si no tiene hijos propios (no romper cadena).
                $hasGrandchildren = ScheduledMaintenance::where('parent_id', $child->id)->exists();
                if ($hasGrandchildren) {
                    $this->warn("    · #{$child->id} tiene descendientes — saltado (revisar manual).");

                    continue;
                }

                if (! $dryRun) {
                    $child->forceDelete();
                }
                $deletedTotal++;
            }
        }

        if ($dryRun) {
            $this->info("DRY-RUN: se borrarían {$deletedTotal} registros. Corré sin --dry-run para aplicar.");
        } else {
            $this->info("OK — {$deletedTotal} hijos duplicados eliminados.");
        }

        return self::SUCCESS;
    }
}
