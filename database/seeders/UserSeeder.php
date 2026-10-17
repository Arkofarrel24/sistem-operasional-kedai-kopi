<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo accounts (temporary; the full demo dataset comes in phase 5).
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Dimas Pratama', 'email' => 'owner@demo.test', 'role' => Role::Owner],
            ['name' => 'Ayu Lestari', 'email' => 'kasir1@demo.test', 'role' => Role::Cashier],
            ['name' => 'Budi Santoso', 'email' => 'kasir2@demo.test', 'role' => Role::Cashier],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'password' => 'password',
                ],
            );
        }
    }
}
