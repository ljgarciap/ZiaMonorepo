<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'superadmin@zia.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'role' => 'superadmin'
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@zia.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin'
            ]
        );

        User::firstOrCreate(
            ['email' => 'user@zia.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('password'),
                'role' => 'user'
            ]
        );
    }
}
