<?php

namespace App\Services\Payment;

use App\Models\Gym;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class StripeSubscriptionService implements PaymentServiceInterface
{
    protected $stripe;

    /**
     * Create a new service instance.
     *
     * @return void
     */
    public function __construct()
    {
        if (!class_exists('Stripe\StripeClient')) {
            Log::warning('Stripe PHP SDK is not installed. Run: composer require stripe/stripe-php');
        } else {
            $this->stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
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
            // Get price ID based on plan type and billing cycle
            $priceId = $this->getPriceId($plan, $billingCycle);

            // Get gym owner
            $owner = $gym->owner;

            // Get or create customer
            $customer = $this->getOrCreateCustomer($owner);

            // Attach payment method to customer
            if (isset($paymentData['payment_method_id'])) {
                $this->stripe->paymentMethods->attach(
                    $paymentData['payment_method_id'],
                    ['customer' => $customer->id]
                );

                // Set as default payment method
                $this->stripe->customers->update($customer->id, [
                    'invoice_settings' => [
                        'default_payment_method' => $paymentData['payment_method_id'],
                    ],
                ]);
            }

            // Create subscription
            $stripeSubscription = $this->stripe->subscriptions->create([
                'customer' => $customer->id,
                'items' => [
                    ['price' => $priceId],
                ],
                'metadata' => [
                    'gym_id' => $gym->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                ],
            ]);

            // Create subscription record
            $subscription = new Subscription();
            $subscription->gym_id = $gym->id;
            $subscription->subscription_plan_id = $plan->id;
            $subscription->status = $stripeSubscription->status;
            $subscription->current_period_start = Carbon::createFromTimestamp($stripeSubscription->current_period_start);
            $subscription->current_period_end = Carbon::createFromTimestamp($stripeSubscription->current_period_end);
            $subscription->payment_provider = 'stripe';
            $subscription->payment_provider_id = $stripeSubscription->id;
            $subscription->billing_cycle = $billingCycle;
            $subscription->save();

            return $subscription;

        } catch (ApiErrorException $e) {
            Log::error('Stripe error: ' . $e->getMessage(), [
                'gym_id' => $gym->id,
                'plan_id' => $plan->id,
                'error' => $e->getJsonBody(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe subscription: ' . $e->getMessage(), [
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
            $stripeSubscription = $this->stripe->subscriptions->retrieve($subscription->payment_provider_id);

            if ($atPeriodEnd) {
                $this->stripe->subscriptions->update($subscription->payment_provider_id, [
                    'cancel_at_period_end' => true,
                ]);
            } else {
                $stripeSubscription->cancel();
            }

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Stripe error: ' . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'error' => $e->getJsonBody(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to cancel Stripe subscription: ' . $e->getMessage(), [
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
            // Get price ID for the new plan
            $priceId = $this->getPriceId($newPlan, $subscription->billing_cycle);

            // Get subscription from Stripe
            $stripeSubscription = $this->stripe->subscriptions->retrieve($subscription->payment_provider_id);

            // Update subscription items
            $this->stripe->subscriptions->update($subscription->payment_provider_id, [
                'items' => [
                    [
                        'id' => $stripeSubscription->items->data[0]->id,
                        'price' => $priceId,
                    ],
                ],
                'metadata' => [
                    'plan_id' => $newPlan->id,
                ],
                'proration_behavior' => 'create_prorations',
            ]);

            // Update subscription record
            $subscription->subscription_plan_id = $newPlan->id;
            $subscription->save();

            return $subscription;

        } catch (ApiErrorException $e) {
            Log::error('Stripe error: ' . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'new_plan_id' => $newPlan->id,
                'error' => $e->getJsonBody(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to change Stripe subscription plan: ' . $e->getMessage(), [
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
        try {
            $gym = $subscription->gym;
            $owner = $gym->owner;

            // Attach payment method to customer
            $this->stripe->paymentMethods->attach(
                $paymentMethodId,
                ['customer' => $owner->stripe_customer_id]
            );

            // Set as default payment method
            $this->stripe->customers->update($owner->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            return true;

        } catch (ApiErrorException $e) {
            Log::error('Stripe error: ' . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'error' => $e->getJsonBody(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update payment method: ' . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'exception' => $e,
            ]);
            throw $e;
        }
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
            $webhookSecret = config('services.stripe.webhook_secret');
            $event = \Stripe\Webhook::constructEvent($payload, $signature, $webhookSecret);
            return true;

        } catch (\Exception $e) {
            Log::error('Webhook signature verification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get or create customer on Stripe.
     *
     * @param  \App\Models\User  $user
     * @return \Stripe\Customer
     */
    protected function getOrCreateCustomer(User $user)
    {
        if ($user->stripe_customer_id) {
            return $this->stripe->customers->retrieve($user->stripe_customer_id);
        }

        $customer = $this->stripe->customers->create([
            'email' => $user->email,
            'name' => $user->name,
            'phone' => $user->phone,
            'metadata' => [
                'user_id' => $user->id
            ]
        ]);

        $user->stripe_customer_id = $customer->id;
        $user->save();

        return $customer;
    }

    /**
     * Get Stripe price ID for a plan and billing cycle.
     *
     * @param  \App\Models\SubscriptionPlan  $plan
     * @param  string  $billingCycle
     * @return string
     * 
     * @throws \Exception
     */
    protected function getPriceId(SubscriptionPlan $plan, string $billingCycle)
    {
        $priceMap = [
            'starter' => [
                'monthly' => config('services.stripe.prices.starter.monthly'),
                'quarterly' => config('services.stripe.prices.starter.quarterly'),
                'annual' => config('services.stripe.prices.starter.annual'),
            ],
            'growth' => [
                'monthly' => config('services.stripe.prices.growth.monthly'),
                'quarterly' => config('services.stripe.prices.growth.quarterly'),
                'annual' => config('services.stripe.prices.growth.annual'),
            ],
            'enterprise' => [
                'monthly' => config('services.stripe.prices.enterprise.monthly'),
                'quarterly' => config('services.stripe.prices.enterprise.quarterly'),
                'annual' => config('services.stripe.prices.enterprise.annual'),
            ],
        ];

        if (!isset($priceMap[$plan->code][$billingCycle])) {
            throw new \Exception("No price ID configured for plan {$plan->code} with billing cycle {$billingCycle}");
        }

        return $priceMap[$plan->code][$billingCycle];
    }
}