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
        $this->stripe = new StripeClient(config('services.stripe.secret'));
    }

    /**
     * Create a plan in Stripe.
     *
     * @param  array  $planData
     * @return string  The plan ID in Stripe
     */
    public function createPlanInProvider(array $planData)
    {
        try {
            // First create a product
            $product = $this->stripe->products->create([
                'name' => $planData['name'],
                'description' => $planData['description'] ?? null,
            ]);

            // Create price data
            $priceData = [
                'product' => $product->id,
                'unit_amount' => (int) $planData['amount'],
                'currency' => $planData['currency'] ?? 'inr',
            ];

            // Add recurring info if it's a recurring plan
            if (!empty($planData['is_recurring'])) {
                $priceData['recurring'] = [
                    'interval' => $planData['interval'] ?? 'month',
                    'interval_count' => $planData['interval_count'] ?? 1,
                ];
            }

            // Then create a price for the product
            $price = $this->stripe->prices->create($priceData);

            return $price->id;

        } catch (ApiErrorException $e) {
            Log::error('Stripe error: ' . $e->getMessage(), [
                'error' => $e->getJsonBody(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe plan: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Create a subscription in Stripe.
     *
     * @param  array  $subscriptionData
     * @return object  The subscription object from Stripe
     */
    public function createSubscription(array $subscriptionData)
    {
        try {
            // If no customer ID is provided, we need a payment method ID
            if (empty($subscriptionData['customer_id']) && !empty($subscriptionData['payment_method_id'])) {
                // Create customer and attach payment method
                $customer = $this->stripe->customers->create([
                    'payment_method' => $subscriptionData['payment_method_id'],
                    'invoice_settings' => [
                        'default_payment_method' => $subscriptionData['payment_method_id'],
                    ],
                    'metadata' => $subscriptionData['metadata'] ?? [],
                ]);

                $customerId = $customer->id;
            } else {
                $customerId = $subscriptionData['customer_id'];
            }

            // Create subscription
            $subscription = $this->stripe->subscriptions->create([
                'customer' => $customerId,
                'items' => [
                    ['price' => $subscriptionData['plan_id']],
                ],
                'metadata' => $subscriptionData['metadata'] ?? [],
            ]);

            return $subscription;

        } catch (ApiErrorException $e) {
            Log::error('Stripe error: ' . $e->getMessage(), [
                'error' => $e->getJsonBody(),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create Stripe subscription: ' . $e->getMessage());
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
     */
    public function changePlan(Subscription $subscription, SubscriptionPlan $newPlan)
    {
        try {
            // Get the appropriate plan ID for the current billing cycle
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

            // Get subscription from Stripe
            $stripeSubscription = $this->stripe->subscriptions->retrieve($subscription->payment_provider_id);

            // Update subscription items
            $this->stripe->subscriptions->update($subscription->payment_provider_id, [
                'items' => [
                    [
                        'id' => $stripeSubscription->items->data[0]->id,
                        'price' => $providerPlanId,
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
     */
    public function updatePaymentMethod(Subscription $subscription, string $paymentMethodId)
    {
        try {
            // Get subscription details to find customer
            $stripeSubscription = $this->stripe->subscriptions->retrieve($subscription->payment_provider_id);
            $customerId = $stripeSubscription->customer;

            // Attach payment method to customer
            $this->stripe->paymentMethods->attach(
                $paymentMethodId,
                ['customer' => $customerId]
            );

            // Set as default payment method
            $this->stripe->customers->update($customerId, [
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
}