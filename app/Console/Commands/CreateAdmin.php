<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Creates (or resets the password of) an admin/staff account on a server.
 * The demo seeder accounts use the public password "password", so production
 * skips them and uses this instead — the password is typed at a hidden
 * prompt, never passed as an argument (it would land in shell history).
 */
class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin
                            {email : Login email for the account}
                            {--name= : Display name (asked if omitted)}
                            {--role=admin : admin or staff}';

    protected $description = 'Create an admin/staff account, or reset its password if it already exists';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $role = $this->option('role');
        $existing = User::where('email', $email)->first();

        if (! in_array($role, ['admin', 'staff'], true)) {
            $this->error('--role must be admin or staff.');

            return self::FAILURE;
        }

        if ($existing && $existing->role === 'tenant') {
            $this->error("{$email} is a tenant account; use a different email for admin/staff.");

            return self::FAILURE;
        }

        $name = $this->option('name') ?: ($existing?->name ?? $this->ask('Full name'));
        $password = $this->secret('Password (min. 8 characters)');

        if ($password !== $this->secret('Confirm password')) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            ['email' => ['required', 'email'], 'name' => ['required', 'string', 'max:255'], 'password' => ['required', Password::min(8)]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password, 'role' => $role, 'is_active' => true],
        );

        $this->info(($existing ? 'Updated' : 'Created')." {$role} account {$email}.");

        return self::SUCCESS;
    }
}
