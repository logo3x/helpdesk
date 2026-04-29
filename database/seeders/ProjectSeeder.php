<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;

/**
 * Proyectos / contratos reales de Confipetrol observados en el inventario
 * actual (Libro1.pdf). El admin puede agregar / editar / desactivar
 * más desde /admin → Configuración → Proyectos.
 */
class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $projects = [
            [
                'code' => '499015105',
                'name' => 'PERENCO CARUPANA',
                'client' => 'Perenco',
            ],
            [
                'code' => '62010905100',
                'name' => 'GRANTIERRA VMM',
                'client' => 'Grantierra',
            ],
            [
                'code' => 'INTERNO',
                'name' => 'Operación interna Confipetrol',
                'client' => null,
            ],
        ];

        foreach ($projects as $data) {
            Project::updateOrCreate(
                ['code' => $data['code']],
                $data + ['is_active' => true],
            );
        }
    }
}
