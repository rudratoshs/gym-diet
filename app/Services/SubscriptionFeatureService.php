<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionFeatureUsage;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionFeatureService
{
    /**
     * Check if a gym has access to a specific feature.
     *
     * @param  int  $gymId
     * @param  string  $featureCode
     * @return bool
     */
    public function hasAccess(int $gymId, string $featureCode): bool
    {
        // Get gym's active subscription
        $subscription = Subscription::where('gym_id', $gymId)
            ->where('status', 'active')
            ->where('current_period_end', '>', now())
            ->first();

        if (!$subscription) {
            return false;
        }

        // Get subscription plan
        $plan = $subscription->plan;

        // Check if feature is available in plan through pivot table
        $feature = SubscriptionFeature::where('code', $featureCode)->first();

        if (!$feature) {
            return false;
        }

        return $plan->features()->where('subscription_features.id', $feature->id)->exists();
    }

    /**
     * Check if a gym has remaining usage for a specific feature.
     *
     * @param  int  $gymId
     * @param  string  $featureCode
     * @return bool
     */
    public function hasRemainingUsage(int $gymId, string $featureCode): bool
    {
        // First check if feature is accessible
        if (!$this->hasAccess($gymId, $featureCode)) {
            return false;
        }

        // Get feature
        $feature = SubscriptionFeature::where('code', $featureCode)->first();

        if (!$feature) {
            return false;
        }

        // Get feature usage
        $usage = SubscriptionFeatureUsage::where('gym_id', $gymId)
            ->where('subscription_feature_id', $feature->id)
            ->first();

        if (!$usage) {
            return true; // No usage record means limit hasn't been enforced
        }

        // Check if it's time to reset the usage counter
        if ($usage->reset_at && $usage->reset_at->isPast()) {
            $usage->resetUsage();
            return true;
        }

        // If limit is null, it means unlimited
        if ($usage->limit === null) {
            return true;
        }

        // Check if usage exceeds limit
        return $usage->current_usage < $usage->limit;
    }

    /**
     * Check if a feature is available and has remaining usage.
     *
     * @param  int  $gymId
     * @param  string  $featureCode
     * @param  int  $requiredAmount
     * @return bool
     */
    public function hasFeatureAndAvailableUsage(int $gymId, string $featureCode, int $requiredAmount = 1): bool
    {
        if (!$this->hasAccess($gymId, $featureCode)) {
            return false;
        }

        $feature = SubscriptionFeature::where('code', $featureCode)->first();

        if (!$feature) {
            return false;
        }

        $usage = SubscriptionFeatureUsage::where('gym_id', $gymId)
            ->where('subscription_feature_id', $feature->id)
            ->first();

        if (!$usage) {
            // Set up usage record with plan limits
            $limit = $this->getFeatureLimit($gymId, $featureCode);
            return $limit === null || $requiredAmount <= $limit;
        }

        // If limit is null, it means unlimited
        if ($usage->limit === null) {
            return true;
        }

        // Check if there's enough remaining usage
        return ($usage->limit - $usage->current_usage) >= $requiredAmount;
    }

    /**
     * Increment usage counter for a feature.
     *
     * @param  int  $gymId
     * @param  string  $featureCode
     * @param  int  $amount
     * @return bool
     */
    public function incrementUsage(int $gymId, string $featureCode, int $amount = 1): bool
    {
        // Check if feature is accessible
        if (!$this->hasAccess($gymId, $featureCode)) {
            return false;
        }

        // Get feature
        $feature = SubscriptionFeature::where('code', $featureCode)->first();

        if (!$feature) {
            return false;
        }

        // Get or create usage record
        $usage = SubscriptionFeatureUsage::firstOrCreate(
            [
                'gym_id' => $gymId,
                'subscription_feature_id' => $feature->id,
            ],
            [
                'current_usage' => 0,
                'limit' => $this->getFeatureLimit($gymId, $featureCode),
                'reset_at' => $this->getNextResetDate($gymId),
            ]
        );

        // Check if it's time to reset the usage counter
        if ($usage->reset_at && $usage->reset_at->isPast()) {
            $usage->resetUsage();
        }

        // Increment usage
        $usage->incrementUsage($amount);

        return true;
    }

    /**
     * Set up feature usage tracking for a gym based on subscription plan.
     *
     * @param  int  $gymId
     * @param  \App\Models\SubscriptionPlan  $plan
     * @return void
     */
    public function setupFeaturesForGym(int $gymId, SubscriptionPlan $plan): void
    {
        try {
            // Begin database transaction
            DB::beginTransaction();

            // Decode features JSON to an array
            $planFeatures = is_string($plan->features) ? json_decode($plan->features, true) : $plan->features;

            // Ensure it's an array before proceeding
            if (!is_array($planFeatures)) {
                throw new \Exception('Invalid features data format.');
            }

            foreach ($planFeatures as $featureCode => $limit) {
                // Fetch feature ID using the feature code
                $feature = SubscriptionFeature::where('code', $featureCode)->first();

                if (!$feature) {
                    throw new \Exception("Feature with code '{$featureCode}' not found.");
                }

                // Create or update feature usage record
                SubscriptionFeatureUsage::updateOrCreate(
                    [
                        'gym_id' => $gymId,
                        'subscription_feature_id' => $feature->id, // Use feature ID instead of string code
                    ],
                    [
                        'current_usage' => 0,
                        'limit' => $limit,
                        'reset_at' => $this->getNextResetDate($gymId),
                    ]
                );
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to set up features for gym: ' . $e->getMessage(), [
                'gym_id' => $gymId,
                'plan_id' => $plan->id,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Update feature usage tracking for a gym when changing plans.
     *
     * @param  int  $gymId
     * @param  \App\Models\SubscriptionPlan  $newPlan
     * @return void
     */
    public function updateFeaturesForGym(int $gymId, SubscriptionPlan $newPlan): void
    {
        try {
            // Begin database transaction
            DB::beginTransaction();

            // Get all features for the new plan with their limits
            $planFeatures = $newPlan->features;

            // Get all current usage records
            $currentUsage = SubscriptionFeatureUsage::where('gym_id', $gymId)->get()->keyBy('subscription_feature_id');

            foreach ($planFeatures as $feature) {
                // Get limit from pivot table
                $limit = $feature->pivot->limit;

                // Create or update feature usage record
                SubscriptionFeatureUsage::updateOrCreate(
                    [
                        'gym_id' => $gymId,
                        'subscription_feature_id' => $feature->id,
                    ],
                    [
                        'current_usage' => $currentUsage->has($feature->id) ? $currentUsage[$feature->id]->current_usage : 0,
                        'limit' => $limit,
                        'reset_at' => $this->getNextResetDate($gymId),
                    ]
                );
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update features for gym: ' . $e->getMessage(), [
                'gym_id' => $gymId,
                'plan_id' => $newPlan->id,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Get feature limit for a gym from its subscription plan.
     *
     * @param  int  $gymId
     * @param  string  $featureCode
     * @return int|null
     */
    private function getFeatureLimit(int $gymId, string $featureCode)
    {
        $subscription = Subscription::where('gym_id', $gymId)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return 0;
        }

        $feature = SubscriptionFeature::where('code', $featureCode)->first();

        if (!$feature) {
            return 0;
        }

        $planFeature = DB::table('subscription_plan_features')
            ->where('subscription_plan_id', $subscription->subscription_plan_id)
            ->where('subscription_feature_id', $feature->id)
            ->first();

        return $planFeature ? $planFeature->limit : null;
    }

    /**
     * Get next reset date based on billing cycle.
     *
     * @param  int  $gymId
     * @return \Carbon\Carbon
     */
    private function getNextResetDate(int $gymId): Carbon
    {
        $subscription = Subscription::where('gym_id', $gymId)
            ->where('status', 'active')
            ->first();

        if (!$subscription) {
            return now()->addMonth();
        }

        // Calculate reset date based on billing cycle
        switch ($subscription->billing_cycle) {
            case 'monthly':
                return now()->addMonth();
            case 'quarterly':
                return now()->addMonths(3);
            case 'annual':
                return now()->addYear();
            default:
                return now()->addMonth();
        }
    }
}