<?php
// app/Config/LanguageTranslations.php

namespace App\Config;

class LanguageTranslations
{
    /**
     * Get translations for a specific language
     * 
     * @param string $lang Language code
     * @return array
     */
    public static function getTranslations($lang = 'en')
    {
        $translations = [
            'en' => self::getEnglishTranslations(),
            'hi' => self::getHindiTranslations(),
            // Add more language methods as needed
        ];

        return $translations[$lang] ?? $translations['en'];
    }

    /**
     * Get English translations
     * 
     * @return array
     */
    private static function getEnglishTranslations()
    {
        return [
            // Basic information
            'age_prompt' => 'Please share your age:',
            'age_error' => 'Please enter a valid age between 12 and 120',
            'gender_prompt' => 'Please select your gender:',
            'male' => 'Male',
            'female' => 'Female',
            'other' => 'Other',
            'height_prompt' => 'Please share your height (in cm or feet-inches):',
            'weight_prompt' => 'Please share your current weight (in kg or lbs):',
            'target_weight_prompt' => 'Please share your target weight, or type "same":',

            // Activity
            'activity_prompt' => 'Which describes your activity level?',
            'activity_header' => 'Activity Level',
            'activity_body' => 'Select your typical activity',
            'sedentary' => 'Sedentary',
            'light_active' => 'Lightly active',
            'mod_active' => 'Moderately active',
            'very_active' => 'Very active',
            'extreme_active' => 'Extremely active',

            // Health
            'health_prompt' => 'Any health conditions?',
            'health_header' => 'Health Conditions',
            'health_body' => 'Select all that apply',
            'diabetes' => 'Diabetes',
            'hypertension' => 'Hypertension',
            'heart' => 'Heart disease',
            'kidney' => 'Kidney issues',
            'liver' => 'Liver problems',
            'digestive' => 'Digestive disorders',
            'thyroid' => 'Thyroid issues',
            'none_health' => 'None of these',
            'health_details_prompt' => 'More details about your health:',

            // Diet
            'diet_prompt' => 'What diet do you follow?',
            'diet_header' => 'Diet Type',
            'diet_body' => 'Select your eating style',
            'omnivore' => 'Omnivore',
            'vegetarian' => 'Vegetarian',
            'eggetarian' => 'Vegetarian + eggs',
            'vegan' => 'Vegan',
            'jain' => 'Jain',
            'keto' => 'Keto',
            'other_diet' => 'Other',

            // Allergies
            'allergies_prompt' => 'Any food allergies?',
            'allergies_header' => 'Food Allergies',
            'allergies_body' => 'Select all that apply',
            'dairy' => 'Dairy',
            'gluten' => 'Gluten',
            'nuts' => 'Nuts',
            'seafood' => 'Seafood',
            'eggs' => 'Eggs',
            'soy' => 'Soy',
            'other_allergy' => 'Other',
            'none' => 'None',

            // Restrictions
            'restrictions_prompt' => 'Foods you avoid?',
            'restrictions_header' => 'Food Restrictions',
            'restrictions_body' => 'Select foods you avoid',
            'red_meat' => 'Red meat',
            'poultry' => 'Poultry',
            'seafood_r' => 'Seafood',
            'eggs_r' => 'Eggs',
            'dairy_r' => 'Dairy',
            'onion_garlic' => 'Onion/Garlic',
            'processed' => 'Processed foods',
            'none_r' => 'None',

            // Goals
            'goal_prompt' => 'Your primary health goal?',
            'goal_header' => 'Primary Goal',
            'goal_body' => 'Select main objective',
            'weight_loss' => 'Weight loss',
            'muscle_gain' => 'Muscle gain',
            'maintain' => 'Maintain weight',
            'energy' => 'Better energy',
            'health' => 'Improve health',
            'other_goal' => 'Other',

            // Plan
            'plan_prompt' => 'Choose your plan type:',
            'complete_plan' => 'Complete Plan',
            'basic_plan' => 'Basic Plan',
            'focus_plan' => 'Food Focus',
            'complete_prompt' => 'Thanks! Generating your diet plan...',

            // Exercise
            'exercise_prompt' => 'Your exercise pattern?',
            'exercise_header' => 'Exercise Pattern',
            'exercise_body' => 'Select your approach',
            'strength' => 'Strength training',
            'cardio' => 'Cardio focused',
            'mix_exercise' => 'Mixed approach',
            'yoga' => 'Yoga/low-impact',
            'sport' => 'Sport-specific',
            'minimal_ex' => 'Minimal exercise',

            // Timeline
            'timeline_prompt' => 'Your timeline for results?',
            'timeline_header' => 'Timeline',
            'timeline_body' => 'Select your timeframe',
            'short_term' => 'Short term (1-4 wk)',
            'medium_term' => 'Medium (1-3 mo)',
            'long_term' => 'Long term (3+ mo)',
            'lifestyle' => 'Lifestyle (ongoing)'
        ];
    }

    /**
     * Get Hindi translations
     * 
     * @return array
     */
    private static function getHindiTranslations()
    {
        return [
            // Basic Hindi translations (placeholder)
            'age_prompt' => 'कृपया अपनी उम्र बताएं:',
            // Add other Hindi translations as needed
        ];
    }
}