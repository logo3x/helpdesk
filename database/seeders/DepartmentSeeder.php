<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    /**
     * Base departments. Extend via admin panel once the Department resource exists.
     *
     * @var array<int, array{name: string, slug: string}>
     */
    protected array $departments = [
        ['name' => 'Tecnología de la Información', 'slug' => 'ti'],
        ['name' => 'Recursos Humanos', 'slug' => 'rrhh'],
        ['name' => 'Compras', 'slug' => 'compras'],
        ['name' => 'Mantenimiento', 'slug' => 'mantenimiento'],
        ['name' => 'Operaciones', 'slug' => 'operaciones'],
    ];

    public function run(): void
    {
        foreach ($this->departments as $department) {
            DB::table('departments')->updateOrInsert(
                ['slug' => $department['slug']],
                [
                    'name' => $department['name'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
