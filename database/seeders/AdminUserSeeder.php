<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run()
    {
        // Ensure the admin user exists and update if necessary
        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'], // Unique identifier
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'), // Change this in production!
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // Assign role only if the admin does not already have it
        if (!$admin->hasRole('admin')) {
            $admin->assignRole('admin');
        }
    }
}