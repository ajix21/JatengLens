<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name'      => 'Super Admin',
                'email'     => 'admin@sociowatch.id',
                'password'  => Hash::make('admin123'),
                'role'      => 'superadmin',
                'is_active' => true,
            ],
            [
                'name'      => 'Operator',
                'email'     => 'operator@sociowatch.id',
                'password'  => Hash::make('operator123'),
                'role'      => 'admin',
                'is_active' => true,
            ],
            [
                'name'      => 'Viewer',
                'email'     => 'viewer@sociowatch.id',
                'password'  => Hash::make('viewer123'),
                'role'      => 'viewer',
                'is_active' => true,
            ],
        ];

        foreach ($users as $userData) {
            User::firstOrCreate(['email' => $userData['email']], $userData);
        }
    }
}
