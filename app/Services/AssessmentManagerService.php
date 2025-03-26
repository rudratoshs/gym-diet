<?php

namespace App\Services;

use App\Models\AssessmentSession;
use App\Models\ClientProfile;
use App\Models\User;
use App\Config\DietAssessmentFlow;
use Illuminate\Support\Facades\Log;

class AssessmentManagerService
{
    /**
     * Convert assessment responses to client profile data
     * 
     * @param AssessmentSession $session
     * @return array Formatted profile data
     */
    public function mapAssessmentToProfile(AssessmentSession $session)
    {
        $responses = $session->responses ?? [];
        $profileData = [];

        // Direct field mappings (assessment key => profile field)
        $directMappings = [
            'age' => 'age',
            'gender' => 'gender',
            'height' => 'height',
            'current_weight' => 'current_weight',
            'target_weight' => 'target_weight',
            'body_type' => 'body_type',
            'activity_level' => 'activity_level',
            'diet_type' => 'diet_type',
            'primary_goal' => 'primary_goal',
            'health_details' => 'health_details',
            'stress_sleep' => 'stress_sleep',
            'exercise_routine' => 'exercise_routine',
            'cooking_capability' => 'cooking_capability',
            'daily_schedule' => 'daily_schedule',
            'meal_timing' => 'meal_timing',
            'fasting_details' => 'fasting_details',
            'water_intake' => 'water_intake',
            'cooking_time' => 'cooking_time',
            'cooking_style' => 'cooking_style',
            'grocery_access' => 'grocery_access',
            'meal_budget' => 'meal_budget',
            'exercise_timing' => 'exercise_timing',
            'sleep_hours' => 'sleep_hours',
            'commitment_level' => 'commitment_level',
            'motivation' => 'motivation',
            'past_attempts' => 'past_attempts',
            'timeline' => 'timeline',
            'detail_level' => 'detail_level',
            'recipe_complexity' => 'recipe_complexity',
            'meal_variety' => 'meal_variety',
            'country' => 'country',
            'state' => 'state',
            'city' => 'city',
            'additional_requests' => 'additional_requests'
        ];

        // JSON field mappings (assessment key => profile JSON field)
        $jsonMappings = [
            'health_conditions' => 'health_conditions',
            'allergies' => 'allergies',
            'recovery_needs' => 'recovery_needs',
            'organ_recovery_focus' => 'organ_recovery_focus',
            'cuisine_preferences' => 'cuisine_preferences',
            'food_restrictions' => 'food_restrictions',
            'meal_preferences' => 'meal_preferences',
            'favorite_foods' => 'favorite_foods',
            'disliked_foods' => 'disliked_foods',
            'medications' => 'medications',
            'past_medical_history' => 'past_medical_history',
            'religion_diet' => 'religion_diet'
        ];

        // Process direct mappings
        foreach ($directMappings as $responseKey => $profileField) {
            if (isset($responses[$responseKey])) {
                $profileData[$profileField] = $responses[$responseKey];
            }
        }

        // Process JSON field mappings
        foreach ($jsonMappings as $responseKey => $profileField) {
            if (isset($responses[$responseKey])) {
                $value = $responses[$responseKey];

                // If the value is already an array, use it
                if (is_array($value)) {
                    $profileData[$profileField] = $value;
                }
                // If it's a comma-separated string, convert to array
                else if (is_string($value) && strpos($value, ',') !== false) {
                    $profileData[$profileField] = array_map('trim', explode(',', $value));
                }
                // Otherwise, make it a single-item array
                else {
                    $profileData[$profileField] = [$value];
                }
            }
        }

        // Handle special fields
        if (isset($responses['medication_details'])) {
            $profileData['medication_details'] = $responses['medication_details'];
        }

        if (isset($responses['organ_recovery_details'])) {
            $profileData['organ_recovery_details'] = $responses['organ_recovery_details'];
        }

        return $profileData;
    }

    /**
     * Update client profile with assessment data
     * 
     * @param AssessmentSession $session
     * @return ClientProfile
     */
    public function updateProfileFromAssessment(AssessmentSession $session)
    {
        $user = User::findOrFail($session->user_id);
        $profileData = $this->mapAssessmentToProfile($session);

        // Find or create client profile
        $profile = ClientProfile::firstOrNew(['user_id' => $user->id]);

        // Update profile fields
        foreach ($profileData as $field => $value) {
            $profile->$field = $value;
        }

        $profile->save();

        return $profile;
    }

    /**
     * Check for existing profile data to pre-fill assessment
     * 
     * @param User $user
     * @param string $questionId
     * @return mixed|null Value from profile or null
     */
    public function getPrefilledAnswerFromProfile(User $user, string $questionId)
    {
        $profile = ClientProfile::where('user_id', $user->id)->first();

        if (!$profile) {
            return null;
        }

        // Map question IDs to profile fields
        $fieldMapping = [
            'age' => 'age',
            'gender' => 'gender',
            'height' => 'height',
            'current_weight' => 'current_weight',
            'target_weight' => 'target_weight',
            'body_type' => 'body_type',
            'activity_level' => 'activity_level',
            'diet_type' => 'diet_type',
            // Add other mappings as needed
        ];

        if (!isset($fieldMapping[$questionId])) {
            return null;
        }

        $profileField = $fieldMapping[$questionId];
        return $profile->$profileField;
    }

    /**
     * Calculate completion percentage for an assessment
     * 
     * @param AssessmentSession $session
     * @return int Percentage complete (0-100)
     */
    public function calculateCompletionPercentage(AssessmentSession $session)
    {
        $user = User::find($session->user_id);
        $lang = $user->language ?? 'en';

        $questions = DietAssessmentFlow::getQuestions($session->assessment_type ?? 'moderate', $lang);
        $totalQuestions = count($questions);

        $answeredQuestions = count(array_filter(array_keys($session->responses ?? []), function ($key) {
            return !str_starts_with($key, '_');
        }));

        return $totalQuestions > 0 ? round(($answeredQuestions / $totalQuestions) * 100) : 0;
    }

    /**
     * Generate a summary of assessment responses
     * 
     * @param AssessmentSession $session
     * @return array Summary of key responses
     */
    public function generateResponseSummary(AssessmentSession $session)
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

        return $summary;
    }
}