<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Gym;
use App\Models\Subscription;
use App\Services\Payment\PaymentServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * Handle Stripe webhook events.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handleStripe(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');

        // Create the payment service
        $paymentFactory = new PaymentServiceFactory();
        $paymentService = $paymentFactory->create('stripe');

        // Verify webhook signature
        if (!$paymentService->verifyWebhookSignature($payload, $signature)) {
            Log::error('Stripe webhook signature verification failed.');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Parse event
        $event = null;
        try {
            $event = json_decode($payload, true);
        } catch (\Exception $e) {
            Log::error('Error parsing Stripe webhook: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Handle different event types
        switch ($event['type']) {
            case 'customer.subscription.created':
                $this->handleSubscriptionCreated($event['data']['object'], 'stripe');
                break;

            case 'customer.subscription.updated':
                $this->handleSubscriptionUpdated($event['data']['object'], 'stripe');
                break;

            case 'customer.subscription.deleted':
                $this->handleSubscriptionDeleted($event['data']['object'], 'stripe');
                break;

            case 'invoice.payment_succeeded':
                $this->handlePaymentSucceeded($event['data']['object'], 'stripe');
                break;

            case 'invoice.payment_failed':
                $this->handlePaymentFailed($event['data']['object'], 'stripe');
                break;

            default:
                Log::info('Unhandled Stripe event: ' . $event['type']);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle Razorpay webhook events.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function handleRazorpay(Request $request)
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature');

        // Create the payment service
        $paymentFactory = new PaymentServiceFactory();
        $paymentService = $paymentFactory->create('razorpay');

        // Verify webhook signature
        if (!$paymentService->verifyWebhookSignature($payload, $signature)) {
            Log::error('Razorpay webhook signature verification failed.');
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Parse event
        $event = null;
        try {
            $event = json_decode($payload, true);
        } catch (\Exception $e) {
            Log::error('Error parsing Razorpay webhook: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Handle different event types
        switch ($event['event']) {
            case 'subscription.authenticated':
                $this->handleSubscriptionCreated($event['payload']['subscription']['entity'], 'razorpay');
                break;

            case 'subscription.activated':
                $this->handleSubscriptionUpdated($event['payload']['subscription']['entity'], 'razorpay');
                break;

            case 'subscription.cancelled':
                $this->handleSubscriptionDeleted($event['payload']['subscription']['entity'], 'razorpay');
                break;

            case 'subscription.charged':
                $this->handlePaymentSucceeded($event['payload']['payment']['entity'], 'razorpay');
                break;

            case 'subscription.payment.failed':
                $this->handlePaymentFailed($event['payload']['payment']['entity'], 'razorpay');
                break;

            default:
                Log::info('Unhandled Razorpay event: ' . $event['event']);
        }

        return response()->json(['status' => 'success']);
    }

    /**
     * Handle subscription created event.
     *
     * @param  array  $data
     * @param  string  $provider
     * @return void
     */
    private function handleSubscriptionCreated(array $data, string $provider)
    {
        $providerId = $data['id'];

        // Extract gym ID from metadata
        $gymId = null;
        if ($provider === 'stripe') {
            $gymId = $data['metadata']['gym_id'] ?? null;
        } elseif ($provider === 'razorpay') {
            $gymId = $data['notes']['gym_id'] ?? null;
        }

        if (!$gymId) {
            Log::warning("{$provider} subscription created without gym_id", ['data' => $data]);
            return;
        }

        // Find the gym
        $gym = Gym::find($gymId);
        if (!$gym) {
            Log::warning("Gym not found for {$provider} subscription", ['gym_id' => $gymId]);
            return;
        }

        // Update subscription status
        $subscription = Subscription::where('gym_id', $gymId)
            ->where('payment_provider', $provider)
            ->where('payment_provider_id', $providerId)
            ->first();

        if ($subscription) {
            // Update status
            $subscription->status = 'active';
            $subscription->save();

            // Update gym subscription status
            $gym->subscription_status = 'active';
            $gym->subscription_expires_at = $subscription->current_period_end;
            $gym->save();

            Log::info("Subscription activated for gym", [
                'gym_id' => $gymId,
                'subscription_id' => $subscription->id
            ]);
        } else {
            Log::warning("Subscription not found for {$provider} subscription ID", [
                'provider_id' => $providerId,
                'gym_id' => $gymId
            ]);
        }
    }

    /**
     * Handle subscription updated event.
     *
     * @param  array  $data
     * @param  string  $provider
     * @return void
     */
    private function handleSubscriptionUpdated(array $data, string $provider)
    {
        $providerId = $data['id'];

        // Find the subscription
        $subscription = Subscription::where('payment_provider', $provider)
            ->where('payment_provider_id', $providerId)
            ->first();

        if (!$subscription) {
            Log::warning("Subscription not found for {$provider} update", [
                'provider_id' => $providerId
            ]);
            return;
        }

        // Update subscription details
        $subscription->status = $this->mapSubscriptionStatus($data, $provider);

        // Update period dates for Stripe
        if ($provider === 'stripe') {
            $subscription->current_period_start = date('Y-m-d H:i:s', $data['current_period_start']);
            $subscription->current_period_end = date('Y-m-d H:i:s', $data['current_period_end']);

            if (isset($data['canceled_at'])) {
                $subscription->canceled_at = date('Y-m-d H:i:s', $data['canceled_at']);
            }
        }

        $subscription->save();

        // Update gym subscription status
        $gym = Gym::find($subscription->gym_id);
        if ($gym) {
            // Only update gym status if subscription is still active
            if ($subscription->status === 'active') {
                $gym->subscription_status = 'active';
                $gym->subscription_expires_at = $subscription->current_period_end;
            } elseif ($subscription->status === 'canceled' || $subscription->status === 'past_due') {
                $gym->subscription_status = 'inactive';
            }

            $gym->save();
        }

        Log::info("Subscription updated", [
            'gym_id' => $subscription->gym_id,
            'subscription_id' => $subscription->id,
            'status' => $subscription->status
        ]);
    }

    /**
     * Handle subscription deleted/canceled event.
     *
     * @param  array  $data
     * @param  string  $provider
     * @return void
     */
    private function handleSubscriptionDeleted(array $data, string $provider)
    {
        $providerId = $data['id'];

        // Find the subscription
        $subscription = Subscription::where('payment_provider', $provider)
            ->where('payment_provider_id', $providerId)
            ->first();

        if (!$subscription) {
            Log::warning("Subscription not found for {$provider} deletion", [
                'provider_id' => $providerId
            ]);
            return;
        }

        // Update subscription
        $subscription->status = 'canceled';
        $subscription->canceled_at = now();
        $subscription->save();

        // Update gym subscription status
        $gym = Gym::find($subscription->gym_id);
        if ($gym) {
            $gym->subscription_status = 'inactive';
            $gym->save();

            Log::info("Gym subscription marked as inactive", [
                'gym_id' => $gym->id
            ]);
        }

        Log::info("Subscription canceled", [
            'gym_id' => $subscription->gym_id,
            'subscription_id' => $subscription->id
        ]);
    }

    /**
     * Handle payment succeeded event.
     *
     * @param  array  $data
     * @param  string  $provider
     * @return void
     */
    private function handlePaymentSucceeded(array $data, string $provider)
    {
        $subscriptionId = null;
        $amount = 0;

        if ($provider === 'stripe') {
            $subscriptionId = $data['subscription'] ?? null;
            $amount = isset($data['amount_paid']) ? $data['amount_paid'] / 100 : 0;
        } elseif ($provider === 'razorpay') {
            $subscriptionId = $data['subscription_id'] ?? null;
            $amount = isset($data['amount']) ? $data['amount'] / 100 : 0;
        }

        if (!$subscriptionId) {
            Log::info("Payment received but not for a subscription", [
                'provider' => $provider,
                'data' => $data
            ]);
            return;
        }

        // Find the subscription
        $subscription = Subscription::where('payment_provider', $provider)
            ->where('payment_provider_id', $subscriptionId)
            ->first();

        if (!$subscription) {
            Log::warning("Subscription not found for payment", [
                'provider' => $provider,
                'subscription_id' => $subscriptionId
            ]);
            return;
        }

        // Create payment record
        // (This would be implemented in a real Payment model)
        Log::info("Payment recorded", [
            'gym_id' => $subscription->gym_id,
            'subscription_id' => $subscription->id,
            'amount' => $amount,
            'provider' => $provider
        ]);

        // Ensure subscription is marked as active
        if ($subscription->status !== 'active') {
            $subscription->status = 'active';
            $subscription->save();

            // Update gym subscription status
            $gym = Gym::find($subscription->gym_id);
            if ($gym) {
                $gym->subscription_status = 'active';
                $gym->subscription_expires_at = $subscription->current_period_end;
                $gym->save();
            }
        }
    }

    /**
     * Handle payment failed event.
     *
     * @param  array  $data
     * @param  string  $provider
     * @return void
     */
    private function handlePaymentFailed(array $data, string $provider)
    {
        $subscriptionId = null;

        if ($provider === 'stripe') {
            $subscriptionId = $data['subscription'] ?? null;
        } elseif ($provider === 'razorpay') {
            $subscriptionId = $data['subscription_id'] ?? null;
        }

        if (!$subscriptionId) {
            Log::info("Payment failed but not for a subscription", [
                'provider' => $provider,
                'data' => $data
            ]);
            return;
        }

        // Find the subscription
        $subscription = Subscription::where('payment_provider', $provider)
            ->where('payment_provider_id', $subscriptionId)
            ->first();

        if (!$subscription) {
            Log::warning("Subscription not found for failed payment", [
                'provider' => $provider,
                'subscription_id' => $subscriptionId
            ]);
            return;
        }

        // Update subscription status to past_due
        $subscription->status = 'past_due';
        $subscription->save();

        // Log the failed payment
        Log::info("Payment failed", [
            'gym_id' => $subscription->gym_id,
            'subscription_id' => $subscription->id,
            'provider' => $provider
        ]);

        // Potentially send notification to gym owner
    }

    /**
     * Map provider-specific status to our internal status.
     *
     * @param  array  $data
     * @param  string  $provider
     * @return string
     */
    private function mapSubscriptionStatus(array $data, string $provider): string
    {
        if ($provider === 'stripe') {
            $status = $data['status'] ?? '';

            // Stripe uses these status values
            return match ($status) {
                'active' => 'active',
                'past_due' => 'past_due',
                'canceled' => 'canceled',
                'incomplete' => 'pending',
                'incomplete_expired' => 'canceled',
                'trialing' => 'active',
                'unpaid' => 'past_due',
                default => 'active',
            };
        } else {
            $status = $data['status'] ?? '';

            // Razorpay uses different status values
            return match ($status) {
                'active' => 'active',
                'authenticated' => 'active',
                'pending' => 'pending',
                'halted' => 'past_due',
                'cancelled' => 'canceled',
                'completed' => 'canceled',
                default => 'active',
            };
        }
    }
}