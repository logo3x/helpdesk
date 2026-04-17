<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Generates Shield permissions for all Filament resources and assigns
 * them to the support roles. Runs after RoleSeeder so roles exist.
 *
 * Must run AFTER migrations and RoleSeeder but BEFORE demo seeders.
 */
class ShieldPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Clear any cached Filament components so shield:generate picks up
        // resources from every registered panel (admin + soporte).
        Artisan::call('filament:clear-cached-components');
        Artisan::call('optimize:clear');

        // Generate permissions for both panels. We run soporte first because
        // it has the most resources (Tickets, KB, Canned, Templates, Users);
        // during migrate:fresh the admin panel sometimes caches before
        // soporte is discovered, which left us with missing perms.
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'soporte',
            '--option' => 'permissions',
        ]);
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPerms = Permission::pluck('name')->all();

        // Safety-net: if shield:generate missed Ticket perms (race during
        // migrate:fresh), materialize the canonical set manually so
        // supervisors and agents always end up with proper permissions.
        $allPerms = $this->ensureCorePermissions($allPerms);

        // Base de permisos del panel Soporte (Tickets + KB + CannedResponse + TicketTemplate)
        $soportePerms = array_values(array_filter($allPerms, fn ($p) => str_contains($p, 'Ticket')
            || str_contains($p, 'KbArticle')
            || str_contains($p, 'CannedResponse')
            || str_contains($p, 'TicketTemplate')
        ));

        // Agregar permisos de User (el supervisor puede crear agentes
        // para su depto; el scope por depto se aplica en la Resource).
        $userPerms = array_values(array_filter($allPerms, fn ($p) => in_array($p, [
            'ViewAny:User', 'View:User', 'Create:User', 'Update:User',
        ], true)));
        $soportePerms = array_merge($soportePerms, $userPerms);

        // ── supervisor_soporte: acceso total al panel Soporte (53 permisos)
        // Puede eliminar tickets, restaurar, reordenar, hacer force-delete,
        // y ver/editar tickets de cualquier agente. Puede crear agentes
        // para su departamento.
        Role::where('name', 'supervisor_soporte')->first()?->syncPermissions($soportePerms);

        // ── agente_soporte: acceso limitado
        // Puede crear, ver, editar tickets y KB, pero NO puede eliminar
        // ni restaurar ni reordenar. El scope de "qué tickets ve" se
        // aplica en el Resource (solo sus asignados + sin asignar).
        $restrictedForAgente = [
            'Delete', 'DeleteAny', 'ForceDelete', 'ForceDeleteAny',
            'Restore', 'RestoreAny', 'Reorder',
        ];
        $agentePerms = array_values(array_filter($soportePerms, function ($perm) use ($restrictedForAgente) {
            foreach ($restrictedForAgente as $restricted) {
                if (str_starts_with($perm, $restricted.':')) {
                    return false;
                }
            }

            return true;
        }));
        Role::where('name', 'agente_soporte')->first()?->syncPermissions($agentePerms);

        // ── tecnico_campo: mismas restricciones que agente (por ahora)
        Role::where('name', 'tecnico_campo')->first()?->syncPermissions($agentePerms);

        // ── editor_kb: solo permisos de KB Articles
        $kbPerms = array_values(array_filter($allPerms, fn ($p) => str_contains($p, 'KbArticle')));
        Role::where('name', 'editor_kb')->first()?->syncPermissions($kbPerms);
    }

    /**
     * If shield:generate failed to create the core Ticket / KbArticle /
     * CannedResponse / TicketTemplate permissions (race condition during
     * migrate:fresh), create them manually so the seeder stays idempotent.
     *
     * @param  array<int, string>  $existing
     * @return array<int, string>
     */
    protected function ensureCorePermissions(array $existing): array
    {
        $resources = ['Ticket', 'KbArticle', 'CannedResponse', 'TicketTemplate'];
        $actions = [
            'ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny',
            'Restore', 'ForceDelete', 'ForceDeleteAny', 'RestoreAny',
            'Replicate', 'Reorder',
        ];

        $needed = [];
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $needed[] = "{$action}:{$resource}";
            }
        }

        foreach ($needed as $name) {
            if (! in_array($name, $existing, true)) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
                $existing[] = $name;
            }
        }

        return $existing;
    }
}
