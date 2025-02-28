<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GymSubscriptionPlanResource;
use App\Models\Gym;
use App\Models\GymSubscriptionPlan;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GymSubscriptionPlanController extends Controller
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
     * Display a listing of the resource.
     *
     * @param  \App\Models\Gym  $gym
     * @return \Illuminate\Http\Response
     */
    public function index(Gym $gym)
    {
        $plans = $gym->subscriptionPlans()->when(request()->has('active'), function($query) {
            return $query->where('is_active', request()->boolean('active'));
        })->get();

        return GymSubscriptionPlanResource::collection($plans);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Gym  $gym
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, Gym $gym)
    {
        // Check if gym has an active subscription
        if (!$this->subscriptionService->hasActiveSubscription($gym->id)) {
            return response()->json([
                'error' => 'Gym does not have an active subscription to create client plans'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'billing_cycle' => 'required|in:monthly,quarterly,annual',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $plan = $this->subscriptionService->createGymPlan($gym, $validator->validated());
            return new GymSubscriptionPlanResource($plan);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Gym  $gym
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Gym $gym, $id)
    {
        $plan = $gym->subscriptionPlans()->findOrFail($id);
        return new GymSubscriptionPlanResource($plan);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Gym  $gym
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Gym $gym, $id)
    {
        $plan = $gym->subscriptionPlans()->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'price' => 'numeric|min:0',
            'billing_cycle' => 'in:monthly,quarterly,annual',
            'features' => 'nullable|array',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $plan->update($validator->validated());

        return new GymSubscriptionPlanResource($plan);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Gym  $gym
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Gym $gym, $id)
    {
        $plan = $gym->subscriptionPlans()->findOrFail($id);

        // Check if the plan has active subscriptions
        if ($plan->clientSubscriptions()->where('status', 'active')->exists()) {
            return response()->json([
                'error' => 'Cannot delete a plan that has active client subscriptions'
            ], 409);
        }

        $plan->delete();

        return response()->json(null, 204);
    }

    /**
     * Get public plans available for clients.
     *
     * @param  \App\Models\Gym  $gym
     * @return \Illuminate\Http\Response
     */
    public function publicPlans(Gym $gym)
    {
        $plans = $gym->subscriptionPlans()
            ->where('is_active', true)
            ->get();

        return GymSubscriptionPlanResource::collection($plans);
    }
}