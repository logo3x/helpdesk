<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    /**
     * Base departments. Extend via admin panel once the Department resource exists.
     *
     * `can_access_inventory` controla qué deptos ven el módulo de
     * inventario en el panel /soporte. TI lo tiene por default porque
     * es quien administra los equipos; el resto requiere activación
     * explícita en /admin → Departamentos.
     *
     * @var array<int, array{name: string, slug: string, can_access_inventory: bool}>
     */
    protected array $departments = [
        ['name' => 'Tecnología de la Información', 'slug' => 'ti', 'can_access_inventory' => true],
        ['name' => 'Recursos Humanos', 'slug' => 'rrhh', 'can_access_inventory' => false],
        ['name' => 'Compras', 'slug' => 'compras', 'can_access_inventory' => false],
        ['name' => 'Mantenimiento', 'slug' => 'mantenimiento', 'can_access_inventory' => false],
        ['name' => 'Operaciones', 'slug' => 'operaciones', 'can_access_inventory' => false],
    ];

    public function run(): void
    {
        foreach ($this->departments as $department) {
            DB::table('departments')->updateOrInsert(
                ['slug' => $department['slug']],
                [
                    'name' => $department['name'],
                    'is_active' => true,
                    'can_access_inventory' => $department['can_access_inventory'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
