<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClientSubscription;
use App\Models\GymSubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\Auth;

class ClientSubscriptionController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $subscriptions = ClientSubscription::with('gymSubscriptionPlan')
            ->where('user_id', $user->id)
            ->get();

        return response()->json(['data' => $subscriptions]);
    }

    public function store(Request $request, SubscriptionService $subscriptionService)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'gym_id' => 'required|exists:gyms,id',
            'gym_subscription_plan_id' => 'required|exists:gym_subscription_plans,id',
            'status' => 'required|in:active,canceled,expired',
            'start_date' => 'required|date',
            'auto_renew' => 'required|boolean',
            'payment_status' => 'required|in:paid,pending,failed',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $plan = GymSubscriptionPlan::findOrFail($validated['gym_subscription_plan_id']);

        $subscription = $subscriptionService->subscribeClientToGymPlan($user, $plan, $validated);

        return response()->json(['message' => 'Subscription created', 'data' => $subscription]);
    }

    public function show($id)
    {
        $subscription = ClientSubscription::with('gymSubscriptionPlan')->findOrFail($id);
        return response()->json(['data' => $subscription]);
    }

    public function update(Request $request, $id)
    {
        $subscription = ClientSubscription::findOrFail($id);

        $validated = $request->validate([
            'status' => 'in:active,canceled,expired',
            'end_date' => 'nullable|date',
            'auto_renew' => 'boolean',
            'payment_status' => 'in:paid,pending,failed',
        ]);

        $subscription->update($validated);

        return response()->json(['message' => 'Subscription updated', 'data' => $subscription]);
    }

    public function cancel($id)
    {
        $subscription = ClientSubscription::findOrFail($id);
        $subscription->update([
            'status' => 'canceled',
            'auto_renew' => false,
        ]);

        return response()->json(['message' => 'Subscription canceled', 'data' => $subscription]);
    }
}
