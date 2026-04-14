<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * Base categories per department. Structure: [department_slug => [category names]].
     *
     * @var array<string, array<int, string>>
     */
    protected array $catalog = [
        'ti' => [
            'Hardware',
            'Software',
            'Red e Internet',
            'Correo y Teams',
            'Cuentas y accesos',
            'Impresoras',
            'Telefonía',
        ],
        'rrhh' => [
            'Nómina',
            'Vacaciones y permisos',
            'Contratos',
            'Afiliaciones',
        ],
        'compras' => [
            'Solicitud de compra',
            'Cotizaciones',
            'Proveedores',
        ],
        'mantenimiento' => [
            'Mantenimiento locativo',
            'Equipos',
            'Servicios generales',
        ],
        'operaciones' => [
            'Soporte operativo',
            'Documentación',
        ],
    ];

    public function run(): void
    {
        foreach ($this->catalog as $departmentSlug => $categories) {
            $department = Department::where('slug', $departmentSlug)->first();

            if ($department === null) {
                continue;
            }

            foreach ($categories as $order => $name) {
                Category::updateOrCreate(
                    ['slug' => Str::slug("{$departmentSlug}-{$name}")],
                    [
                        'department_id' => $department->id,
                        'name' => $name,
                        'is_active' => true,
                        'sort_order' => $order,
                    ],
                );
            }
        }
    }
}
