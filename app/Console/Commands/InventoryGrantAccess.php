<?php

namespace App\Console\Commands;

use App\Models\Department;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('inventory:grant
    {department : slug, nombre, fragmento o ID del departamento}
    {--revoke : Revoca el acceso en lugar de habilitarlo}')]
#[Description('Habilita o revoca el módulo de inventario para un departamento (can_access_inventory).')]
class InventoryGrantAccess extends Command
{
    public function handle(): int
    {
        $term = (string) $this->argument('department');
        $revoke = (bool) $this->option('revoke');

        $department = $this->findDepartment($term);

        if (! $department) {
            $this->error("No se encontró ningún departamento con slug, nombre o ID que coincida con: {$term}");

            $suggestions = Department::orderBy('name')->pluck('name', 'slug');
            if ($suggestions->isNotEmpty()) {
                $this->line('Departamentos disponibles:');
                foreach ($suggestions as $slug => $name) {
                    $this->line("  · {$name} (slug: {$slug})");
                }
            }

            return self::FAILURE;
        }

        $target = ! $revoke;

        if ((bool) $department->can_access_inventory === $target) {
            $verb = $target ? 'ya tenía' : 'ya no tenía';
            $this->info("Departamento «{$department->name}» {$verb} acceso al inventario. Nada que hacer.");

            return self::SUCCESS;
        }

        $department->forceFill(['can_access_inventory' => $target])->save();

        $verb = $target ? 'habilitado para' : 'revocado a';
        $this->info("✓ Acceso al inventario {$verb} «{$department->name}».");

        return self::SUCCESS;
    }

    protected function findDepartment(string $term): ?Department
    {
        if (ctype_digit($term)) {
            $byId = Department::find((int) $term);
            if ($byId) {
                return $byId;
            }
        }

        return Department::where('slug', $term)
            ->orWhere('name', $term)
            ->orWhere('name', 'like', '%'.$term.'%')
            ->orWhere('slug', 'like', '%'.$term.'%')
            ->first();
    }
}
