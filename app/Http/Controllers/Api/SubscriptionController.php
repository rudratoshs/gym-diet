<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResource;
use App\Models\Gym;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionService;
use App\Services\Payment\PaymentServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    protected $subscriptionService;
    protected $paymentFactory;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\SubscriptionService  $subscriptionService
     * @return void
     */
    public function __construct(SubscriptionService $subscriptionService,PaymentServiceFactory $paymentFactory)
    {
        $this->subscriptionService = $subscriptionService;
        $this->paymentFactory = $paymentFactory;
    }

    /**
     * Display the active subscription for a gym.
     *
     * @param  \App\Models\Gym  $gym
     * @return \Illuminate\Http\Response
     */
    public function show(Gym $gym)
    {
        // Check permissions
        if (!auth()->user()->can('view', $gym)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

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
        // Check permissions
        if (!auth()->user()->can('update', $gym)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'plan_id' => 'required|exists:subscription_plans,id',
            'plan_option' => 'required|string',
            'payment_provider' => 'required|in:stripe,razorpay',
            'payment_data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get the plan
        $plan = SubscriptionPlan::findOrFail($request->plan_id);

        // Verify the plan option exists
        $providerPlans = $plan->payment_provider_plans;
        if (!isset($providerPlans[$request->plan_option])) {
            return response()->json(['error' => 'Invalid plan option'], 422);
        }

        try {
            // Check if gym already has an active subscription
            $activeSubscription = $this->subscriptionService->getActiveSubscription($gym->id);

            if ($activeSubscription) {
                return response()->json([
                    'error' => 'Gym already has an active subscription',
                    'subscription_id' => $activeSubscription->id,
                ], 409);
            }

            // Get the provider plan ID
            $providerPlanId = $providerPlans[$request->plan_option]['id'];

            // Map the plan option to a billing cycle
            $billingCycle = $this->mapPlanOptionToBillingCycle($request->plan_option);

            // Set the provider plan ID in the payment data
            $paymentData = $request->payment_data ?? [];
            $paymentData['plan_id'] = $providerPlanId;

            // Create the subscription
            $subscription = $this->subscriptionService->subscribeToPlan(
                $gym,
                $plan,
                $billingCycle,
                $request->payment_provider,
                $paymentData
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
        // Check permissions
        if (!auth()->user()->can('update', $gym)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Validate request inputs
        $validator = Validator::make($request->all(), [
            'plan_id' => 'sometimes|exists:subscription_plans,id',
            'payment_provider' => 'sometimes|in:stripe,razorpay',
            'payment_method_id' => 'sometimes|string',
            'plan_option' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Fetch the active subscription
        $subscription = $this->subscriptionService->getActiveSubscription($gym->id);

        if (!$subscription) {
            return response()->json(['error' => 'No active subscription found'], 404);
        }

        try {
            DB::beginTransaction();

            // Change subscription plan if requested
            if ($request->has('plan_id')) {
                $newPlan = SubscriptionPlan::findOrFail($request->plan_id);

                // Verify if the new plan option exists
                $providerPlans = $newPlan->payment_provider_plans;
                if (!isset($providerPlans[$request->plan_option])) {
                    return response()->json(['error' => 'Invalid plan option'], 422);
                }

                // Get the provider plan ID
                $providerPlanId = $providerPlans[$request->plan_option]['id'];
                $billingCycle = $this->mapPlanOptionToBillingCycle($request->plan_option);

                // Call subscription service to change the plan
                $subscription = $this->subscriptionService->changePlan(
                    $subscription,
                    $newPlan,
                    $billingCycle,
                    $providerPlanId
                );
            }

            // Update payment method if requested
            if ($request->has('payment_method_id')) {
                $paymentService = $this->paymentFactory->create($request->payment_provider);
                $paymentService->updatePaymentMethod($subscription, $request->payment_method_id);
            }

            DB::commit();
            return new SubscriptionResource($subscription->fresh());

        } catch (\Exception $e) {
            DB::rollBack();
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
        // Check permissions
        if (!auth()->user()->can('update', $gym)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

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

    /**
     * Map plan option to billing cycle.
     *
     * @param  string  $planOption
     * @return string
     */
    private function mapPlanOptionToBillingCycle(string $planOption)
    {
        if (str_starts_with($planOption, 'day_')) {
            return 'daily';
        } elseif (str_starts_with($planOption, 'week_')) {
            return 'weekly';
        } elseif (str_starts_with($planOption, 'month_')) {
            $count = intval(substr($planOption, 6));
            return $count == 1 ? 'monthly' : ($count == 3 ? 'quarterly' : 'monthly');
        } elseif (str_starts_with($planOption, 'year_')) {
            return 'annual';
        } elseif (str_starts_with($planOption, 'one_time_')) {
            return 'one_time';
        }

        return 'monthly'; // Default
    }
}