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
            $this->command?->warn('Skipped the demo admin account in production. Run: php artisan app:create-admin you@example.com');

            return;
        }

        // `name` is set explicitly: DatabaseSeeder runs WithoutModelEvents, so
        // the User model's saving hook that normally combines it doesn't fire.
        User::create([
            'name' => 'Jose Valle',
            'first_name' => 'Jose',
            'last_name' => 'Valle',
            'email' => 'admin@ajoasis.test',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'phone' => null,
            'email_verified_at' => now(),
        ]);
    }
}
