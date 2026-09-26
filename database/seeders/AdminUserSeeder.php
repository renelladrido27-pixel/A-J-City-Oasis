<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
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
