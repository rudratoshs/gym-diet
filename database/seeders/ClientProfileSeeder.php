<?php

// database/seeders/ClientProfileSeeder.php
namespace Database\Seeders;

use App\Models\ClientProfile;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ClientProfileSeeder extends Seeder
{
    public function run()
    {
        // Create gym if not exists
        $gym = Gym::firstOrCreate(
            ['email' => 'info@fitnessfirst.com'],
            [
                'name' => 'Fitness First',
                'address' => '123 Gym Street, Fitness City',
                'phone' => '1234567890',
                'owner_id' => 1, // Ensure this admin user exists
                'subscription_status' => 'active',
                'subscription_expires_at' => now()->addYear(),
                'max_clients' => 200,
            ]
        );

        // Create a client if not exists
        $client = User::firstOrCreate(
            ['email' => 'client@example.com'],
            [
                'name' => 'John Client',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $client->assignRole('client');

        // Attach client to gym if not already attached
        if (!$gym->users()->where('user_id', $client->id)->exists()) {
            $gym->users()->attach($client->id, [
                'role' => 'client',
                'status' => 'active'
            ]);
        }

        // Create or update client profile
        ClientProfile::updateOrCreate(
            ['user_id' => $client->id],
            [
                'age' => 30,
                'gender' => 'male',
                'height' => 180,
                'current_weight' => 85,
                'target_weight' => 75,
                'activity_level' => 'moderately_active',
                'diet_type' => 'omnivore',
                'health_conditions' => ['none'],
                'allergies' => ['none'],
                'recovery_needs' => ['none'],
                'meal_preferences' => ['high_protein', 'low_carb'],
            ]
        );

        // Create a trainer if not exists
        $trainer = User::firstOrCreate(
            ['email' => 'trainer@example.com'],
            [
                'name' => 'Trainer Joe',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        $trainer->assignRole('trainer');

        // Attach trainer to gym if not already attached
        if (!$gym->users()->where('user_id', $trainer->id)->exists()) {
            $gym->users()->attach($trainer->id, [
                'role' => 'trainer',
                'status' => 'active'
            ]);
        }
    }
}