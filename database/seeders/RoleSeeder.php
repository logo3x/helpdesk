<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * The 7 base roles for Helpdesk Confipetrol (from PROYECTO.md §7).
     *
     * @var array<int, array{name: string, description: string}>
     */
    protected array $roles = [
        ['name' => 'super_admin', 'description' => 'Control total del sistema'],
        ['name' => 'admin', 'description' => 'Administración funcional'],
        ['name' => 'supervisor_soporte', 'description' => 'Supervisa grupos de soporte'],
        ['name' => 'agente_soporte', 'description' => 'Atiende tickets de soporte'],
        ['name' => 'tecnico_campo', 'description' => 'Técnico en sitio'],
        ['name' => 'editor_kb', 'description' => 'Gestiona base de conocimiento'],
        ['name' => 'usuario_final', 'description' => 'Crea y consulta sus propios tickets'],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name'], 'guard_name' => 'web'],
            );
        }
    }
}
