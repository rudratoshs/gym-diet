<?php

// app/Http/Controllers/Api/MealPlanController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\MealPlanResource;
use App\Models\DietPlan;
use App\Models\MealPlan;
use App\Models\User;
use App\Models\Gym;
use App\Services\AI\BaseAIService;
use App\Services\SubscriptionFeatureService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class MealPlanController extends Controller
{
    protected $aiService;
    protected $subscriptionFeatureService;

    /**
     * Create a new controller instance.
     *
     * @param  \App\Services\AIService  $aiService
     * @param  \App\Services\SubscriptionFeatureService  $subscriptionFeatureService
     * @return void
     */
    public function __construct(BaseAIService $aiService = null, SubscriptionFeatureService $subscriptionFeatureService = null)
    {
        $this->aiService = $aiService ?? app(BaseAIService::class);
        $this->subscriptionFeatureService = $subscriptionFeatureService ?? app(SubscriptionFeatureService::class);
    }

    /**
     * Store a newly created meal plan in storage.
     */
    public function store(Request $request, DietPlan $dietPlan)
    {
        // Check if user can update this diet plan
        if (!$this->canUpdateDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check gym subscription status
        $client = User::find($dietPlan->client_id);
        $gym = $this->getClientGym($client);

        if (!$gym) {
            return response()->json(['error' => 'Client not associated with any gym'], 400);
        }

        // Check if gym has an active subscription
        if ($gym->subscription_status !== 'active') {
            return response()->json(['error' => 'Gym subscription is not active'], 403);
        }

        $validated = $request->validate([
            'day_of_week' => [
                'required',
                Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']),
                Rule::unique('meal_plans')->where(function ($query) use ($dietPlan) {
                    return $query->where('diet_plan_id', $dietPlan->id);
                }),
            ],
        ]);

        // Check if meal plan already exists for this day
        $existingMealPlan = $dietPlan->mealPlans()->where('day_of_week', $request->day_of_week)->first();
        if ($existingMealPlan) {
            return response()->json(['error' => 'Meal plan for this day already exists'], 422);
        }

        $mealPlan = new MealPlan($validated);
        $mealPlan->diet_plan_id = $dietPlan->id;
        $mealPlan->save();

        return new MealPlanResource($mealPlan);
    }

    /**
     * Display the specified meal plan.
     */
    public function show(Request $request, DietPlan $dietPlan, MealPlan $mealPlan)
    {
        // Check if meal plan belongs to diet plan
        if ($mealPlan->diet_plan_id !== $dietPlan->id) {
            return response()->json(['message' => 'Meal plan not found for this diet plan'], 404);
        }

        // Check if user can view this diet plan
        if (!$this->canViewDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mealPlan->load('meals');

        return new MealPlanResource($mealPlan);
    }

    /**
     * Remove the specified meal plan from storage.
     */
    public function destroy(Request $request, DietPlan $dietPlan, MealPlan $mealPlan)
    {
        // Check if meal plan belongs to diet plan
        if ($mealPlan->diet_plan_id !== $dietPlan->id) {
            return response()->json(['message' => 'Meal plan not found for this diet plan'], 404);
        }

        // Check if user can update this diet plan
        if (!$this->canUpdateDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $mealPlan->delete();

        return response()->json(['message' => 'Meal plan deleted successfully']);
    }

    /**
     * Generate meals for a meal plan using AI.
     */
    public function generateWithAI(Request $request, DietPlan $dietPlan, MealPlan $mealPlan)
    {
        // Check if meal plan belongs to diet plan
        if ($mealPlan->diet_plan_id !== $dietPlan->id) {
            return response()->json(['message' => 'Meal plan not found for this diet plan'], 404);
        }

        // Check if user can update this diet plan
        if (!$this->canUpdateDietPlan($request->user(), $dietPlan)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get client and their gym
        $client = User::find($dietPlan->client_id);
        $gym = $this->getClientGym($client);

        if (!$gym) {
            return response()->json(['error' => 'Client not associated with any gym'], 400);
        }

        // Check if gym has an active subscription
        if ($gym->subscription_status !== 'active') {
            return response()->json(['error' => 'Gym subscription is not active'], 403);
        }

        // Check if AI meal generation is enabled for the gym's subscription
        if (!$this->subscriptionFeatureService->hasAccess($gym->id, 'ai_meal_generation')) {
            return response()->json(['error' => 'AI meal generation not available in current subscription'], 403);
        }

        // Check if the gym has reached its AI meal generation limit
        if (!$this->subscriptionFeatureService->hasRemainingUsage($gym->id, 'ai_meal_generation')) {
            return response()->json(['error' => 'AI meal generation limit reached for this subscription period'], 403);
        }

        // Update meal plan status to indicate processing
        $mealPlan->generation_status = 'processing';
        $mealPlan->save();

        // Get client profile
        $clientProfile = $client->clientProfile;

        if (!$clientProfile) {
            return response()->json(['error' => 'Client profile not found'], 400);
        }

        // Generate meals using AI service
        try {
            // Get assessment responses or use empty array if none
            $responses = [];
            $latestAssessment = $client->assessmentSessions()->where('status', 'completed')->latest()->first();
            if ($latestAssessment) {
                $responses = $latestAssessment->responses ?? [];
            }

            // This would typically be a job that runs in the background
            $this->aiService->generateMealsForDay(
                $mealPlan,
                $dietPlan,
                $clientProfile,
                $responses,
                [
                    'profile' => $clientProfile,
                    'health_conditions' => $clientProfile->health_conditions ?? ['none'],
                    'allergies' => $clientProfile->allergies ?? ['none'],
                    'meal_preferences' => $clientProfile->meal_preferences ?? ['balanced'],
                    'cuisine_preferences' => $clientProfile->cuisine_preferences ?? ['no_preference'],
                ],
                $mealPlan->day_of_week
            );

            // Increment the usage counter for this feature
            $this->subscriptionFeatureService->incrementUsage($gym->id, 'ai_meal_generation');

            // Return updated meal plan with generated meals
            return new MealPlanResource($mealPlan->fresh()->load('meals'));

        } catch (\Exception $e) {
            // Set status back to pending in case of error
            $mealPlan->generation_status = 'failed';
            $mealPlan->save();

            return response()->json([
                'error' => 'Failed to generate meals',
                'message' => $e->getMessage()
            ], 500);
        }
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
     * Get the gym a client belongs to.
     *
     * @param User $client
     * @return Gym|null
     */
    private function getClientGym(User $client)
    {
        if (!$client) {
            return null;
        }

        return $client->gyms()->first();
    }
}