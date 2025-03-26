<?php

namespace App\Services;

use App\Models\AssessmentSession;
use App\Models\User;
use App\Config\DietAssessmentFlow;
use Illuminate\Support\Facades\Log;

class WebAssessmentService
{
    protected $assessmentFlow;

    /**
     * Load assessment flow based on type
     * 
     * @param string $level Assessment level
     * @param string $lang Language code
     * @return array The loaded assessment flow
     */
    public function loadAssessmentFlow($level = 'moderate', $lang = 'en')
    {
        $this->assessmentFlow = [
            'phases' => DietAssessmentFlow::getPhases($lang),
            'questions' => DietAssessmentFlow::getQuestions($level, $lang)
        ];

        return $this->assessmentFlow;
    }

    /**
     * Get assessment status for a user
     * 
     * @param User $user
     * @return array Status information
     */
    public function getAssessmentStatus(User $user)
    {
        // Check for active or incomplete assessments
        $activeSession = AssessmentSession::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        // Check for completed assessments
        $completedSession = AssessmentSession::where('user_id', $user->id)
            ->where('status', 'completed')
            ->latest()
            ->first();

        if ($activeSession) {
            // Calculate completion percentage
            $this->loadAssessmentFlow($activeSession->assessment_type ?? 'moderate');

            $totalQuestions = count($this->assessmentFlow['questions']);
            $answeredQuestions = count(array_filter(array_keys($activeSession->responses ?? []), function ($key) {
                return !str_starts_with($key, '_');
            }));

            $completionPercentage = round(($answeredQuestions / $totalQuestions) * 100);

            return [
                'has_active_assessment' => true,
                'has_completed_assessment' => false,
                'session_id' => $activeSession->id,
                'assessment_type' => $activeSession->assessment_type,
                'current_phase' => $activeSession->current_phase,
                'current_question' => $activeSession->current_question,
                'completion_percentage' => $completionPercentage,
                'last_updated' => $activeSession->updated_at
            ];
        } else if ($completedSession) {
            return [
                'has_active_assessment' => false,
                'has_completed_assessment' => true,
                'session_id' => $completedSession->id,
                'assessment_type' => $completedSession->assessment_type,
                'completed_at' => $completedSession->completed_at
            ];
        } else {
            return [
                'has_active_assessment' => false,
                'has_completed_assessment' => false
            ];
        }
    }

    /**
     * Start a new assessment
     * 
     * @param User $user
     * @param string $assessmentType
     * @param bool $abandonExisting
     * @return array Session and question information
     */
    public function startAssessment(User $user, string $assessmentType, bool $abandonExisting = false)
    {
        // Check for existing active session
        $existingSession = AssessmentSession::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingSession) {
            // Option to abandon existing session
            if ($abandonExisting) {
                $existingSession->status = 'abandoned';
                $existingSession->save();
            } else {
                return [
                    'error' => true,
                    'message' => 'You already have an active assessment session',
                    'session_id' => $existingSession->id
                ];
            }
        }

        // Create new session
        $session = new AssessmentSession();
        $session->user_id = $user->id;
        $session->assessment_type = $assessmentType;
        $session->current_phase = 1;
        $session->status = 'in_progress';
        $session->started_at = now();
        $session->responses = [];
        $session->save();

        // Load assessment flow
        $this->loadAssessmentFlow($assessmentType, $user->language ?? 'en');

        // Get first question ID
        $firstPhase = $this->assessmentFlow['phases'][1] ?? null;
        $firstQuestionId = $firstPhase ? $firstPhase['first_question'] : 'age';

        $session->current_question = $firstQuestionId;
        $session->save();

        // Get question details
        $questionDetails = $this->getQuestionDetails($firstQuestionId);

        return [
            'error' => false,
            'message' => 'Assessment started successfully',
            'session_id' => $session->id,
            'current_question' => $questionDetails
        ];
    }

    /**
     * Get details for a specific question
     * 
     * @param string $questionId
     * @return array|null Question details
     */
    public function getQuestionDetails($questionId)
    {
        $question = $this->assessmentFlow['questions'][$questionId] ?? null;

        if (!$question) {
            return null;
        }

        // Return only data-centric information about the question
        return [
            'question_id' => $questionId,
            'prompt' => $question['prompt'],
            'validation' => $question['validation'] ?? null,
            'options' => $question['options'] ?? null,
            'multiple' => $question['multiple'] ?? false,
            'phase' => $question['phase'] ?? 1,
            // Include contextual information
            'header' => $question['header'] ?? null,
            'body' => $question['body'] ?? null,
        ];
    }

    /**
     * Submit a response to a question
     * 
     * @param AssessmentSession $session
     * @param string $questionId
     * @param mixed $response
     * @return array Result information
     */
    public function submitResponse(AssessmentSession $session, string $questionId, $response)
    {
        // Load assessment flow
        $this->loadAssessmentFlow(
            $session->assessment_type,
            User::find($session->user_id)->language ?? 'en'
        );

        // Get question details
        $question = $this->assessmentFlow['questions'][$questionId] ?? null;
        if (!$question) {
            return [
                'error' => true,
                'message' => 'Question not found in assessment flow'
            ];
        }

        // Validate response based on data types
        if (!$this->validateResponse($response, $question)) {
            return [
                'error' => true,
                'message' => 'Invalid response format',
                'validation' => $question['validation'] ?? null
            ];
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

            return [
                'error' => false,
                'message' => 'Assessment completed successfully',
                'session_id' => $session->id,
                'is_complete' => true
            ];
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

            return [
                'error' => false,
                'message' => 'Response saved successfully',
                'session_id' => $session->id,
                'is_complete' => false,
                'next_question' => $nextQuestionDetails
            ];
        }
    }

    /**
     * Validate response based on data types and validation rules
     * 
     * @param mixed $response
     * @param array $question
     * @return bool
     */
    public function validateResponse($response, array $question)
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
                        if (!in_array($value, $allowedValues)) {
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
    public function getNextQuestion(array $question, $response)
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
                // If condition is a special function
                else if (is_string($condition) && strpos($condition, 'has') === 0) {
                    // Handle special functions like hasOrganRecovery
                    // This is a simplification; actual implementation would check response data
                    if (
                        strpos($condition, 'hasOrganRecovery') === 0 &&
                        (is_array($response) && in_array('14', $response)) || $response === '14'
                    ) {
                        return $conditional['next'];
                    }

                    if (
                        strpos($condition, 'hasPostSurgery') === 0 &&
                        (is_array($response) && in_array('15', $response)) || $response === '15'
                    ) {
                        return $conditional['next'];
                    }

                    if (
                        strpos($condition, 'hasOtherAllergies') === 0 &&
                        (is_array($response) && in_array('13', $response)) || $response === '13'
                    ) {
                        return $conditional['next'];
                    }
                }
            }

            // If no conditions matched, use default
            return $question['next_conditional']['default'] ?? null;
        }

        // Simple next question
        return $question['next'] ?? null;
    }

    /**
     * Generate a summary of completed assessment
     * 
     * @param AssessmentSession $session
     * @return array Assessment summary
     */
    public function getAssessmentSummary(AssessmentSession $session)
    {
        $responses = $session->responses ?? [];
        $summary = [];

        // Key fields to include in summary
        $keyFields = [
            'age' => 'Age',
            'gender' => 'Gender',
            'height' => 'Height',
            'current_weight' => 'Current weight',
            'target_weight' => 'Target weight',
            'activity_level' => 'Activity level',
            'diet_type' => 'Diet type',
            'primary_goal' => 'Primary goal'
        ];

        foreach ($keyFields as $field => $label) {
            if (isset($responses[$field])) {
                $summary[$field] = [
                    'label' => $label,
                    'value' => $responses[$field]
                ];
            }
        }

        return [
            'session_id' => $session->id,
            'assessment_type' => $session->assessment_type,
            'completed_at' => $session->completed_at,
            'summary' => $summary
        ];
    }
}