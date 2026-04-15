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
        // Generate permissions for both panels
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'admin',
            '--option' => 'permissions',
        ]);
        Artisan::call('shield:generate', [
            '--all' => true,
            '--panel' => 'soporte',
            '--option' => 'permissions',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPerms = Permission::pluck('name')->all();

        // Soporte roles get Ticket, KbArticle, TicketTemplate, CannedResponse permissions
        $soportePerms = array_values(array_filter($allPerms, fn ($p) => str_contains($p, 'Ticket')
            || str_contains($p, 'KbArticle')
            || str_contains($p, 'CannedResponse')
            || str_contains($p, 'TicketTemplate')
        ));

        foreach (['supervisor_soporte', 'agente_soporte', 'tecnico_campo'] as $roleName) {
            Role::where('name', $roleName)->first()?->syncPermissions($soportePerms);
        }

        // editor_kb only gets KB article permissions
        $kbPerms = array_values(array_filter($allPerms, fn ($p) => str_contains($p, 'KbArticle')));
        Role::where('name', 'editor_kb')->first()?->syncPermissions($kbPerms);
    }
}
