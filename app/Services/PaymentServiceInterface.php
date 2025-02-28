<?php

namespace App\Services\Payment;

use App\Models\Gym;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;

interface PaymentServiceInterface
{
    /**
     * Create a subscription for a gym.
     *
     * @param  \App\Models\Gym  $gym
     * @param  \App\Models\SubscriptionPlan  $plan
     * @param  string  $billingCycle
     * @param  array  $paymentData
     * @return \App\Models\Subscription
     */
    public function createSubscription(Gym $gym, SubscriptionPlan $plan, string $billingCycle, array $paymentData);

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