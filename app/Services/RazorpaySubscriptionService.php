<?php

namespace App\Services\Payment;

use App\Models\Gym;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class RazorpaySubscriptionService implements PaymentServiceInterface
{
    protected $razorpay;

    /**
     * Create a new service instance.
     *
     * @return void
     */
    public function __construct()
    {
        if (!class_exists('Razorpay\Api\Api')) {
            Log::warning('Razorpay PHP SDK is not installed. Run: composer require razorpay/razorpay');
        } else {
            $this->razorpay = new \Razorpay\Api\Api(
                config('services.razorpay.key'),
                config('services.razorpay.secret')
            );
        }
    }

    /**
     * Create a subscription for a gym.
     *
     * @param  \App\Models\Gym  $gym
     * @param  \App\Models\SubscriptionPlan  $plan
     * @param  string  $billingCycle
     * @param  array  $paymentData
     * @return \App\Models\Subscription
     * 
     * @throws \Exception
     */
    public function createSubscription(Gym $gym, SubscriptionPlan $plan, string $billingCycle, array $paymentData)
    {
        try {
            // Get plan ID for the Razorpay plan
            $planId = $this->getPlanId($plan, $billingCycle);

            // Get gym owner
            $owner = $gym->owner;

            // Get or create customer
            $customer = $this->getOrCreateCustomer($owner);

            // Create subscription
            $subscriptionData = [
                'plan_id' => $planId,
                'customer_id' => $customer->id,
                'total_count' => $this->getTotalCount($billingCycle),
                'notes' => [
                    'gym_id' => $gym->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                ]
            ];

            $razorpaySubscription = $this->razorpay->subscription->create($subscriptionData);

            // Create subscription record
            $subscription = new Subscription();
            $subscription->gym_id = $gym->id;
            $subscription->subscription_plan_id = $plan->id;
            $subscription->status = 'active';
            $subscription->current_period_start = now();
            $subscription->current_period_end = $this->calculateEndDate($billingCycle);
            $subscription->payment_provider = 'razorpay';
            $subscription->payment_provider_id = $razorpaySubscription->id;
            $subscription->billing_cycle = $billingCycle;
            $subscription->save();

            return $subscription;

        } catch (\Exception $e) {
            Log::error('Failed to create Razorpay subscription: ' . $e->getMessage(), [
                'gym_id' => $gym->id,
                'plan_id' => $plan->id,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Cancel a subscription.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  bool  $atPeriodEnd
     * @return bool
     * 
     * @throws \Exception
     */
    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = true)
    {
        try {
            $this->razorpay->subscription->fetch($subscription->payment_provider_id)->cancel();
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to cancel Razorpay subscription: ' . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Change subscription plan.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\SubscriptionPlan  $newPlan
     * @return \App\Models\Subscription
     * 
     * @throws \Exception
     */
    public function changePlan(Subscription $subscription, SubscriptionPlan $newPlan)
    {
        try {
            // Razorpay doesn't support changing plans directly
            // We need to cancel the current subscription and create a new one

            // Cancel current subscription
            $this->cancelSubscription($subscription, false);

            // Create new subscription
            $gym = $subscription->gym;
            $newSubscription = $this->createSubscription(
                $gym,
                $newPlan,
                $subscription->billing_cycle,
                []
            );

            return $newSubscription;

        } catch (\Exception $e) {
            Log::error('Failed to change Razorpay subscription plan: ' . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'new_plan_id' => $newPlan->id,
                'exception' => $e,
            ]);
            throw $e;
        }
    }

    /**
     * Update payment method for a subscription.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  string  $paymentMethodId
     * @return bool
     * 
     * @throws \Exception
     */
    public function updatePaymentMethod(Subscription $subscription, string $paymentMethodId)
    {
        // Razorpay doesn't support updating payment methods for existing subscriptions
        // This would require cancelling and recreating the subscription
        throw new \Exception('Updating payment method is not supported for Razorpay subscriptions');
    }

    /**
     * Verify webhook signature.
     *
     * @param  string  $payload
     * @param  string  $signature
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature)
    {
        try {
            $webhookSecret = config('services.razorpay.webhook_secret');

            $expectedSignature = hash_hmac('sha256', $payload, $webhookSecret);

            return hash_equals($expectedSignature, $signature);

        } catch (\Exception $e) {
            Log::error('Webhook signature verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get or create customer on Razorpay.
     *
     * @param  \App\Models\User  $user
     * @return \Razorpay\Api\Customer
     */
    protected function getOrCreateCustomer(User $user)
    {
        if ($user->razorpay_customer_id) {
            return $this->razorpay->customer->fetch($user->razorpay_customer_id);
        }

        $customer = $this->razorpay->customer->create([
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->phone,
            'notes' => [
                'user_id' => $user->id
            ]
        ]);

        $user->razorpay_customer_id = $customer->id;
        $user->save();

        return $customer;
    }

    /**
     * Get Razorpay plan ID for a subscription plan and billing cycle.
     *
     * @param  \App\Models\SubscriptionPlan  $plan
     * @param  string  $billingCycle
     * @return string
     * 
     * @throws \Exception
     */
    protected function getPlanId(SubscriptionPlan $plan, string $billingCycle)
    {
        $planMap = [
            'starter' => [
                'monthly' => config('services.razorpay.plans.starter.monthly'),
                'quarterly' => config('services.razorpay.plans.starter.quarterly'),
                'annual' => config('services.razorpay.plans.starter.annual'),
            ],
            'growth' => [
                'monthly' => config('services.razorpay.plans.growth.monthly'),
                'quarterly' => config('services.razorpay.plans.growth.quarterly'),
                'annual' => config('services.razorpay.plans.growth.annual'),
            ],
            'enterprise' => [
                'monthly' => config('services.razorpay.plans.enterprise.monthly'),
                'quarterly' => config('services.razorpay.plans.enterprise.quarterly'),
                'annual' => config('services.razorpay.plans.enterprise.annual'),
            ],
        ];

        if (!isset($planMap[$plan->code][$billingCycle])) {
            throw new \Exception("No plan ID configured for plan {$plan->code} with billing cycle {$billingCycle}");
        }

        return $planMap[$plan->code][$billingCycle];
    }

    /**
     * Get total count of billing cycles (number of payments).
     *
     * @param  string  $billingCycle
     * @return int
     */
    protected function getTotalCount(string $billingCycle)
    {
        return match ($billingCycle) {
            'monthly' => 12, // 1 year worth of monthly payments
            'quarterly' => 4, // 1 year worth of quarterly payments
            'annual' => 1, // 1 annual payment
            default => 12,
        };
    }

    /**
     * Calculate subscription end date based on billing cycle.
     *
     * @param  string  $billingCycle
     * @return \Carbon\Carbon
     */
    protected function calculateEndDate(string $billingCycle)
    {
        $now = now();

        return match ($billingCycle) {
            'monthly' => $now->copy()->addMonth(),
            'quarterly' => $now->copy()->addMonths(3),
            'annual' => $now->copy()->addYear(),
            default => $now->copy()->addMonth(),
        };
    }
}