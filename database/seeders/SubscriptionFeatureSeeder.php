<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SubscriptionFeatureSeeder extends Seeder
{
    public function run()
    {
        $features = [
            ['name' => 'Max Clients', 'code' => 'max_clients', 'description' => 'Maximum number of clients allowed', 'type' => 'numeric'],
            ['name' => 'Max Diet Plans', 'code' => 'max_diet_plans', 'description' => 'Maximum number of diet plans allowed', 'type' => 'numeric'],
            ['name' => 'AI Meal Generation', 'code' => 'ai_meal_generation', 'description' => 'Enable AI-powered meal generation', 'type' => 'boolean'],
            ['name' => 'AI Meal Generation Limit', 'code' => 'ai_meal_generation_limit', 'description' => 'Limit for AI meal generations', 'type' => 'numeric'],
            ['name' => 'WhatsApp Integration', 'code' => 'whatsapp_integration', 'description' => 'Enable WhatsApp notifications & support', 'type' => 'boolean'],
            ['name' => 'Custom Branding', 'code' => 'custom_branding', 'description' => 'Allow custom branding for users', 'type' => 'boolean'],
            ['name' => 'Analytics', 'code' => 'analytics', 'description' => 'Access to advanced analytics', 'type' => 'boolean'],
        ];

        foreach ($features as $feature) {
            DB::table('subscription_features')->updateOrInsert(
                ['code' => $feature['code']], // Unique key to prevent duplicates
                array_merge($feature, [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ])
            );
        }
    }
}