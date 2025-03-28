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

            // **region**
            'country_prompt' => 'Which country do you live in?',
            'state_prompt' => 'Which state or region do you live in?',
            'city_prompt' => 'Which city do you live in?',

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
            'gerd' => 'GERD/Acid reflux',
            'ibs' => 'IBS/IBD',
            'hormonal' => 'Hormonal imbalances',
            'pcos' => 'PCOS',
            'respiratory' => 'Respiratory issues',
            'joint_pain' => 'Joint pain/Arthritis',
            'skin_conditions' => 'Skin conditions',
            'other_health' => 'Other',

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
            'pescatarian' => 'Pescatarian',
            'flexitarian' => 'Flexitarian',
            'mediterranean' => 'Mediterranean',
            'dash' => 'DASH',
            'fodmap' => 'FODMAP',
            'raw_food' => 'Raw food',
            'diet_type_other_prompt' => 'Please describe your diet type:',

            // Spice Preference
            'spice_preference_prompt' => 'What is your spice preference?',
            'spice_preference_header' => 'Spice Level',
            'spice_preference_body' => 'Select your preferred spice level',
            'mild_spice' => 'Mild (no spice)',
            'low_spice' => 'Low spice',
            'medium_spice' => 'Medium spice',
            'spicy' => 'Spicy',
            'very_spicy' => 'Very spicy',


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
            'tree_nuts' => 'Tree nuts',
            'peanuts' => 'Peanuts',
            'corn' => 'Corn',
            'fruits' => 'Fruits',
            'nightshades' => 'Nightshades',
            'sulfites' => 'Sulfites',
            'fodmaps' => 'FODMAPs',
            'none_allergy' => 'None',
            'allergy_details_prompt' => 'Please provide details about your specific food allergies or intolerances:',


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
            'wheat_gluten' => 'Wheat/Gluten',
            'soy_r' => 'Soy',
            'nightshades_r' => 'Nightshades',
            'root_veg' => 'Root vegetables',
            'nuts_r' => 'Nuts',
            'added_sugar' => 'Added sugar',
            'other_restriction' => 'Other',
            'none_restriction' => 'None',
            'food_restrictions_other_prompt' => 'Please specify other foods you avoid:',

            // Goals
            'goal_prompt' => 'Your primary health goal?',
            'goal_header' => 'Primary Goal',
            'goal_body' => 'Select main objective',
            'weight_loss' => 'Weight loss',
            'muscle_gain' => 'Muscle gain',
            'maintain' => 'Maintain weight',
            'energy' => 'Better energy',
            'health' => 'Improve health',
            'digestion' => 'Improved digestion',
            'overall_health' => 'Improved overall health',
            'recovery' => 'Recovery from condition',
            'athletic' => 'Athletic performance',
            'longevity' => 'Longevity & prevention',
            'hormone_balance' => 'Hormone balance',
            'mental_clarity' => 'Mental clarity',
            'other_goal' => 'Other',

            // Recovery Needs
            'recovery_needs_prompt' => 'Are you looking to address any specific health concerns?',
            'recovery_needs_header' => 'Health Goals',
            'recovery_needs_body' => 'Select all that apply to you',
            'weight_loss_need' => 'Weight loss',
            'muscle_gain_need' => 'Muscle gain',
            'digestive_health' => 'Digestive health',
            'energy_improvement' => 'Energy improvement',
            'blood_sugar' => 'Blood sugar management',
            'cholesterol' => 'Cholesterol management',
            'inflammation' => 'Inflammation reduction',
            'detoxification' => 'Detoxification',
            'immune_support' => 'Immune support',
            'sleep_improvement' => 'Sleep improvement',
            'stress_management' => 'Stress management',
            'hair_skin' => 'Hair/skin health',
            'hormone_balance_need' => 'Hormone balance',
            'organ_recovery' => 'Organ recovery',
            'post_surgery' => 'Post-surgery nutrition',
            'none_specifically' => 'None specifically',

            // Favorite and Disliked Foods
            'favorite_foods_prompt' => 'What are your favorite foods?',
            'disliked_foods_prompt' => 'Any foods you dislike?',

            // Nutrition Knowledge
            'nutrition_knowledge_prompt' => 'How well do you understand nutrition?',
            'nutrition_knowledge_header' => 'Nutrition Knowledge',
            'nutrition_knowledge_body' => 'Select your level',
            'beginner' => 'Beginner',
            'basic' => 'Basic',
            'intermediate' => 'Intermediate',
            'advanced' => 'Advanced',
            'expert' => 'Expert',

            // Work Type 
            'work_type_prompt' => 'What’s your work type?',
            'work_type_header' => 'Work Type',
            'work_type_body' => 'Select the best match',
            'desk_job' => 'Desk job',
            'moderate_physical' => 'Moderate physical',
            'demanding_job' => 'Physically demanding',
            'student' => 'Student',
            'parent' => 'Stay-at-home parent',
            'retired' => 'Retired',
            'unemployed' => 'Unemployed/Other',

            // Cooking Time
            'cooking_time_prompt' => 'How much time do you spend cooking?',
            'cooking_time_header' => 'Cooking Time',
            'cooking_time_body' => 'Select your available time',
            'minimal_time' => 'Minimal (0-15 min)',
            'brief_time' => 'Brief (15-30 min)',
            'moderate_time' => 'Moderate (30-60 min)',
            'extended_time' => 'Extended (60+ min)',
            'batch_cooking' => 'Batch cooking (weekends)',

            // Grocery Access
            'grocery_access_prompt' => 'How easy is grocery shopping for you?',
            'grocery_access_header' => 'Grocery Access',
            'grocery_access_body' => 'Select your situation',
            'excellent_variety' => 'Great variety nearby',
            'good_access' => 'Good basic options',
            'limited_options' => 'Limited choices',
            'delivery_services' => 'Use delivery services',
            'challenging_access' => 'Hard to get groceries',

            // Budget Constraints
            'budget_constraints_prompt' => 'Do you have a meal budget?',
            'budget_constraints_header' => 'Meal Budget',
            'budget_constraints_body' => 'Select your situation',
            'very_budget' => 'Strict budget',
            'moderately_budget' => 'Moderate budget',
            'flexible_budget' => 'Flexible budget',
            'no_constraints' => 'No budget concerns',

            // Exercise Timing
            'exercise_timing_prompt' => 'When do you usually exercise?',
            'exercise_timing_header' => 'Exercise Timing',
            'exercise_timing_body' => 'Select your usual time',
            'early_morning' => 'Early morning (5-8 AM)',
            'morning' => 'Morning (8-11 AM)',
            'midday' => 'Midday (11 AM-2 PM)',
            'afternoon' => 'Afternoon (2-5 PM)',
            'evening' => 'Evening (5-8 PM)',
            'night' => 'Night (8-11 PM)',
            'varies' => 'Varies/Inconsistent',

            // Sleep Hours
            'sleep_hours_prompt' => 'How many hours do you sleep per night?',
            'sleep_hours_header' => 'Sleep Duration',
            'sleep_hours_body' => 'Select your average',
            'less_than_5' => '< 5 hours',
            '5_to_6' => '5-6 hours',
            '6_to_7' => '6-7 hours',
            '7_to_8' => '7-8 hours',
            '8_to_9' => '8-9 hours',
            'more_than_9' => '> 9 hours',
            'variable_sleep' => 'Highly variable',

            // Commitment Level
            'commitment_level_prompt' => 'How committed are you to a structured plan?',
            'commitment_level_header' => 'Commitment Level',
            'commitment_level_body' => 'Be honest about your approach',
            'very_committed' => 'Very committed (strict)',
            'mostly' => 'Mostly committed (90/10)',
            'moderate_commitment' => 'Moderate (80/20)',
            'flexible_commitment' => 'Flexible approach',
            'gradual' => 'Gradual implementation',

            // Motivation
            'motivation_prompt' => 'What’s your main reason for dietary changes?',
            'motivation_header' => 'Motivation',
            'motivation_body' => 'Select your key reason',
            'better_health' => 'Better health/longevity',
            'appearance' => 'Appearance/body goals',
            'performance' => 'Performance boost',
            'energy' => 'More energy/vitality',
            'medical' => 'Medical necessity',
            'family' => 'Family/social support',
            'event' => 'Upcoming event/goal',

            // Past Attempts
            'past_attempts_prompt' => 'Have you tried diet plans before?',
            'past_attempts_header' => 'Past Attempts',
            'past_attempts_body' => 'Select your experience',
            'many_little' => 'Many tries, little success',
            'some_mixed' => 'Some attempts, mixed results',
            'few_limited' => 'Few tries, limited success',
            'success_regained' => 'Success before, but regained',
            'first_attempt' => 'First serious try',

            // Detail Level
            'detail_level_prompt' => 'How detailed should your meal plan be?',
            'detail_level_header' => 'Detail Level',
            'detail_level_body' => 'Select your preference',
            'very_detailed' => 'Very detailed (exact amounts)',
            'moderately_detailed' => 'Moderately detailed',
            'general_guidelines' => 'General guidelines',
            'simple_flexible' => 'Simple & flexible',

            // Recipe Complexity
            'recipe_complexity_prompt' => 'How complex should your recipes be?',
            'recipe_complexity_header' => 'Recipe Complexity',
            'recipe_complexity_body' => 'Select your preference',
            'very_simple' => 'Very simple (few ingredients)',
            'moderately_simple' => 'Moderately simple',
            'balanced_complexity' => 'Balanced complexity',
            'complex' => 'Complex (gourmet)',

            // Meal Variety
            'meal_variety_prompt' => 'How much variety do you want in meals?',
            'meal_variety_header' => 'Meal Variety',
            'meal_variety_body' => 'Select your preference',
            'high_variety' => 'High variety (different daily)',
            'moderate_variety' => 'Moderate variety',
            'limited_variety' => 'Limited variety (simple)',
            'same_meals' => 'Same meals repeated',

            // Additional Requests
            'additional_requests_prompt' => 'Any additional requests or info to share?',

            // Organ Recovery
            'organ_recovery_prompt' => 'Which organs are you focusing on for recovery?',
            'organ_recovery_header' => 'Organ Recovery',
            'organ_recovery_body' => 'Select all that apply',
            'liver_rx' => 'Liver',
            'kidneys_rx' => 'Kidneys',
            'heart_rx' => 'Heart',
            'lungs' => 'Lungs',
            'digestive_system' => 'Digestive system',
            'pancreas' => 'Pancreas',
            'other_organ' => 'Other',
            'organ_recovery_details_prompt' => 'Please provide more details about the organs you\'re focusing on:',
            'surgery_details_prompt' => 'Please share details about your surgery and when it occurred:',

            // Religious Diet
            'religion_diet_prompt' => 'Follow a religious diet?',
            'religion_diet_header' => 'Religious Diet',
            'religion_diet_body' => 'Select if applicable',
            'kosher' => 'Kosher (Jewish)',
            'halal' => 'Halal (Islamic)',
            'hindu_veg' => 'Hindu veg',
            'buddhist_veg' => 'Buddhist veg',
            'jain_veg' => 'Jain veg',
            'fasting' => 'Fasting periods',
            'other_religious' => 'Other diet',
            'none_religious' => 'None',
            'religion_diet_details_prompt' => 'Describe your diet rules',
            'fasting_details_prompt' => 'Describe fasting (timing, food)',


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
            'exercise_frequency_prompt' => 'How often do you exercise?',
            'exercise_frequency_header' => 'Exercise Frequency',
            'exercise_frequency_body' => 'Select your typical schedule',
            'daily' => 'Daily',
            '4to6_weekly' => '4-6 times per week',
            '2to3_weekly' => '2-3 times per week',
            'once_weekly' => 'Once per week',
            'rarely' => 'Rarely/never',

            // Timeline
            'timeline_prompt' => 'Your timeline for results?',
            'timeline_header' => 'Timeline',
            'timeline_body' => 'Select your timeframe',
            'short_term' => 'Short term (1-4 wk)',
            'medium_term' => 'Medium (1-3 mo)',
            'long_term' => 'Long term (3+ mo)',
            'lifestyle' => 'Lifestyle (ongoing)',

            // Medications
            'meds_prompt' => 'Are you taking any medications?',
            'meds_header' => 'Medications',
            'meds_body' => 'Select all that apply',
            'rx_meds' => 'Prescription medications',
            'otc_meds' => 'Over-the-counter medications',
            'supplements' => 'Supplements/vitamins',
            'combo_meds' => 'Combination therapy',
            'no_meds' => 'None',
            'meds_detail_prompt' => 'Please provide details about your medications:',

            'cuisine_preferences_prompt' => 'Select up to 3 cuisines',
            'cuisine_preferences_header' => 'Cuisine Preferences',
            'cuisine_preferences_body' => 'Pick your top 3 cuisines',

            'north_indian' => 'North Indian',
            'south_indian' => 'South Indian',
            'east_indian' => 'East Indian',
            'west_indian' => 'West Indian',
            'punjabi' => 'Punjabi',
            'gujarati' => 'Gujarati',
            'bengali' => 'Bengali',
            'mediterranean_cuisine' => 'Mediterranean',
            'chinese_cuisine' => 'Chinese',
            'japanese' => 'Japanese',
            'korean' => 'Korean',
            'thai' => 'Thai',
            'vietnamese' => 'Vietnamese',
            'middle_eastern' => 'Middle Eastern',
            'mexican' => 'Mexican',
            'italian_cuisine' => 'Italian',
            'continental' => 'Continental',
            'no_specific_cuisine' => 'No preference',

            // Meal timing
            'meal_timing_prompt' => 'Select your meal schedule',
            'meal_timing_header' => 'Meal Timing',
            'meal_timing_body' => 'Choose your preferred pattern',

            'traditional_meals' => 'Traditional (3 meals)',
            'small_frequent' => 'Small meals (5-6)',
            'intermittent_16_8' => 'Intermittent 16:8',
            'intermittent_18_6' => 'Intermittent 18:6',
            'omad' => 'OMAD (1 meal/day)',
            'flexible_pattern' => 'Flexible/No set pattern',

            // Meal Preferences
            'meal_preferences_prompt' => 'Select your meal preferences',
            'meal_preferences_header' => 'Meal Preferences',
            'meal_preferences_body' => 'Choose all that apply',
            'high_protein' => 'High protein',
            'low_carb' => 'Low carb',
            'low_fat' => 'Low fat',
            'gluten_free' => 'Gluten-free',
            'dairy_free' => 'Dairy-free',
            'sugar_free' => 'Sugar-free',
            'low_sodium' => 'Low sodium',
            'whole_foods' => 'Whole foods focus',
            'plant_based' => 'Plant-based',
            'seasonal' => 'Local/Seasonal',
            'balanced_macros' => 'Balanced macros',
            'home_cooking' => 'Home-cooked meals',
            'no_specific_prefs' => 'No specific preference',

            // Meal Portion Size
            'meal_portion_size_prompt' => 'What is your preferred meal portion size?',
            'meal_portion_size_header' => 'Meal Portion Size',
            'meal_portion_size_body' => 'Select your preferred portion size',
            'small_portion' => 'Small portion',
            'medium_portion' => 'Medium portion',
            'large_portion' => 'Large portion',
            'variable_portion' => 'Varies per meal',
            'not_sure_portion' => 'Not sure',

            // Daily schedule
            'schedule_prompt' => 'Your typical daily schedule?',
            'schedule_header' => 'Daily Schedule',
            'schedule_body' => 'Select your typical routine',
            'early_riser' => 'Early riser',
            'standard' => 'Standard hours',
            'late_riser' => 'Late riser',
            'night_shift' => 'Night shift',
            'irregular' => 'Irregular hours',

            // Cooking capability
            'cooking_prompt' => 'Your cooking capability?',
            'cooking_header' => 'Cooking Capability',
            'cooking_body' => 'Select your cooking situation',
            'full_cooking' => 'Full cooking facilities',
            'basic_cooking' => 'Basic cooking facilities',
            'minimal_cooking' => 'Minimal cooking facilities',
            'prepared_food' => 'Mostly prepared foods',
            'cooking_help' => 'Have cooking assistance',

            // Stress and sleep
            'stress_prompt' => 'Stress and sleep patterns?',
            'stress_header' => 'Stress & Sleep',
            'stress_body' => 'Select your situation',
            'low_good' => 'Low stress, good sleep',
            'moderate_ok' => 'Moderate stress, OK sleep',
            'high_enough' => 'High stress, enough sleep',
            'low_poor' => 'Low stress, poor sleep',
            'high_poor' => 'High stress, poor sleep',

            // Commitment level
            'commitment_prompt' => 'Your commitment to this plan?',
            'commitment_header' => 'Commitment Level',
            'commitment_body' => 'Select your commitment level',
            'very_committed' => 'Very committed',
            'mostly' => 'Mostly committed',
            'moderate' => 'Moderately committed',
            'flexible' => 'Need flexibility',
            'gradual' => 'Gradual approach',

            // Additional requests
            'additional_prompt' => 'Any additional requests or specifics?',

            // Meal variety
            'variety_prompt' => 'Desired meal variety?',
            'variety_header' => 'Meal Variety',
            'variety_body' => 'Select your preference',
            'high_variety' => 'High variety (different meals daily)',
            'moderate_var' => 'Moderate variety (some repetition)',
            'limited_var' => 'Limited variety (weekly rotation)',
            'repetitive' => 'Repetitive (same daily pattern)',

            // Body type
            'body_type_prompt' => 'Which best describes your body type?',
            'body_type_header' => 'Body Type',
            'body_type_body' => 'Select your natural physique',
            'ectomorph' => 'Naturally thin',
            'mesomorph' => 'Athletic build',
            'endomorph' => 'Naturally stocky',
            'combination' => 'Combination',
            'not_sure' => 'Not sure',

            // Medical history
            'medical_history_prompt' => 'Any past medical conditions?',
            'medical_history_header' => 'Medical History',
            'medical_history_body' => 'Select all that apply',
            'heart_disease' => 'Heart disease',
            'high_cholesterol' => 'High cholesterol',
            'hypertension' => 'Hypertension',
            'diabetes' => 'Diabetes',
            'cancer' => 'Cancer',
            'autoimmune' => 'Autoimmune issue',
            'gastrointestinal' => 'Gastrointestinal',
            'mental_health' => 'Mental health issue',
            'none_medical' => 'None of these',

            // Water intake
            'water_intake_prompt' => 'Daily water intake?',
            'water_intake_header' => 'Water Intake',
            'water_intake_body' => 'Select your typical consumption',
            'water_lt1' => 'Less than 1 liter',
            'water_1to2' => '1-2 liters',
            'water_2to3' => '2-3 liters',
            'water_gt3' => 'More than 3 liters',
            'water_unknown' => 'Don\'t track water intake',

            // Weight goal
            'weight_goal_prompt' => 'Your desired rate of weight change?',
            'weight_goal_header' => 'Weight Goal',
            'weight_goal_body' => 'Select your preferred pace',
            'rapid_loss' => 'Rapid weight loss',
            'moderate_loss' => 'Moderate weight loss',
            'slow_loss' => 'Slow, steady weight loss',
            'maintain' => 'Maintain current weight',
            'slight_gain' => 'Slight weight gain',
            'moderate_gain' => 'Moderate weight gain',
            'significant_gain' => 'Significant weight gain',

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