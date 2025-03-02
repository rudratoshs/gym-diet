<?php

namespace App\Services;

use App\Models\Gym;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\GymSubscriptionPlan;
use App\Models\ClientSubscription;
use App\Services\Payment\PaymentServiceFactory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    protected $featureService;
    protected $paymentFactory;

    /**
     * Create a new service instance.
     *
     * @param  \App\Services\SubscriptionFeatureService  $featureService
     * @param  \App\Services\Payment\PaymentServiceFactory  $paymentFactory
     * @return void
     */
    public function __construct(
        SubscriptionFeatureService $featureService,
        PaymentServiceFactory $paymentFactory
    ) {
        $this->featureService = $featureService;
        $this->paymentFactory = $paymentFactory;
    }

    /**
     * Subscribe gym to a platform subscription plan.
     *
     * @param  \App\Models\Gym  $gym
     * @param  \App\Models\SubscriptionPlan  $plan
     * @param  string  $billingCycle
     * @param  string  $paymentMethod
     * @param  array  $paymentData
     * @return \App\Models\Subscription
     */
    public function subscribeToPlan(Gym $gym, SubscriptionPlan $plan, string $billingCycle, string $paymentProvider, array $paymentData)
    {
        try {
            // Begin database transaction
            DB::beginTransaction();

            // Get the payment service
            $paymentService = $this->paymentFactory->create($paymentProvider);

            // Create subscription through payment provider
            $subscriptionData = [
                'plan_id' => $paymentData['plan_id'], // Ensure this exists in paymentData
                'metadata' => [
                    'gym_id' => $gym->id,
                    'gym_name' => $gym->name,
                ],
                'start_date' => now()->timestamp // Optional: Can be removed if not needed
            ];

            // Create subscription through payment provider (passing subscriptionData)
            $subscriptionResponse = $paymentService->createSubscription($subscriptionData);

            // Store subscription in the database
            $subscription = Subscription::create([
                'gym_id' => $gym->id,
                'subscription_plan_id' => $plan->id,
                'status' => $subscriptionResponse->status ?? 'active', // Default to active if not provided
                'current_period_start' => $subscriptionResponse->current_period_start ?? now(),
                'current_period_end' => $subscriptionResponse->current_period_end ?? now()->addMonth(), // Default to 1 month if missing
                'payment_provider' => $paymentProvider,
                'payment_provider_id' => $subscriptionResponse->id ?? null, // Ensure this field exists
                'billing_cycle' => $billingCycle,
            ]);

            // Set up feature usage tracking
            $this->featureService->setupFeaturesForGym($gym->id, $plan);

            // Update gym subscription status
            $gym->subscription_status = 'active';
            $gym->subscription_expires_at = $subscription->current_period_end;
            $gym->save();

            DB::commit();
            return $subscription; // Now returning an Eloquent model

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create subscription: ' . $e->getMessage(), [
                'gym_id' => $gym->id,
                'plan_id' => $plan->id,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Cancel a gym's subscription.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  bool  $atPeriodEnd
     * @return bool
     */
    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = true)
    {
        try {
            // Get the payment service
            $paymentService = $this->paymentFactory->create($subscription->payment_provider);

            // Cancel subscription with payment provider
            $result = $paymentService->cancelSubscription($subscription, $atPeriodEnd);

            if ($result) {
                $subscription->canceled_at = now();

                if (!$atPeriodEnd) {
                    $subscription->status = 'canceled';

                    // Update gym subscription status
                    $gym = $subscription->gym;
                    $gym->subscription_status = 'inactive';
                    $gym->save();
                }

                $subscription->save();
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to cancel subscription: ' . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Change a gym's subscription plan.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\SubscriptionPlan  $newPlan
     * @return \App\Models\Subscription
     */
    public function changePlan(Subscription $subscription, SubscriptionPlan $newPlan)
    {
        try {
            // Begin database transaction
            DB::beginTransaction();

            // Get the payment service
            $paymentService = $this->paymentFactory->create($subscription->payment_provider);

            // Change plan with payment provider
            $result = $paymentService->changePlan($subscription, $newPlan);

            // Update feature usage tracking for the new plan
            $this->featureService->updateFeaturesForGym($subscription->gym_id, $newPlan);

            DB::commit();
            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to change subscription plan: ' . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'new_plan_id' => $newPlan->id,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Create a gym subscription plan for clients.
     *
     * @param  \App\Models\Gym  $gym
     * @param  array  $data
     * @return \App\Models\GymSubscriptionPlan
     */
    public function createInternalGymPlan(Gym $gym, array $data)
    {
        // Check if the gym has an active subscription
        if (!$this->hasActiveSubscription($gym->id)) {
            throw new \Exception('Gym does not have an active subscription to create client plans.');
        }

        try {
            // Begin database transaction
            DB::beginTransaction();

            // Validate required parameters
            if (empty($data['name']) || empty($data['price'])) {
                throw new \Exception('Missing required fields: name, price.');
            }

            $paymentProvider = $data['payment_provider'];
            $billingCycle = $data['billing_cycle'];
            // Get the payment service instance
            $paymentService = $this->paymentFactory->create($paymentProvider);

            if (!$paymentService) {
                throw new \Exception("Payment provider [$paymentProvider] is not supported.");
            }

            // Prepare minimal plan details
            $plan = new SubscriptionPlan([
                'name' => $data['name'],
                'price' => $data['price']
            ]);

            // Create Plan in the Payment Provider (e.g., Razorpay)
            $paymentProviderPlanId = $paymentService->createPlan($plan, $billingCycle);

            if (!$paymentProviderPlanId) {
                throw new \Exception("Failed to create plan with $paymentProvider.");
            }

            // Store in the database
            $gymPlan = GymSubscriptionPlan::create([
                'gym_id' => $gym->id,
                'name' => $data['name'],
                'price' => $data['price'],
                'billing_cycle' => $billingCycle,
                'is_active' => $data['is_active'] ?? true,
                'payment_provider_plan_id' => $paymentProviderPlanId,
            ]);

            // Commit the transaction
            DB::commit();

            return $gymPlan;

        } catch (\Exception $e) {
            // Rollback in case of error
            DB::rollBack();

            Log::error('Failed to create gym subscription plan: ' . $e->getMessage(), [
                'gym_id' => $gym->id,
                'billing_cycle' => $billingCycle,
                'payment_provider' => $paymentProvider,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * Subscribe a client to a gym plan.
     *
     * @param  \App\Models\User  $client
     * @param  \App\Models\GymSubscriptionPlan  $plan
     * @param  array  $data
     * @return \App\Models\ClientSubscription
     */
    public function subscribeClientToGymPlan(User $client, GymSubscriptionPlan $plan, array $data)
    {
        $gym = $plan->gym;

        // Check if gym has reached client limit
        if (!$this->canAddMoreClients($gym->id)) {
            throw new \Exception('Gym has reached the maximum number of clients allowed by their subscription');
        }

        // Calculate end date based on billing cycle
        $startDate = $data['start_date'] ?? now();
        $endDate = $this->calculateEndDate($startDate, $plan->billing_cycle);

        return ClientSubscription::create([
            'user_id' => $client->id,
            'gym_id' => $gym->id,
            'gym_subscription_plan_id' => $plan->id,
            'status' => $data['status'] ?? 'active',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'auto_renew' => $data['auto_renew'] ?? false,
            'payment_status' => $data['payment_status'] ?? 'pending',
        ]);
    }

    /**
     * Check if gym has an active subscription.
     *
     * @param  int  $gymId
     * @return bool
     */
    public function hasActiveSubscription(int $gymId): bool
    {
        return Subscription::where('gym_id', $gymId)
            ->where('status', 'active')
            ->where('current_period_end', '>', now())
            ->exists();
    }

    /**
     * Check if gym can add more clients based on subscription limits.
     *
     * @param  int  $gymId
     * @return bool
     */
    public function canAddMoreClients(int $gymId): bool
    {
        // Get current client count
        $clientCount = DB::table('gym_user')
            ->where('gym_id', $gymId)
            ->where('role', 'client')
            ->count();

        // Check if feature is available and hasn't reached limit
        return $this->featureService->hasFeatureAndAvailableUsage($gymId, 'max_clients', $clientCount);
    }

    /**
     * Calculate subscription end date based on billing cycle.
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  string  $billingCycle
     * @return \Carbon\Carbon
     */
    protected function calculateEndDate(Carbon $startDate, string $billingCycle): Carbon
    {
        switch ($billingCycle) {
            case 'monthly':
                return $startDate->copy()->addMonth();
            case 'quarterly':
                return $startDate->copy()->addMonths(3);
            case 'annual':
                return $startDate->copy()->addYear();
            default:
                return $startDate->copy()->addMonth();
        }
    }

    /**
     * Get active subscription for a gym.
     *
     * @param  int  $gymId
     * @return \App\Models\Subscription|null
     */
    public function getActiveSubscription(int $gymId)
    {
        return Subscription::where('gym_id', $gymId)
            ->where('status', 'active')
            ->where('current_period_end', '>', now())
            ->first();
    }
}