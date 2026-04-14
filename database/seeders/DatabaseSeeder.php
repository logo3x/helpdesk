<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            DepartmentSeeder::class,
            CategorySeeder::class,
        ]);

        $this->seedBootstrapAdmin();
    }

    /**
     * Creates the bootstrap super_admin used during development and initial
     * deployment. Credentials come from env vars so they are never committed.
     *
     * If SEED_ADMIN_PASSWORD is missing, a random password is generated and
     * printed to the console — copy it from there, it is not stored anywhere.
     */
    protected function seedBootstrapAdmin(): void
    {
        $email = config('helpdesk.seed_admin.email');
        $name = config('helpdesk.seed_admin.name');
        $password = config('helpdesk.seed_admin.password');
        $generated = false;

        if (blank($password)) {
            $password = Str::password(16, symbols: false);
            $generated = true;
        }

        $admin = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ],
        );

        if (! $admin->hasRole('super_admin')) {
            $admin->assignRole('super_admin');
        }

        if ($generated && $admin->wasRecentlyCreated) {
            $this->command->warn("=> Bootstrap admin created: {$email}");
            $this->command->warn("=> Generated password: {$password}");
            $this->command->warn('=> Save this password now — it will not be shown again. Set SEED_ADMIN_PASSWORD in .env to skip this.');
        }
    }
}
