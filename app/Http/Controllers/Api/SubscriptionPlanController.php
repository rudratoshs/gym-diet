<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use App\Models\SubscriptionFeature;
use App\Models\SubscriptionPlanFeature;
use App\Services\Payment\PaymentServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use DB;
use Illuminate\Support\Facades\Log;

class SubscriptionPlanController extends Controller
{
    protected $paymentFactory;

    public function __construct(PaymentServiceFactory $paymentFactory)
    {
        $this->paymentFactory = $paymentFactory;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Check if user is admin
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $plans = SubscriptionPlan::when($request->has('active'), function ($query) use ($request) {
            return $query->where('is_active', $request->boolean('active'));
        })->get();

        return SubscriptionPlanResource::collection($plans);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Check if user is admin
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:subscription_plans',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'payment_provider' => 'required|in:stripe,razorpay',
            'plan_type' => 'required|in:one_time,recurring',
            'plans' => 'required|array|min:1',
            'plans.*.interval' => 'required_if:plan_type,recurring|in:day,week,month,year',
            'plans.*.interval_count' => 'required_if:plan_type,recurring|integer|min:1',
            'plans.*.duration_days' => 'required_if:plan_type,one_time|integer|min:1',
            'plans.*.name' => 'required|string',
            'plans.*.price' => 'required|numeric|min:0',
            'features' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Start a database transaction
            DB::beginTransaction();

            // Get payment service
            $paymentService = $this->paymentFactory->create($request->payment_provider);

            // Create plans in payment provider for each defined plan
            $paymentProviderPlans = [];

            foreach ($request->plans as $planItem) {
                $planData = [
                    'name' => $planItem['name'],
                    'description' => $request->description,
                    'amount' => $planItem['price'] * 100, // Convert to cents/paise
                    'currency' => 'INR',
                    'is_recurring' => $request->plan_type === 'recurring'
                ];

                if ($request->plan_type === 'recurring') {
                    $planData['interval'] = $planItem['interval'];
                    $planData['interval_count'] = $planItem['interval_count'];
                    $key = $planItem['interval'] . '_' . $planItem['interval_count'];
                } else {
                    $planData['duration_days'] = $planItem['duration_days'];
                    $key = 'one_time_' . $planItem['duration_days'] . 'days';
                }

                $planId = $paymentService->createPlanInProvider($planData);
                $paymentProviderPlans[$key] = [
                    'id' => $planId,
                    'name' => $planItem['name'],
                    'price' => $planItem['price'],
                    'type' => $request->plan_type,
                    'details' => $request->plan_type === 'recurring' ?
                        ['interval' => $planItem['interval'], 'interval_count' => $planItem['interval_count']] :
                        ['duration_days' => $planItem['duration_days']]
                ];
            }

            // Create subscription plan
            $plan = SubscriptionPlan::create([
                'name' => $request->name,
                'code' => $request->code,
                'description' => $request->description,
                'is_active' => $request->input('is_active', true),
                'payment_provider' => $request->payment_provider,
                'plan_type' => $request->plan_type,
                'payment_provider_plans' => $paymentProviderPlans
            ]);

            // Attach features to the plan - convert from flat structure to relationship
            foreach ($request->features as $featureCode => $featureValue) {
                // Find the feature by code
                $feature = SubscriptionFeature::where('code', $featureCode)->first();

                if ($feature) {
                    // For boolean features
                    if ($feature->type === 'boolean') {
                        $plan->features()->attach($feature->id, [
                            'value' => $featureValue ? true : false,
                            'limit' => null
                        ]);
                    }
                    // For numeric features
                    else if ($feature->type === 'numeric') {
                        $plan->features()->attach($feature->id, [
                            'value' => null,
                            'limit' => is_numeric($featureValue) ? $featureValue : null
                        ]);
                    }
                }
            }

            // Commit the transaction
            DB::commit();

            // Load the features relationship for the response
            $plan->load('features');

            return new SubscriptionPlanResource($plan);

        } catch (\Exception $e) {
            // Rollback the transaction in case of error
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $plan = SubscriptionPlan::findOrFail($id);
        return new SubscriptionPlanResource($plan);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Check if user is admin
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $plan = SubscriptionPlan::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'code' => 'string|max:50|unique:subscription_plans,code,' . $id,
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'features' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Update only the allowed fields
        $plan->fill($request->only([
            'name',
            'code',
            'description',
            'is_active',
            'features'
        ]));

        $plan->save();

        return new SubscriptionPlanResource($plan);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        // Check if user is admin
        if (!request()->user()->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $plan = SubscriptionPlan::findOrFail($id);

        // Check if the plan is in use
        if ($plan->subscriptions()->exists()) {
            return response()->json([
                'error' => 'Cannot delete a plan that has active subscriptions'
            ], 409);
        }

        $plan->delete();

        return response()->json(null, 204);
    }

    /**
     * Display a listing of public plans.
     *
     * @return \Illuminate\Http\Response
     */
    public function publicPlans()
    {
        $plans = SubscriptionPlan::where('is_active', true)->get();
        return SubscriptionPlanResource::collection($plans);
    }
}