<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssessmentSession;
use App\Models\User;
use App\Config\DietAssessmentFlow;
use App\Services\WebAssessmentService;
use App\Services\AssessmentManagerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class WebAssessmentController extends Controller
{
    protected $assessmentFlow;
    protected $assessmentService;
    protected $managerService;

    public function __construct(WebAssessmentService $assessmentService, AssessmentManagerService $managerService)
    {
        $this->assessmentService = $assessmentService;
        $this->managerService = $managerService;
    }

    /**
     * Get the current assessment status for the user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function status()
    {
        $user = Auth::user();
        $status = $this->assessmentService->getAssessmentStatus($user);

        return response()->json($status);
    }

    public function start(Request $request)
    {
        $request->validate([
            'assessment_type' => 'required|in:quick,moderate,comprehensive'
        ]);

        $user = Auth::user();
        $result = $this->assessmentService->startAssessment(
            $user,
            $request->input('assessment_type'),
            $request->input('abandon_existing', false)
        );

        if (isset($result['error']) && $result['error']) {
            return response()->json([
                'message' => $result['message'],
                'session_id' => $result['session_id'] ?? null
            ], 409);
        }

        return response()->json([
            'message' => $result['message'],
            'session_id' => $result['session_id'],
            'current_question' => $result['current_question']
        ], 201);
    }

    /**
     * Get current question details
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCurrentQuestion(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:assessment_sessions,id'
        ]);

        $sessionId = $request->input('session_id');

        // Get session and check ownership
        $session = AssessmentSession::findOrFail($sessionId);
        if ($session->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to assessment session'
            ], 403);
        }

        if ($session->status !== 'in_progress') {
            return response()->json([
                'message' => 'Assessment is not in progress',
                'status' => $session->status
            ], 400);
        }

        // Load assessment flow and get current question
        $this->loadAssessmentFlow($session->assessment_type);

        $currentQuestionId = $session->current_question;

        $questionDetails = $this->getQuestionDetails($currentQuestionId);

        return response()->json($questionDetails);
    }

    /**
     * Submit assessment response
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitResponse(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:assessment_sessions,id',
            'question_id' => 'required|string',
            'response' => 'required'
        ]);

        $sessionId = $request->input('session_id');
        $questionId = $request->input('question_id');
        $response = $request->input('response');

        // Get session and check ownership
        $session = AssessmentSession::findOrFail($sessionId);
        if ($session->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to assessment session'
            ], 403);
        }

        if ($session->status !== 'in_progress') {
            return response()->json([
                'message' => 'Assessment is not in progress',
                'status' => $session->status
            ], 400);
        }

        // Ensure the submitted question matches the current question
        if ($session->current_question !== $questionId) {
            return response()->json([
                'message' => 'Invalid question ID, expected ' . $session->current_question,
                'expected' => $session->current_question,
                'received' => $questionId
            ], 400);
        }

        // Load assessment flow
        $this->loadAssessmentFlow($session->assessment_type);

        // Get question details
        $question = $this->assessmentFlow['questions'][$questionId] ?? null;
        if (!$question) {
            return response()->json([
                'message' => 'Question not found in assessment flow'
            ], 404);
        }

        // Validate response based on basic data types and validation rules
        if (!$this->validateResponse($response, $question)) {
            return response()->json([
                'message' => 'Invalid response format',
                'validation' => $question['validation'] ?? null
            ], 422);
        }

        // Store response
        $responses = $session->responses ?? [];
        $responses[$questionId] = $response;
        $session->responses = $responses;

        // Determine next question
        $nextQuestionId = $this->getNextQuestion($question, $response);

        if ($nextQuestionId === 'complete') {
            // Complete the assessment
            $session->status = 'completed';
            $session->completed_at = now();
            $session->save();

            return response()->json([
                'message' => 'Assessment completed successfully',
                'session_id' => $session->id,
                'is_complete' => true
            ]);
        } else {
            // Update session with next question
            $session->current_question = $nextQuestionId;

            // Update phase if needed
            $nextQuestion = $this->assessmentFlow['questions'][$nextQuestionId] ?? null;
            if ($nextQuestion && isset($nextQuestion['phase'])) {
                $session->current_phase = $nextQuestion['phase'];
            }

            $session->save();

            // Get next question details
            $nextQuestionDetails = $this->getQuestionDetails($nextQuestionId);

            return response()->json([
                'message' => 'Response saved successfully',
                'session_id' => $session->id,
                'is_complete' => false,
                'next_question' => $nextQuestionDetails
            ]);
        }
    }

    /**
     * Resume an assessment
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resume(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:assessment_sessions,id'
        ]);

        $sessionId = $request->input('session_id');

        // Get session and check ownership
        $session = AssessmentSession::findOrFail($sessionId);
        if ($session->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to assessment session'
            ], 403);
        }

        // Check if session can be resumed
        if ($session->status === 'completed') {
            return response()->json([
                'message' => 'Assessment is already completed',
                'status' => $session->status
            ], 400);
        }

        // Update status if needed
        if ($session->status === 'abandoned') {
            $session->status = 'in_progress';
            $session->save();
        }

        // Load assessment flow and get current question
        $this->loadAssessmentFlow($session->assessment_type);
        $currentQuestionId = $session->current_question;

        $questionDetails = $this->getQuestionDetails($currentQuestionId);

        // Get summary of previously answered questions
        $responses = $session->responses ?? [];
        $answeredQuestions = [];

        foreach ($responses as $key => $value) {
            // Skip internal keys
            if (str_starts_with($key, '_')) {
                continue;
            }

            $question = $this->assessmentFlow['questions'][$key] ?? null;
            if ($question) {
                $answeredQuestions[$key] = [
                    'question' => $question['prompt'] ?? $key,
                    'response' => $value
                ];
            }
        }

        return response()->json([
            'message' => 'Assessment resumed successfully',
            'session_id' => $session->id,
            'current_question' => $questionDetails,
            'previous_responses' => $answeredQuestions
        ]);
    }

    /**
     * Load assessment flow based on type
     * 
     * @param string $level Assessment level
     * @return void
     */
    protected function loadAssessmentFlow($level = 'moderate')
    {
        // Get user language preference
        $userLang = auth()->user()->language ?? 'en';

        // Load questions based on level and language
        $this->assessmentFlow = [
            'phases' => DietAssessmentFlow::getPhases($userLang),
            'questions' => DietAssessmentFlow::getQuestions($level, $userLang)
        ];
    }

    /**
     * Get question details by ID
     * 
     * @param string $questionId
     * @return array|null
     */
    protected function getQuestionDetails($questionId)
    {
        $question = $this->assessmentFlow['questions'][$questionId] ?? null;

        if (!$question) {
            return null;
        }

        // Return data-focused question details
        return [
            'question_id' => $questionId,
            'prompt' => $question['prompt'],
            'validation' => $question['validation'] ?? null,
            'options' => $question['options'] ?? null,
            'multiple' => $question['multiple'] ?? false,
            'phase' => $question['phase'] ?? 1,
            // Include additional context to help frontend but not UI-specific
            'header' => $question['header'] ?? null,
            'body' => $question['body'] ?? null,
        ];
    }

    /**
     * Validate response based on data type and validation rules
     * 
     * @param mixed $response
     * @param array $question
     * @return bool
     */
    protected function validateResponse($response, array $question)
    {
        // Check validation rules if present
        if (isset($question['validation'])) {
            if ($question['validation'] === 'numeric|min:12|max:120') {
                // Age validation
                return is_numeric($response) && (int) $response >= 12 && (int) $response <= 120;
            }
            // Add other validation rules as needed
        }

        // For questions with options
        if (isset($question['options'])) {
            $allowedValues = array_column($question['options'], 'id');

            // For multi-select
            if (isset($question['multiple']) && $question['multiple']) {
                // If response is an array
            if (is_array($response)) {
                foreach ($response as $value) {
                    // Accept predefined options or any string (for custom input)
                    if (!in_array($value, $allowedValues) && !is_string($value)) {
                        return false;
                    }
                }
                return true;
            }
                // If response is a comma-separated string
                else if (is_string($response) && strpos($response, ',') !== false) {
                    $values = array_map('trim', explode(',', $response));
                    foreach ($values as $value) {
                        if (!in_array($value, $allowedValues)) {
                            return false;
                        }
                    }
                    return true;
                }
                return false;
            }

            // For single-select
            return in_array($response, $allowedValues);
        }

        // For simple text/numeric inputs with no specific validation
        return true;
    }

    /**
     * Determine next question based on current question and response
     * 
     * @param array $question Current question details
     * @param mixed $response User's response
     * @return string|null Next question ID or null
     */
    protected function getNextQuestion(array $question, $response)
    {
        // Check for conditional next question
        if (isset($question['next_conditional'])) {
            $conditionals = $question['next_conditional']['conditions'] ?? [];

            foreach ($conditionals as $conditional) {
                $condition = $conditional['condition'] ?? null;

                // Skip if condition is not set
                if ($condition === null) {
                    continue;
                }

                // If condition is a simple value match
                if (is_string($condition) && $response === $condition) {
                    return $conditional['next'];
                }
                // If condition is an array of values to match
                else if (is_array($condition) && in_array($response, $condition)) {
                    return $conditional['next'];
                }
                // If condition is a string containing special function
                else if (is_string($condition) && strpos($condition, 'has') === 0) {
                    // Handle special condition functions like hasOrganRecovery
                    // Simplified for now
                }
            }

            // If no conditions matched, use default
            return $question['next_conditional']['default'] ?? null;
        }

        // Simple next question
        return $question['next'] ?? null;
    }
    /** Delete an assessment session
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function delete(Request $request)
    {
        $request->validate([
            'session_id' => 'required|exists:assessment_sessions,id'
        ]);

        $session = AssessmentSession::findOrFail($request->input('session_id'));

        if ($session->user_id !== Auth::id()) {
            return response()->json([
                'message' => 'Unauthorized access to delete assessment session'
            ], 403);
        }

        $session->delete();

        return response()->json([
            'message' => 'Assessment session deleted successfully'
        ]);
    }
}