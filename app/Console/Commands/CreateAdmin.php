<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AccountRules;
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
                            {--first-name= : First name (asked if omitted)}
                            {--middle-name= : Middle name (optional)}
                            {--last-name= : Surname (asked if omitted)}
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

        $firstName = $this->option('first-name') ?: ($existing?->first_name ?? $this->ask('First name'));
        $middleName = $this->option('middle-name') ?: $existing?->middle_name;
        $lastName = $this->option('last-name') ?: ($existing?->last_name ?? $this->ask('Surname'));
        $password = $this->secret('Password (min. 8 characters, with upper/lower case, a number and a symbol)');

        if ($password !== $this->secret('Confirm password')) {
            $this->error('Passwords do not match.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['email' => $email, 'first_name' => $firstName, 'middle_name' => $middleName, 'last_name' => $lastName, 'password' => $password],
            ['email' => ['required', 'email'], ...AccountRules::name(), 'password' => ['required', Password::defaults()]],
            AccountRules::messages(),
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => $email],
            [
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'password' => $password,
                'role' => $role,
                'is_active' => true,
                // Created by whoever runs the server — no emailed code needed.
                'email_verified_at' => now(),
            ],
        );

        $this->info(($existing ? 'Updated' : 'Created')." {$role} account {$email}.");

        return self::SUCCESS;
    }
}
