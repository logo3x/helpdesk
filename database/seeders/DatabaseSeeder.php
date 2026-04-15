<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DepartmentSeeder::class,
            CategorySeeder::class,
            SlaConfigSeeder::class,
            ChatFlowSeeder::class,
            ShieldPermissionSeeder::class,
            KbArticleSeeder::class,
        ]);

        // Admin bootstrap — password fijo para dev, en producción se cambia
        // desde el panel o con: php artisan tinker --execute "..."
        $admin = User::firstOrCreate(
            ['email' => 'admin@confipetrol.local'],
            [
                'name' => 'Administrador',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ],
        );

        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }

        if (app()->environment('local')) {
            $this->call(TicketDemoSeeder::class);
        }
    }
}
