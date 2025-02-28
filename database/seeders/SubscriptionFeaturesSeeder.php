<?php

namespace Database\Seeders;

use App\Models\SubscriptionFeature;
use Illuminate\Database\Seeder;

class SubscriptionFeaturesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $features = [
            [
                'name' => 'Maximum Clients',
                'code' => 'max_clients',
                'description' => 'Maximum number of clients allowed for the gym',
                'type' => 'numeric'
            ],
            [
                'name' => 'Maximum Diet Plans Per Client',
                'code' => 'max_diet_plans',
                'description' => 'Maximum number of diet plans that can be created per client',
                'type' => 'numeric'
            ],
            [
                'name' => 'AI Meal Generation',
                'code' => 'ai_meal_generation',
                'description' => 'Ability to generate meal plans using AI',
                'type' => 'boolean'
            ],
            [
                'name' => 'WhatsApp Integration',
                'code' => 'whatsapp_integration',
                'description' => 'Ability to send messages and notifications via WhatsApp',
                'type' => 'boolean'
            ],
            [
                'name' => 'Custom Branding',
                'code' => 'custom_branding',
                'description' => 'Ability to customize branding elements like logos and colors',
                'type' => 'boolean'
            ],
            [
                'name' => 'Analytics',
                'code' => 'analytics',
                'description' => 'Access to advanced analytics and reporting',
                'type' => 'boolean'
            ],
            [
                'name' => 'Priority Support',
                'code' => 'priority_support',
                'description' => 'Access to priority customer support',
                'type' => 'boolean'
            ],
            [
                'name' => 'White Label',
                'code' => 'white_label',
                'description' => 'Remove all platform branding for a complete white label experience',
                'type' => 'boolean'
            ],
            [
                'name' => 'Client Subscription Management',
                'code' => 'client_subscription_management',
                'description' => 'Ability to create and manage client subscriptions',
                'type' => 'boolean'
            ],
            [
                'name' => 'Bulk Client Import',
                'code' => 'bulk_client_import',
                'description' => 'Ability to import clients in bulk via CSV',
                'type' => 'boolean'
            ],
            [
                'name' => 'Shopping List Generation',
                'code' => 'shopping_list_generation',
                'description' => 'Ability to generate shopping lists based on meal plans',
                'type' => 'boolean'
            ],
            [
                'name' => 'Progress Tracking',
                'code' => 'progress_tracking',
                'description' => 'Ability to track client progress with measurements and photos',
                'type' => 'boolean'
            ],
            [
                'name' => 'Meal Compliance Tracking',
                'code' => 'meal_compliance_tracking',
                'description' => 'Ability to track client meal compliance',
                'type' => 'boolean'
            ],
        ];

        foreach ($features as $featureData) {
            SubscriptionFeature::create($featureData);
        }
    }
}