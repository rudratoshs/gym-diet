<?php

namespace App\Services\Payment;

use App\Models\Gym;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;

interface PaymentServiceInterface
{
    /**
     * Create a plan in the payment provider.
     *
     * @param  array  $planData
     * @return string  The plan ID in the payment provider
     */
    public function createPlanInProvider(array $planData);

    /**
     * Create a subscription in the payment provider.
     *
     * @param  array  $subscriptionData
     * @return object  The subscription object from the payment provider
     */
    public function createSubscription(array $subscriptionData);

    /**
     * Cancel a subscription.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  bool  $atPeriodEnd
     * @return bool
     */
    public function cancelSubscription(Subscription $subscription, bool $atPeriodEnd = true);

    /**
     * Change subscription plan.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  \App\Models\SubscriptionPlan  $newPlan
     * @return \App\Models\Subscription
     */
    public function changePlan(Subscription $subscription, SubscriptionPlan $newPlan);

    /**
     * Update payment method for a subscription.
     *
     * @param  \App\Models\Subscription  $subscription
     * @param  string  $paymentMethodId
     * @return bool
     */
    public function updatePaymentMethod(Subscription $subscription, string $paymentMethodId);

    /**
     * Verify webhook signature.
     *
     * @param  string  $payload
     * @param  string  $signature
     * @return bool
     */
    public function verifyWebhookSignature(string $payload, string $signature);
}