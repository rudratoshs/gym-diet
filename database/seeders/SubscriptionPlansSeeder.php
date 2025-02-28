<?php

namespace Database\Seeders;

use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlansSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create basic subscription plans
        $plans = [
            [
                'name' => 'Starter Plan',
                'code' => 'starter',
                'description' => 'Basic plan for small gyms with up to 50 clients.',
                'monthly_price' => 49.99,
                'quarterly_price' => 134.97, // 10% discount
                'annual_price' => 479.90, // 20% discount
                'features' => [
                    'max_clients' => 50,
                    'max_diet_plans' => 2,
                    'ai_meal_generation' => true,
                    'ai_meal_generation_limit' => 50,
                    'whatsapp_integration' => true,
                    'custom_branding' => false,
                    'analytics' => false,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Growth Plan',
                'code' => 'growth',
                'description' => 'Comprehensive plan for growing gyms with up to 200 clients.',
                'monthly_price' => 99.99,
                'quarterly_price' => 269.97, // 10% discount
                'annual_price' => 959.90, // 20% discount
                'features' => [
                    'max_clients' => 200,
                    'max_diet_plans' => 5,
                    'ai_meal_generation' => true,
                    'ai_meal_generation_limit' => 200,
                    'whatsapp_integration' => true,
                    'custom_branding' => true,
                    'analytics' => true,
                ],
                'is_active' => true,
            ],
            [
                'name' => 'Enterprise Plan',
                'code' => 'enterprise',
                'description' => 'Premium plan for large gyms with unlimited clients and advanced features.',
                'monthly_price' => 199.99,
                'quarterly_price' => 539.97, // 10% discount
                'annual_price' => 1919.90, // 20% discount
                'features' => [
                    'max_clients' => null, // unlimited
                    'max_diet_plans' => null, // unlimited
                    'ai_meal_generation' => true,
                    'ai_meal_generation_limit' => null, // unlimited
                    'whatsapp_integration' => true,
                    'custom_branding' => true,
                    'analytics' => true,
                    'priority_support' => true,
                    'white_label' => true,
                ],
                'is_active' => true,
            ],
        ];

        foreach ($plans as $planData) {
            // Extract features data
            $features = $planData['features'];
            unset($planData['features']);

            // Create the plan
            $plan = SubscriptionPlan::create($planData);

            // Attach features to the plan
            $this->attachFeaturesToPlan($plan, $features);
        }
    }

    /**
     * Attach features to a subscription plan.
     *
     * @param  \App\Models\SubscriptionPlan  $plan
     * @param  array  $features
     * @return void
     */
    private function attachFeaturesToPlan(SubscriptionPlan $plan, array $features): void
    {
        foreach ($features as $code => $value) {
            $feature = SubscriptionFeature::where('code', $code)->first();

            if ($feature) {
                // Determine limit and value based on feature type
                if ($feature->type === 'boolean') {
                    $plan->features()->attach($feature->id, [
                        'value' => $value ? 'true' : 'false',
                        'limit' => null,
                    ]);
                } elseif ($feature->type === 'numeric') {
                    // For numeric features like 'max_clients', the value is the limit
                    $plan->features()->attach($feature->id, [
                        'value' => 'true',
                        'limit' => $value,
                    ]);
                } elseif (str_ends_with($code, '_limit')) {
                    // For limit features, we use the value as the limit
                    // Remove '_limit' suffix to find the base feature
                    $baseFeatureCode = str_replace('_limit', '', $code);
                    $baseFeature = SubscriptionFeature::where('code', $baseFeatureCode)->first();

                    if ($baseFeature) {
                        // Update the base feature's limit
                        $pivotData = $plan->features()->where('subscription_features.id', $baseFeature->id)->first();

                        if ($pivotData) {
                            $plan->features()->updateExistingPivot($baseFeature->id, [
                                'limit' => $value,
                            ]);
                        }
                    }
                }
            }
        }
    }
}