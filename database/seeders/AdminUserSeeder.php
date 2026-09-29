<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Demo logins for local development only. Their password is public (it's
     * in this repo), so production skips them — create real accounts on the
     * server with `php artisan app:create-admin`.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Skipped demo admin/staff accounts in production. Run: php artisan app:create-admin you@example.com');

            return;
        }

        User::create([
            'name' => 'Jose Valle',
            'email' => 'admin@ajoasis.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => null,
        ]);

        User::create([
            'name' => 'Caretaker Staff',
            'email' => 'staff@ajoasis.test',
            'password' => Hash::make('password'),
            'role' => 'staff',
            'phone' => null,
        ]);
    }
}
