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
     * Create a subscription in Razorpay.
     *
     * @param  array  $subscriptionData
     * @return object  The subscription object from Razorpay
     */
    public function createSubscription(array $subscriptionData)
    {
        try {
            // Prepare subscription data
            $data = [
                'plan_id' => $subscriptionData['plan_id'],
                'total_count' => 60, // Number of billing cycles (max allowed)
                'quantity' => 1,
                'customer_notify' => 1,
                'notes' => $subscriptionData['metadata'] ?? []
            ];

            // Ensure start_at is in the future (at least 5 minutes ahead)
            if (isset($subscriptionData['start_date'])) {
                $startAt = max($subscriptionData['start_date'], now()->addMinutes(5)->timestamp);
                $data['start_at'] = $startAt;
            }
            // Create subscription in Razorpay
            $subscription = $this->razorpay->subscription->create($data);

            // Convert to a standardized format
            return (object) [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'current_period_start' => $subscription->current_start ? strtotime($subscription->current_start) : null,
                'current_period_end' => $subscription->current_end ? strtotime($subscription->current_end) : null,
                'customer_id' => $subscription->customer_id ?? null,
                'notes' => $subscription->notes ?? [],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create Razorpay subscription: ' . $e->getMessage(), [
                'error' => $e,
                'subscription_data' => $subscriptionData
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
     */
    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = true)
    {
        try {
            $this->razorpay->subscription->fetch($subscription->payment_provider_id)->cancel($atPeriodEnd);
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
     */
    public function changePlan(Subscription $subscription, SubscriptionPlan $newPlan)
    {
        try {
            // Razorpay doesn't support changing plans directly
            // Cancel current subscription
            $this->cancelSubscription($subscription, false);

            // Create new subscription with the new plan
            $planOption = $subscription->billing_cycle;
            $planIdKey = '';

            switch ($subscription->billing_cycle) {
                case 'monthly':
                    $planIdKey = 'month_1';
                    break;
                case 'quarterly':
                    $planIdKey = 'month_3';
                    break;
                case 'annual':
                    $planIdKey = 'year_1';
                    break;
            }

            $providerPlans = $newPlan->payment_provider_plans;
            if (!isset($providerPlans[$planIdKey])) {
                throw new \Exception("Plan option not available: {$planIdKey}");
            }

            $providerPlanId = $providerPlans[$planIdKey]['id'];

            $subscriptionData = [
                'plan_id' => $providerPlanId,
                'metadata' => [
                    'gym_id' => $subscription->gym_id,
                    'plan_id' => $newPlan->id,
                    'billing_cycle' => $subscription->billing_cycle
                ]
            ];

            $providerSubscription = $this->createSubscription($subscriptionData);

            // Update subscription
            $subscription->subscription_plan_id = $newPlan->id;
            $subscription->payment_provider_id = $providerSubscription->id;
            $subscription->current_period_start = Carbon::createFromTimestamp($providerSubscription->current_period_start ?? now()->timestamp);
            $subscription->current_period_end = Carbon::createFromTimestamp($providerSubscription->current_period_end ?? $this->calculateEndDate(now(), $subscription->billing_cycle)->timestamp);
            $subscription->status = 'active';
            $subscription->canceled_at = null;
            $subscription->save();

            return $subscription;

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
     */
    public function updatePaymentMethod(Subscription $subscription, string $paymentMethodId)
    {
        // Razorpay doesn't support updating payment methods for existing subscriptions
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
    private function getOrCreateCustomer($owner)
    {
        try {
            // Step 1: Try to fetch an existing customer by email
            $customers = $this->razorpay->customer->all(['email' => $owner->email]);

            if (!empty($customers['items'])) {
                // ✅ Use existing customer
                return $customers['items'][0];
            }

            // Step 2: If not found, create a new customer
            return $this->razorpay->customer->create([
                'name' => $owner->name,
                'email' => $owner->email,
                'contact' => $owner->phone,
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to get or create Razorpay customer: " . $e->getMessage(), [
                'email' => $owner->email,
                'exception' => $e,
            ]);
            throw $e;
        }
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
     * Calculate end date based on billing cycle.
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  string  $billingCycle
     * @return \Carbon\Carbon
     */
    private function calculateEndDate(Carbon $startDate, string $billingCycle)
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
     * Create a plan in Razorpay.
     *
     * @param  SubscriptionPlan  $plan
     * @param  string  $billingCycle  (monthly, quarterly, yearly)
     * @return string|null  Razorpay Plan ID or null on failure
     */
    public function createPlan(SubscriptionPlan $plan, string $billingCycle)
    {
        try {
            if (!$this->razorpay) {
                throw new \Exception("Razorpay SDK not initialized.");
            }

            // Convert price to paise (Razorpay expects smallest currency unit)
            $amountInPaise = $plan->price * 100;

            // Map billing cycle
            $periods = [
                'monthly' => 'monthly',
                'quarterly' => 'quarterly',
                'yearly' => 'yearly'
            ];

            if (!isset($periods[$billingCycle])) {
                throw new \Exception("Invalid billing cycle: $billingCycle");
            }

            // Prepare Razorpay API request
            $planData = [
                "period" => $periods[$billingCycle],
                "interval" => 1,
                "item" => [
                    "name" => $plan->name,
                    "amount" => $amountInPaise,
                    "currency" => "INR"
                ]
            ];

            // Create the plan in Razorpay
            $razorpayPlan = $this->razorpay->plan->create($planData);

            // Log success
            Log::info("Razorpay Plan Created: " . json_encode($razorpayPlan->toArray()));

            // Return Razorpay Plan ID
            return $razorpayPlan->id;

        } catch (\Exception $e) {
            Log::error("Failed to create Razorpay Plan: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a plan in Razorpay.
     *
     * @param  array  $planData
     * @return string  The plan ID in Razorpay
     */
    public function createPlanInProvider(array $planData)
    {
        try {
            $period = 'daily';
            $intervalCount = 1;

            // Map our interval to Razorpay period
            if (isset($planData['interval'])) {
                switch ($planData['interval']) {
                    case 'day':
                        $period = 'daily';
                        break;
                    case 'week':
                        $period = 'weekly';
                        break;
                    case 'month':
                        $period = 'monthly';
                        break;
                    case 'year':
                        $period = 'yearly';
                        break;
                }

                // Interval count
                if (isset($planData['interval_count'])) {
                    $intervalCount = $planData['interval_count'];
                }
            }

            // Create a Razorpay Plan
            $razorpayPlan = $this->razorpay->plan->create([
                'period' => $period,
                'interval' => $intervalCount,
                'item' => [
                    'name' => $planData['name'],
                    'amount' => (int) $planData['amount'],
                    'currency' => $planData['currency'] ?? 'INR',
                    'description' => $planData['description'] ?? ''
                ]
            ]);

            return $razorpayPlan->id;

        } catch (\Exception $e) {
            Log::error('Failed to create Razorpay plan: ' . $e->getMessage(), [
                'error' => $e,
                'plan_data' => $planData
            ]);
            throw $e;
        }
    }

}