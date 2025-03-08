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
        return [
            1 => ['name' => __('attributes.phase_basic', [], $lang), 'first_question' => 'age'],
            2 => ['name' => __('attributes.phase_health', [], $lang), 'first_question' => 'health_conditions'],
            3 => ['name' => __('attributes.phase_diet', [], $lang), 'first_question' => 'diet_type'],
            4 => ['name' => __('attributes.phase_food', [], $lang), 'first_question' => 'meal_preferences'],
            5 => ['name' => __('attributes.phase_lifestyle', [], $lang), 'first_question' => 'daily_schedule'],
            6 => ['name' => __('attributes.phase_goals', [], $lang), 'first_question' => 'primary_goal'],
            7 => ['name' => __('attributes.phase_plan', [], $lang), 'first_question' => 'plan_type']
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
}