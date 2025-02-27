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
use Illuminate\Support\Facades\Log;

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

        // Create new assessment session with more detailed initial state
        $session = new AssessmentSession();
        $session->user_id = $user->id;
        $session->current_phase = 1; // Start with Phase 1
        $session->current_question = 'age'; // Start with age question
        $session->responses = []; // Initialize empty responses
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

        // Add phase information to the response
        $phases = [
            1 => 'Basic Information',
            2 => 'Health Assessment',
            3 => 'Diet Preferences',
            4 => 'Lifestyle',
            5 => 'Goals',
            6 => 'Plan Customization'
        ];

        $assessmentData = new AssessmentSessionResource($assessment);
        $assessmentData->additional([
            'phase_name' => $phases[$assessment->current_phase] ?? 'Unknown Phase',
            'total_phases' => count($phases),
            'completion_percentage' => $this->calculateCompletionPercentage($assessment)
        ]);

        return $assessmentData;
    }

    /**
     * Calculate assessment completion percentage
     */
    private function calculateCompletionPercentage(AssessmentSession $assessment)
    {
        // Define expected number of questions per phase
        $questionsPerPhase = [
            1 => 5, // Basic Information
            2 => 4, // Health Assessment
            3 => 4, // Diet Preferences 
            4 => 4, // Lifestyle
            5 => 3, // Goals
            6 => 1  // Plan Customization
        ];

        $totalQuestions = array_sum($questionsPerPhase);
        $completedQuestions = 0;

        // Count completed questions based on responses
        $responses = $assessment->responses ?? [];
        $completedQuestions = count($responses);

        // Calculate percentage
        return min(100, round(($completedQuestions / $totalQuestions) * 100));
    }

    /**
     * Update assessment responses
     */
    public function update(Request $request, AssessmentSession $assessment)
    {
        // Check permission
        if (!$request->user()->can('view_clients') && $request->user()->id !== $assessment->user_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Validate input with more comprehensive validation
        $validated = $request->validate([
            'current_phase' => 'sometimes|required|integer|min:1|max:6',
            'current_question' => 'sometimes|required|string',
            'responses' => 'sometimes|required|array',
            'responses.*.question_id' => 'sometimes|required|string',
            'responses.*.answer' => 'sometimes|required',
        ]);

        // Check if assessment is in progress
        if ($assessment->status !== 'in_progress') {
            return response()->json([
                'message' => 'Assessment is not in progress',
                'assessment' => new AssessmentSessionResource($assessment)
            ], 400);
        }

        // Handle batch updates to responses
        if (isset($validated['responses']) && is_array($validated['responses'])) {
            $currentResponses = $assessment->responses ?? [];

            // Process each response in the batch
            foreach ($validated['responses'] as $response) {
                if (isset($response['question_id']) && isset($response['answer'])) {
                    $currentResponses[$response['question_id']] = $response['answer'];
                }
            }

            $validated['responses'] = $currentResponses;
        }

        // Update assessment
        $assessment->fill($validated);
        $assessment->save();

        // Add completion percentage to response
        $assessmentResource = new AssessmentSessionResource($assessment);
        $assessmentResource->additional([
            'completion_percentage' => $this->calculateCompletionPercentage($assessment)
        ]);

        return response()->json([
            'message' => 'Assessment updated successfully',
            'assessment' => $assessmentResource
        ]);
    }

    /**
     * Complete an assessment and generate diet plan
     */
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

        // Validate any final inputs (optional)
        $finalData = $request->validate([
            'final_responses' => 'sometimes|array',
        ]);

        // Merge final responses if provided
        if (isset($finalData['final_responses'])) {
            $responses = $assessment->responses ?? [];
            $assessment->responses = array_merge($responses, $finalData['final_responses']);
        }

        // Mark as completed
        $assessment->status = 'completed';
        $assessment->completed_at = now();
        $assessment->save();

        // Log assessment completion
        Log::info('Assessment completed', [
            'user_id' => $assessment->user_id,
            'assessment_id' => $assessment->id,
            'response_count' => count($assessment->responses ?? []),
        ]);

        // Get the user from the assessment
        $user = User::findOrFail($assessment->user_id);

        // Get the user's gym and create the appropriate AI service
        $gym = $user->gyms()->first();
        $aiService = AIServiceFactory::create($gym);

        try {
            // Generate diet plan with more detailed logging
            Log::info('Generating diet plan', [
                'user_id' => $user->id,
                'assessment_id' => $assessment->id,
                'gym_id' => $gym->id ?? null,
            ]);

            $dietPlan = $aiService->generateDietPlan($assessment);

            Log::info('Diet plan generated successfully', [
                'user_id' => $user->id,
                'diet_plan_id' => $dietPlan->id,
            ]);

            return response()->json([
                'message' => 'Assessment completed and diet plan generated successfully',
                'assessment' => new AssessmentSessionResource($assessment),
                'diet_plan_id' => $dietPlan->id
            ]);
        } catch (\Exception $e) {
            Log::error('Diet plan generation failed', [
                'user_id' => $user->id,
                'assessment_id' => $assessment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

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
            // If no diet plan found, try to generate one now
            try {
                $user = User::findOrFail($assessment->user_id);
                $gym = $user->gyms()->first();
                $aiService = AIServiceFactory::create($gym);

                $dietPlan = $aiService->generateDietPlan($assessment);

                Log::info('Diet plan generated on demand', [
                    'user_id' => $user->id,
                    'diet_plan_id' => $dietPlan->id,
                ]);
            } catch (\Exception $e) {
                Log::error('On-demand diet plan generation failed', [
                    'user_id' => $assessment->user_id,
                    'assessment_id' => $assessment->id,
                    'error' => $e->getMessage()
                ]);

                return response()->json([
                    'message' => 'Could not generate diet plan for this assessment',
                    'error' => $e->getMessage(),
                    'assessment' => new AssessmentSessionResource($assessment)
                ], 500);
            }
        }

        // Load diet plan with meal plans and meals
        $dietPlan->load(['mealPlans.meals']);

        return response()->json([
            'assessment' => new AssessmentSessionResource($assessment),
            'diet_plan' => new DietPlanResource($dietPlan)
        ]);
    }
}