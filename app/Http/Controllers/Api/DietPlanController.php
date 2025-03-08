<?php

// app/Http/Controllers/Api/DietPlanController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DietPlanResource;
use App\Http\Resources\MealPlanResource;
use App\Models\DietPlan;
use App\Models\User;
use App\Models\AssessmentSession;
use App\Services\SubscriptionFeatureService;
use App\Services\AIServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class DietPlanController extends Controller
{
    protected $subscriptionFeatureService;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\SubscriptionFeatureService  $subscriptionFeatureService
     * @return void
     */
    public function __construct(SubscriptionFeatureService $subscriptionFeatureService = null)
    {
        $this->subscriptionFeatureService = $subscriptionFeatureService ?? app(SubscriptionFeatureService::class);
    }

    /**
     * Display a listing of the diet plans.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('admin')) {
            // Admins can see all diet plans
            $dietPlans = DietPlan::with(['client', 'creator']) // Ensure client and creator are loaded
                ->when($request->has('client_id'), function ($query) use ($request) {
                    return $query->where('client_id', $request->client_id);
                })
                ->when($request->has('status'), function ($query) use ($request) {
                    return $query->where('status', $request->status);
                })
                ->paginate(10);
        } else if ($user->hasRole(['gym_admin', 'trainer', 'dietitian'])) {
            // Gym staff can see diet plans for clients in their gyms
            $gymIds = $user->gyms()->pluck('gyms.id');

            $clientIds = User::whereHas('gyms', function ($query) use ($gymIds) {
                $query->whereIn('gyms.id', $gymIds);
            })
                ->whereHas('roles', function ($query) {
                    $query->where('name', 'client');
                })
                ->pluck('id');

            $dietPlans = DietPlan::with(['client', 'creator']) // Ensure relationships are loaded
                ->whereIn('client_id', $clientIds)
                ->when($request->has('client_id'), function ($query) use ($request) {
                    return $query->where('client_id', $request->client_id);
                })
                ->when($request->has('status'), function ($query) use ($request) {
                    return $query->where('status', $request->status);
                })
                ->paginate(10);
        } else {
            // Clients can only see their own diet plans
            $dietPlans = DietPlan::with(['client', 'creator']) // Ensure relationships are loaded
                ->where('client_id', $user->id)
                ->when($request->has('status'), function ($query) use ($request) {
                    return $query->where('status', $request->status);
                })
                ->paginate(10);
        }

        return DietPlanResource::collection($dietPlans);
    }

    /**
     * Store a newly created diet plan in storage.
     */
    public function store(Request $request)
    {
        // 🔹 Check permission
        if (!$request->user()->can('create_diet_plans')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 🔹 Validate input
        $validated = $request->validate([
            'client_id' => 'required|exists:users,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'daily_calories' => 'nullable|integer|min:500|max:10000',
            'protein_grams' => 'nullable|integer|min:10|max:500',
            'carbs_grams' => 'nullable|integer|min:10|max:1000',
            'fats_grams' => 'nullable|integer|min:5|max:500',
            'status' => ['nullable', Rule::in(['active', 'inactive', 'completed'])],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'use_ai' => 'boolean',
        ]);

        // 🔹 Ensure client exists and is a valid client role
        $client = User::findOrFail($validated['client_id']);
        if (!$client->hasRole('client')) {
            return response()->json(['message' => 'Invalid client ID'], 403);
        }

        // 🔹 Get client's gym
        $gym = $client->gyms()->first();
        if (!$gym) {
            return response()->json(['error' => 'Client is not associated with any gym'], 400);
        }

        // 🔹 Check if user can manage this client
        if (!$this->canManageClient($request->user(), $client)) {
            return response()->json(['message' => 'Unauthorized to create a diet plan for this client'], 403);
        }

        // 🔹 Ensure the client has an active gym subscription
        if (!$client->hasActiveSubscriptionToGym($gym->id)) {
            return response()->json(['error' => 'Client does not have an active subscription'], 403);
        }

        // 🔹 Check subscription feature access for diet plans
        if (!$this->subscriptionFeatureService->hasAccess($gym->id, 'max_diet_plans')) {
            return response()->json(['error' => 'Diet plan creation is not available in current subscription'], 403);
        }

        // 🔹 Check if client has exceeded diet plan limits
        $existingPlanCount = DietPlan::where('client_id', $client->id)->count();
        if (!$this->subscriptionFeatureService->hasFeatureAndAvailableUsage($gym->id, 'max_diet_plans', $existingPlanCount + 1)) {
            return response()->json(['error' => 'Maximum number of diet plans per client reached for this subscription'], 403);
        }

        // 🔹 Determine whether AI should be used
        $useAI = $validated['use_ai'] ?? false;
        if (!isset($validated['use_ai']) && $gym) {
            $useAI = $gym->ai_enabled; // Default to gym settings
        }

        // 🔹 Ensure AI is enabled before proceeding
        if ($useAI && !$gym->ai_enabled) {
            return response()->json(['error' => 'AI diet plan generation is not enabled for this gym'], 403);
        }

        if ($useAI) {
            // 🔹 Create an AI-based assessment session
            $assessmentSession = AssessmentSession::create([
                'user_id' => $client->id,
                'current_phase' => 8,
                'current_question' => 'completed',
                'responses' => [
                    'use_ai' => true,
                    'source' => 'manual_creation',
                    'creator_id' => $request->user()->id,
                ],
                'status' => 'completed',
                'started_at' => now(),
                'completed_at' => now(),
            ]);

            if (!$assessmentSession) {
                return response()->json(['error' => 'Failed to create assessment session'], 500);
            }

            // 🔹 Create AI Service
            try {
                $aiService = AIServiceFactory::create($gym);
                if (!$aiService) {
                    throw new \Exception('AI service initialization failed');
                }

                // 🔹 Generate diet plan using AI
                $dietPlan = $aiService->generateDietPlan($assessmentSession);
                if (!$dietPlan) {
                    throw new \Exception('AI diet plan generation failed');
                }

                // 🔹 Update AI-generated diet plan with manual input
                $dietPlan->update([
                    'title' => $validated['title'],
                    'description' => $validated['description'] ?? $dietPlan->description,
                    'daily_calories' => $validated['daily_calories'] ?? $dietPlan->daily_calories,
                    'protein_grams' => $validated['protein_grams'] ?? $dietPlan->protein_grams,
                    'carbs_grams' => $validated['carbs_grams'] ?? $dietPlan->carbs_grams,
                    'fats_grams' => $validated['fats_grams'] ?? $dietPlan->fats_grams,
                    'status' => $validated['status'] ?? 'active',
                    'start_date' => $validated['start_date'] ?? now(),
                    'end_date' => $validated['end_date'] ?? now()->addMonths(3),
                ]);

                // 🔹 Increment gym feature usage
                $this->subscriptionFeatureService->incrementUsage($gym->id, 'max_diet_plans');

                return new DietPlanResource($dietPlan->load(['client', 'creator']));
            } catch (\Exception $e) {
                Log::error('AI diet plan generation failed', [
                    'error' => $e->getMessage(),
                    'user_id' => $client->id
                ]);

                return response()->json([
                    'message' => 'Error generating AI diet plan',
                    'error' => $e->getMessage()
                ], 500);
            }
        } else {
            // 🔹 Create manual diet plan
            $dietPlan = new DietPlan($validated);
            $dietPlan->created_by = $request->user()->id;
            $dietPlan->status = $validated['status'] ?? 'active';
            $dietPlan->save();

            // 🔹 Increment gym feature usage
            $this->subscriptionFeatureService->incrementUsage($gym->id, 'max_diet_plans');

            return new DietPlanResource($dietPlan->load(['client', 'creator']));
        }
    }


    /**
     * Display the specified diet plan.
     */
    public function show(Request $request, DietPlan $dietPlan)
    {
        // Check if user can view this diet plan
        if (!$this->canViewDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $dietPlan->load(['client', 'creator', 'mealPlans.meals']);

        return new DietPlanResource($dietPlan);
    }

    /**
     * Update the specified diet plan in storage.
     */
    public function update(Request $request, DietPlan $dietPlan)
    {
        // Check if user can update this diet plan
        if (!$this->canUpdateDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'daily_calories' => 'nullable|integer|min:500|max:10000',
            'protein_grams' => 'nullable|integer|min:10|max:500',
            'carbs_grams' => 'nullable|integer|min:10|max:1000',
            'fats_grams' => 'nullable|integer|min:5|max:500',
            'status' => ['nullable', Rule::in(['active', 'inactive', 'completed'])],
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $dietPlan->update($validated);

        return new DietPlanResource($dietPlan->load(['client', 'creator']));
    }

    /**
     * Remove the specified diet plan from storage.
     */
    public function destroy(Request $request, DietPlan $dietPlan)
    {
        // Check if user can delete this diet plan
        if (!$this->canUpdateDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $dietPlan->delete();

        return response()->json(['message' => 'Diet plan deleted successfully']);
    }

    /**
     * Get meal plans for a diet plan.
     */
    public function mealPlans(Request $request, DietPlan $dietPlan)
    {
        // Check if user can view this diet plan
        if (!$this->canViewDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mealPlans = $dietPlan->mealPlans()->with('meals')->get();

        return MealPlanResource::collection($mealPlans);
    }

    /**
     * Duplicate a diet plan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\DietPlan  $dietPlan
     * @return \Illuminate\Http\Response
     */
    public function duplicate(Request $request, DietPlan $dietPlan)
    {
        $validator = Validator::make($request->all(), [
            'new_title' => 'sometimes|string|max:255',
            'client_id' => 'sometimes|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Default to same client if not specified
        $clientId = $request->client_id ?? $dietPlan->client_id;

        // Get client and their gym
        $client = User::findOrFail($clientId);
        $gym = $client->gyms()->first();

        if (!$gym) {
            return response()->json(['error' => 'Client not associated with any gym'], 400);
        }

        // Count existing diet plans for the client
        $existingPlanCount = DietPlan::where('client_id', $clientId)->count();

        // Check if limit reached
        if (!$this->subscriptionFeatureService->hasFeatureAndAvailableUsage($gym->id, 'max_diet_plans', $existingPlanCount + 1)) {
            return response()->json(['error' => 'Maximum number of diet plans per client reached for this subscription'], 403);
        }

        // Create new diet plan with duplicated data
        $newDietPlan = $dietPlan->replicate();
        $newDietPlan->title = $request->new_title ?? $dietPlan->title . ' (Copy)';
        $newDietPlan->client_id = $clientId;
        $newDietPlan->start_date = $request->start_date;
        $newDietPlan->end_date = $request->end_date;
        $newDietPlan->created_at = now();
        $newDietPlan->updated_at = now();
        $newDietPlan->save();

        // Duplicate meal plans and meals
        foreach ($dietPlan->mealPlans as $mealPlan) {
            $newMealPlan = $mealPlan->replicate();
            $newMealPlan->diet_plan_id = $newDietPlan->id;
            $newMealPlan->save();

            foreach ($mealPlan->meals as $meal) {
                $newMeal = $meal->replicate();
                $newMeal->meal_plan_id = $newMealPlan->id;
                $newMeal->save();
            }
        }

        // Increment feature usage
        $this->subscriptionFeatureService->incrementUsage($gym->id, 'max_diet_plans');

        return new DietPlanResource($newDietPlan->load(['client', 'creator', 'mealPlans.meals']));
    }


    /**
     * Check if user can manage (create/edit diet plans for) a client.
     */
    private function canManageClient(User $user, User $client)
    {
        // Admin can manage any client
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check if client has role 'client'
        if (!$client->hasRole('client')) {
            return false;
        }

        // Gym admins, trainers, and dietitians can manage clients in their gym
        if ($user->hasRole(['gym_admin', 'trainer', 'dietitian'])) {
            $userGyms = $user->gyms()->pluck('gyms.id')->toArray();
            $clientGyms = $client->gyms()->pluck('gyms.id')->toArray();

            // Check if there's an overlap in gyms
            return count(array_intersect($userGyms, $clientGyms)) > 0;
        }

        return false;
    }

    /**
     * Check if user can view a diet plan.
     */
    private function canViewDietPlan(User $user, DietPlan $dietPlan)
    {
        // Admin can view any diet plan
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can view their own diet plans
        if ($dietPlan->client_id === $user->id) {
            return true;
        }

        // Creator can view the diet plan
        if ($dietPlan->created_by === $user->id) {
            return true;
        }

        // Gym staff can view diet plans for clients in their gym
        if ($user->hasRole(['gym_admin', 'trainer', 'dietitian'])) {
            $client = User::find($dietPlan->client_id);

            if ($client) {
                return $this->canManageClient($user, $client);
            }
        }

        return false;
    }

    /**
     * Check if user can update a diet plan.
     */
    private function canUpdateDietPlan(User $user, DietPlan $dietPlan)
    {
        // Admin can update any diet plan
        if ($user->hasRole('admin')) {
            return true;
        }

        // Creator can update the diet plan
        if ($dietPlan->created_by === $user->id) {
            return true;
        }

        // Gym staff can update diet plans for clients in their gym if they have permission
        if ($user->hasRole(['gym_admin', 'dietitian']) && $user->can('edit_diet_plans')) {
            $client = User::find($dietPlan->client_id);

            if ($client) {
                return $this->canManageClient($user, $client);
            }
        }

        return false;
    }
}