<?php
// app/Config/DietAssessmentFlow.php

namespace App\Config;

class DietAssessmentFlow
{
    /**
     * Get the assessment phases configuration
     * 
     * @param string $lang Language code (default: en)
     * @return array
     */
    public static function getPhases($lang = 'en')
    {
        $t = LanguageTranslations::getTranslations($lang);

        return [
            1 => ['name' => $t['phase_basic'] ?? 'Basic Information', 'first_question' => 'age'],
            2 => ['name' => $t['phase_health'] ?? 'Health Assessment', 'first_question' => 'health_conditions'],
            3 => ['name' => $t['phase_diet'] ?? 'Diet Preferences', 'first_question' => 'diet_type'],
            4 => ['name' => $t['phase_food'] ?? 'Food Details', 'first_question' => 'meal_preferences'],
            5 => ['name' => $t['phase_lifestyle'] ?? 'Lifestyle', 'first_question' => 'daily_schedule'],
            6 => ['name' => $t['phase_goals'] ?? 'Goals', 'first_question' => 'primary_goal'],
            7 => ['name' => $t['phase_plan'] ?? 'Plan Customization', 'first_question' => 'plan_type']
        ];
    }

    /**
     * Get assessment questions based on assessment level
     * 
     * @param string $level Assessment level (quick, moderate, comprehensive)
     * @param string $lang Language code
     * @return array
     */
    public static function getQuestions($level = 'moderate', $lang = 'en')
    {
        switch ($level) {
            case 'quick':
                return QuickAssessmentQuestions::getQuestions($lang);
            case 'comprehensive':
                return ComprehensiveAssessmentQuestions::getQuestions($lang);
            case 'moderate':
            default:
                return ModerateAssessmentQuestions::getQuestions($lang);
        }
    }

    /**
     * Get conditional checks for assessment flows
     */
    public static function getConditionalChecks()
    {
        return [
            // Health condition checks
            'hasHealthCondition' => function ($response) {
                // Convert response to array for consistent processing
                $selections = self::convertResponseToArray($response);

                // If selections contain "None of the above", return false
                if (
                    in_array('8', $selections) || in_array('None of these', $selections) ||
                    in_array('none_health', $selections)
                ) {
                    return false;
                }

                // Otherwise return true if there are any selections
                return !empty($selections);
            },

            // Other condition checks as needed
        ];
    }

    /**
     * Convert string response to array for processing
     */
    private static function convertResponseToArray($response)
    {
        if (is_array($response)) {
            return $response;
        }

        // For multiple selections (comma-separated)
        if (is_string($response) && strpos($response, ',') !== false) {
            return array_map('trim', explode(',', $response));
        }

        // For single selection
        return [$response];
    }
}   