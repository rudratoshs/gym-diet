<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Gym;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    protected $subscriptionService;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\SubscriptionService  $subscriptionService
     * @return void
     */
    public function __construct(SubscriptionService $subscriptionService)
    {
        $this->subscriptionService = $subscriptionService;
    }

    /**
     * Display the active subscription for a gym.
     *
     * @param  \App\Models\Gym  $gym
     * @return \Illuminate\Http\Response
     */
    public function show(Gym $gym)
    {
        $subscription = $this->subscriptionService->getActiveSubscription($gym->id);

        if (!$subscription) {
            return response()->json(['message' => 'No active subscription found'], 404);
        }

        return new SubscriptionResource($subscription);
    }

    /**
     * Subscribe a gym to a plan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Gym  $gym
     * @return \Illuminate\Http\Response
     */
    public function subscribe(Request $request, Gym $gym)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,quarterly,annual',
            'payment_provider' => 'required|in:stripe,razorpay',
            'payment_method_id' => 'required_if:payment_provider,stripe',
            'payment_data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get the plan
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        try {
            // Check if gym already has an active subscription
            $activeSubscription = $this->subscriptionService->getActiveSubscription($gym->id);

            if ($activeSubscription) {
                return response()->json([
                    'error' => 'Gym already has an active subscription',
                    'subscription_id' => $activeSubscription->id,
                ], 409);
            }

            // Create the subscription
            $subscription = $this->subscriptionService->subscribeToPlan(
                $gym,
                $plan,
                $request->billing_cycle,
                $request->payment_provider,
                $request->only('payment_method_id', 'payment_data')
            );

            return new SubscriptionResource($subscription);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update the specified subscription.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Gym  $gym
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Gym $gym)
    {
        $validator = Validator::make($request->all(), [
            'plan_id' => 'sometimes|exists:subscription_plans,id',
            'payment_method_id' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get active subscription
        $subscription = $this->subscriptionService->getActiveSubscription($gym->id);

        if (!$subscription) {
            return response()->json(['error' => 'No active subscription found'], 404);
        }

        try {
            // Change plan if requested
            if ($request->has('plan_id')) {
                $newPlan = SubscriptionPlan::findOrFail($request->plan_id);
                $subscription = $this->subscriptionService->changePlan($subscription, $newPlan);
            }

            // Update payment method if requested
            if ($request->has('payment_method_id')) {
                $paymentService = app('App\\Services\\Payment\\' . ucfirst($subscription->payment_provider) . 'SubscriptionService');
                $paymentService->updatePaymentMethod($subscription, $request->payment_method_id);
            }

            return new SubscriptionResource($subscription->fresh());
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Cancel the specified subscription.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Gym  $gym
     * @return \Illuminate\Http\Response
     */
    public function cancel(Request $request, Gym $gym)
    {
        $validator = Validator::make($request->all(), [
            'at_period_end' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get active subscription
        $subscription = $this->subscriptionService->getActiveSubscription($gym->id);

        if (!$subscription) {
            return response()->json(['error' => 'No active subscription found'], 404);
        }

        try {
            $atPeriodEnd = $request->boolean('at_period_end', true);
            $success = $this->subscriptionService->cancelSubscription($subscription, $atPeriodEnd);

            if ($success) {
                return response()->json([
                    'message' => 'Subscription cancelled successfully' . ($atPeriodEnd ? ' at the end of the billing period' : ' immediately'),
                ]);
            } else {
                return response()->json(['error' => 'Failed to cancel subscription'], 500);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}