<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin user
        User::updateOrCreate(
            ['email' => 'hector@strangepixels.co'],
            [
                'name' => 'Hector',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
            ]
        );

        // Sample agent users
        User::firstOrCreate(
            ['email' => 'agent@example.com'],
            [
                'name' => 'Maria Garcia',
                'password' => Hash::make('password'),
                'role' => UserRole::Agent,
            ]
        );

        User::firstOrCreate(
            ['email' => 'agent2@example.com'],
            [
                'name' => 'Carlos Rodriguez',
                'password' => Hash::make('password'),
                'role' => UserRole::Agent,
            ]
        );
    }
}
