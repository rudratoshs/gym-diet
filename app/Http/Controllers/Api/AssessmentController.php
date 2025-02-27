<?php

// app/Http/Controllers/Api/AssessmentController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssessmentSessionResource;
use App\Http\Resources\DietPlanResource;
use App\Models\AssessmentSession;
use App\Models\User;
use App\Services\AIServiceFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AssessmentController extends Controller
{
    /**
     * Start a new assessment
     */
    public function start(Request $request, User $user)
    {
        // Check permission
        if (!$request->user()->can('view_clients') && $request->user()->id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if user already has an in-progress assessment
        $existingSession = AssessmentSession::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingSession) {
            return response()->json([
                'message' => 'User already has an assessment in progress',
                'assessment' => new AssessmentSessionResource($existingSession)
            ], 409);
        }

        // Create new assessment session
        $session = new AssessmentSession();
        $session->user_id = $user->id;
        $session->current_phase = 1;
        $session->current_question = 'introduction';
        $session->responses = [];
        $session->status = 'in_progress';
        $session->started_at = now();
        $session->save();

        return response()->json([
            'message' => 'Assessment started successfully',
            'assessment' => new AssessmentSessionResource($session)
        ], 201);
    }

    /**
     * Get assessment status
     */
    public function show(Request $request, AssessmentSession $assessment)
    {
        // Check permission
        if (!$request->user()->can('view_clients') && $request->user()->id !== $assessment->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return new AssessmentSessionResource($assessment);
    }

    /**
     * Update assessment responses
     */
    // app/Http/Controllers/Api/AssessmentController.php (continued)
    public function update(Request $request, AssessmentSession $assessment)
    {
        // Check permission
        if (!$request->user()->can('view_clients') && $request->user()->id !== $assessment->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validate input
        $validated = $request->validate([
            'current_phase' => 'sometimes|required|integer|min:1|max:8',
            'current_question' => 'sometimes|required|string',
            'responses' => 'sometimes|required|array',
        ]);

        // Check if assessment is in progress
        if ($assessment->status !== 'in_progress') {
            return response()->json([
                'message' => 'Assessment is not in progress',
                'assessment' => new AssessmentSessionResource($assessment)
            ], 400);
        }

        // Update assessment
        $assessment->fill($validated);
        $assessment->save();

        return response()->json([
            'message' => 'Assessment updated successfully',
            'assessment' => new AssessmentSessionResource($assessment)
        ]);
    }

    public function complete(Request $request, AssessmentSession $assessment)
    {
        // Check permission
        if (!$request->user()->can('view_clients') && $request->user()->id !== $assessment->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if assessment is in progress
        if ($assessment->status !== 'in_progress') {
            return response()->json([
                'message' => 'Assessment is already completed or abandoned',
                'assessment' => new AssessmentSessionResource($assessment)
            ], 400);
        }

        // Mark as completed
        $assessment->status = 'completed';
        $assessment->completed_at = now();
        $assessment->save();

        // Get the user from the assessment
        $user = User::findOrFail($assessment->user_id);

        // Get the user's gym and create the appropriate AI service
        $gym = $user->gyms()->first();
        $aiService = AIServiceFactory::create($gym);

        try {
            $dietPlan = $aiService->generateDietPlan($assessment);

            return response()->json([
                'message' => 'Assessment completed and diet plan generated successfully',
                'assessment' => new AssessmentSessionResource($assessment),
                'diet_plan_id' => $dietPlan->id
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Assessment completed but diet plan generation failed',
                'error' => $e->getMessage(),
                'assessment' => new AssessmentSessionResource($assessment)
            ], 500);
        }
    }

    /**
     * Get assessment result
     */
    public function result(Request $request, AssessmentSession $assessment)
    {
        // Check permission
        if (!$request->user()->can('view_clients') && $request->user()->id !== $assessment->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Check if assessment is completed
        if ($assessment->status !== 'completed') {
            return response()->json([
                'message' => 'Assessment is not completed',
                'assessment' => new AssessmentSessionResource($assessment)
            ], 400);
        }

        // Get latest diet plan generated from this assessment
        $dietPlan = $assessment->user->dietPlans()
            ->where('created_at', '>=', $assessment->completed_at)
            ->latest()
            ->first();

        if (!$dietPlan) {
            return response()->json([
                'message' => 'No diet plan found for this assessment',
                'assessment' => new AssessmentSessionResource($assessment)
            ], 404);
        }

        // Load diet plan with meal plans and meals
        $dietPlan->load(['mealPlans.meals']);

        return response()->json([
            'assessment' => new AssessmentSessionResource($assessment),
            'diet_plan' => new DietPlanResource($dietPlan)
        ]);
    }
}