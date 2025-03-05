<?php
// app/Config/ComprehensiveAssessmentQuestions.php

namespace App\Config;

class ComprehensiveAssessmentQuestions
{
    /**
     * Get comprehensive assessment questions (most detailed)
     * 
     * @param string $lang Language code
     * @return array
     */
    public static function getQuestions($lang = 'en')
    {
        $t = LanguageTranslations::getTranslations($lang);
        $moderateQuestions = ModerateAssessmentQuestions::getQuestions($lang);

        // Start with moderate questions
        $comprehensiveQuestions = $moderateQuestions;

        // Modify the health_conditions question flow to include medications
        $comprehensiveQuestions['health_conditions']['next_conditional']['default'] = 'medications';

        // Add medications section after health conditions
        $comprehensiveQuestions['medications'] = [
            'prompt' => $t['meds_prompt'],
            'type' => 'list',
            'header' => $t['meds_header'],
            'body' => $t['meds_body'],
            'options' => [
                ['id' => '1', 'title' => $t['rx_meds']],
                ['id' => '2', 'title' => $t['otc_meds']],
                ['id' => '3', 'title' => $t['supplements']],
                ['id' => '4', 'title' => $t['combo_meds']],
                ['id' => '5', 'title' => $t['no_meds']]
            ],
            'next_conditional' => [
                'default' => 'allergies',
                'conditions' => [
                    [
                        'condition' => ['1', '2', '3', '4'],
                        'next' => 'medication_details'
                    ]
                ]
            ],
            'phase' => 2
        ];

        $comprehensiveQuestions['medication_details'] = [
            'prompt' => $t['meds_detail_prompt'],
            'type' => 'text',
            'next' => 'allergies',
            'phase' => 2
        ];

        // Modify allergies to include recovery needs
        $comprehensiveQuestions['allergies']['next_conditional']['default'] = 'recovery_needs';

        // Add recovery needs after allergies
        $comprehensiveQuestions['recovery_needs'] = [
            'prompt' => $t['recovery_needs_prompt'],
            'type' => 'list',
            'multiple' => true,
            'header' => $t['recovery_needs_header'],
            'body' => $t['recovery_needs_body'],
            'options' => [
                ['id' => '1', 'title' => $t['weight_loss_need']],
                ['id' => '2', 'title' => $t['muscle_gain_need']],
                ['id' => '3', 'title' => $t['digestive_health']],
                ['id' => '4', 'title' => $t['energy_improvement']],
                ['id' => '5', 'title' => $t['blood_sugar']],
                ['id' => '6', 'title' => $t['cholesterol']],
                ['id' => '7', 'title' => $t['inflammation']],
                ['id' => '8', 'title' => $t['detoxification']],
                ['id' => '9', 'title' => $t['immune_support']],
                ['id' => '10', 'title' => $t['sleep_improvement']],
                ['id' => '11', 'title' => $t['stress_management']],
                ['id' => '12', 'title' => $t['hair_skin']],
                ['id' => '13', 'title' => $t['hormone_balance_need']],
                ['id' => '14', 'title' => $t['organ_recovery']],
                ['id' => '15', 'title' => $t['post_surgery']],
                ['id' => '16', 'title' => $t['none_specifically']]
            ],
            'next_conditional' => [
                'default' => 'diet_type',
                'conditions' => [
                    [
                        'condition' => 'hasOrganRecovery',
                        'next' => 'organ_recovery'
                    ],
                    [
                        'condition' => 'hasPostSurgery',
                        'next' => 'surgery_details'
                    ]
                ]
            ],
            'phase' => 2
        ];

        $comprehensiveQuestions['organ_recovery'] = [
            'prompt' => $t['organ_recovery_prompt'],
            'type' => 'list',
            'multiple' => true,
            'header' => $t['organ_recovery_header'],
            'body' => $t['organ_recovery_body'],
            'options' => [
                ['id' => '1', 'title' => $t['liver_rx']],
                ['id' => '2', 'title' => $t['kidneys_rx']],
                ['id' => '3', 'title' => $t['heart_rx']],
                ['id' => '4', 'title' => $t['lungs']],
                ['id' => '5', 'title' => $t['digestive_system']],
                ['id' => '6', 'title' => $t['pancreas']],
                ['id' => '7', 'title' => $t['other_organ']]
            ],
            'next_conditional' => [
                'default' => 'diet_type',
                'conditions' => [
                    [
                        'condition' => '7',
                        'next' => 'organ_recovery_details'
                    ]
                ]
            ],
            'phase' => 2
        ];

        $comprehensiveQuestions['organ_recovery_details'] = [
            'prompt' => $t['organ_recovery_details_prompt'],
            'type' => 'text',
            'next' => 'diet_type',
            'phase' => 2
        ];

        $comprehensiveQuestions['surgery_details'] = [
            'prompt' => $t['surgery_details_prompt'],
            'type' => 'text',
            'next' => 'diet_type',
            'phase' => 2
        ];

        // Modify diet_type to include religious dietary practices
        $comprehensiveQuestions['diet_type']['next_conditional']['default'] = 'religion_diet';

        // Add religious dietary practices after diet_type
        $comprehensiveQuestions['religion_diet'] = [
            'prompt' => $t['religion_diet_prompt'],
            'type' => 'list',
            'header' => $t['religion_diet_header'],
            'body' => $t['religion_diet_body'],
            'options' => [
                ['id' => '1', 'title' => $t['kosher']],
                ['id' => '2', 'title' => $t['halal']],
                ['id' => '3', 'title' => $t['hindu_veg']],
                ['id' => '4', 'title' => $t['buddhist_veg']],
                ['id' => '5', 'title' => $t['jain_veg']],
                ['id' => '6', 'title' => $t['fasting']],
                ['id' => '7', 'title' => $t['other_religious']],
                ['id' => '8', 'title' => $t['none_religious']]
            ],
            'next_conditional' => [
                'default' => 'food_restrictions',
                'conditions' => [
                    [
                        'condition' => '7',
                        'next' => 'religion_diet_details'
                    ],
                    [
                        'condition' => '6',
                        'next' => 'fasting_details'
                    ]
                ]
            ],
            'phase' => 3
        ];

        $comprehensiveQuestions['religion_diet_details'] = [
            'prompt' => $t['religion_diet_details_prompt'],
            'type' => 'text',
            'next' => 'food_restrictions',
            'phase' => 3
        ];

        $comprehensiveQuestions['fasting_details'] = [
            'prompt' => $t['fasting_details_prompt'],
            'type' => 'text',
            'next' => 'food_restrictions',
            'phase' => 3
        ];

        // Modify food_restrictions to point to cuisine_preferences
        $comprehensiveQuestions['food_restrictions']['next_conditional']['default'] = 'cuisine_preferences';

        // Add cuisine preferences after food restrictions
        $comprehensiveQuestions['cuisine_preferences'] = [
            'prompt' => $t['cuisine_preferences_prompt'],
            'type' => 'list',
            'multiple' => true,
            'header' => $t['cuisine_preferences_header'],
            'body' => $t['cuisine_preferences_body'],
            'options' => [
                ['id' => '1', 'title' => $t['north_indian']],
                ['id' => '2', 'title' => $t['south_indian']],
                ['id' => '3', 'title' => $t['east_indian']],
                ['id' => '4', 'title' => $t['west_indian']],
                ['id' => '5', 'title' => $t['punjabi']],
                ['id' => '6', 'title' => $t['gujarati']],
                ['id' => '7', 'title' => $t['bengali']],
                ['id' => '8', 'title' => $t['mediterranean_cuisine']],
                ['id' => '9', 'title' => $t['chinese_cuisine']],
                ['id' => '10', 'title' => $t['japanese']],
                ['id' => '11', 'title' => $t['korean']],
                ['id' => '12', 'title' => $t['thai']],
                ['id' => '13', 'title' => $t['vietnamese']],
                ['id' => '14', 'title' => $t['middle_eastern']],
                ['id' => '15', 'title' => $t['mexican']],
                ['id' => '16', 'title' => $t['italian_cuisine']],
                ['id' => '17', 'title' => $t['continental']],
                ['id' => '18', 'title' => $t['no_specific_cuisine']]
            ],
            'next' => 'meal_timing',
            'phase' => 3
        ];

        // Add meal timing after cuisine preferences
        $comprehensiveQuestions['meal_timing'] = [
            'prompt' => $t['meal_timing_prompt'],
            'type' => 'list',
            'header' => $t['meal_timing_header'],
            'body' => $t['meal_timing_body'],
            'options' => [
                ['id' => '1', 'title' => $t['traditional_meals']],
                ['id' => '2', 'title' => $t['small_frequent']],
                ['id' => '3', 'title' => $t['intermittent_16_8']],
                ['id' => '4', 'title' => $t['intermittent_18_6']],
                ['id' => '5', 'title' => $t['omad']],
                ['id' => '6', 'title' => $t['flexible_pattern']]
            ],
            'next' => 'meal_preferences',
            'phase' => 4
        ];

        // Add meal preferences after meal timing
        $comprehensiveQuestions['meal_preferences'] = [
            'prompt' => $t['meal_preferences_prompt'],
            'type' => 'list',
            'multiple' => true,
            'header' => $t['meal_preferences_header'],
            'body' => $t['meal_preferences_body'],
            'options' => [
                ['id' => '1', 'title' => $t['high_protein']],
                ['id' => '2', 'title' => $t['low_carb']],
                ['id' => '3', 'title' => $t['low_fat']],
                ['id' => '4', 'title' => $t['gluten_free']],
                ['id' => '5', 'title' => $t['dairy_free']],
                ['id' => '6', 'title' => $t['sugar_free']],
                ['id' => '7', 'title' => $t['low_sodium']],
                ['id' => '8', 'title' => $t['whole_foods']],
                ['id' => '9', 'title' => $t['plant_based']],
                ['id' => '10', 'title' => $t['seasonal']],
                ['id' => '11', 'title' => $t['balanced_macros']],
                ['id' => '12', 'title' => $t['home_cooking']],
                ['id' => '13', 'title' => $t['no_specific_prefs']]
            ],
            'next' => 'favorite_foods',
            'phase' => 4
        ];

        // Add favorite and disliked foods
        $comprehensiveQuestions['favorite_foods'] = [
            'prompt' => $t['favorite_foods_prompt'],
            'type' => 'text',
            'next' => 'disliked_foods',
            'phase' => 4
        ];

        $comprehensiveQuestions['disliked_foods'] = [
            'prompt' => $t['disliked_foods_prompt'],
            'type' => 'text',
            'next' => 'nutrition_knowledge',
            'phase' => 4
        ];

        $comprehensiveQuestions['nutrition_knowledge'] = [
            'prompt' => $t['nutrition_knowledge_prompt'],
            'type' => 'list',
            'header' => $t['nutrition_knowledge_header'],
            'body' => $t['nutrition_knowledge_body'],
            'options' => [
                ['id' => '1', 'title' => $t['beginner']],
                ['id' => '2', 'title' => $t['basic']],
                ['id' => '3', 'title' => $t['intermediate']],
                ['id' => '4', 'title' => $t['advanced']],
                ['id' => '5', 'title' => $t['expert']]
            ],
            'next' => 'daily_schedule',
            'phase' => 4
        ];

        // Modify daily_schedule to add work_type
        $comprehensiveQuestions['daily_schedule']['next'] = 'work_type';

        // Add work type after daily schedule
        $comprehensiveQuestions['work_type'] = [
            'prompt' => $t['work_type_prompt'],
            'type' => 'list',
            'header' => $t['work_type_header'],
            'body' => $t['work_type_body'],
            'options' => [
                ['id' => '1', 'title' => $t['desk_job']],
                ['id' => '2', 'title' => $t['moderate_physical']],
                ['id' => '3', 'title' => $t['demanding_job']],
                ['id' => '4', 'title' => $t['student']],
                ['id' => '5', 'title' => $t['parent']],
                ['id' => '6', 'title' => $t['retired']],
                ['id' => '7', 'title' => $t['unemployed']]
            ],
            'next' => 'cooking_capability',
            'phase' => 5
        ];

        // Modify cooking_capability to add cooking_time
        $comprehensiveQuestions['cooking_capability']['next'] = 'cooking_time';

        // Add cooking time after cooking capability
        $comprehensiveQuestions['cooking_time'] = [
            'prompt' => $t['cooking_time_prompt'],
            'type' => 'list',
            'header' => $t['cooking_time_header'],
            'body' => $t['cooking_time_body'],
            'options' => [
                ['id' => '1', 'title' => $t['minimal_time']],
                ['id' => '2', 'title' => $t['brief_time']],
                ['id' => '3', 'title' => $t['moderate_time']],
                ['id' => '4', 'title' => $t['extended_time']],
                ['id' => '5', 'title' => $t['batch_cooking']]
            ],
            'next' => 'grocery_access',
            'phase' => 5
        ];

        // Add grocery access after cooking time
        $comprehensiveQuestions['grocery_access'] = [
            'prompt' => $t['grocery_access_prompt'],
            'type' => 'list',
            'header' => $t['grocery_access_header'],
            'body' => $t['grocery_access_body'],
            'options' => [
                ['id' => '1', 'title' => $t['excellent_variety']],
                ['id' => '2', 'title' => $t['good_access']],
                ['id' => '3', 'title' => $t['limited_options']],
                ['id' => '4', 'title' => $t['delivery_services']],
                ['id' => '5', 'title' => $t['challenging_access']]
            ],
            'next' => 'budget_constraints',
            'phase' => 5
        ];

        // Add budget constraints after grocery access
        $comprehensiveQuestions['budget_constraints'] = [
            'prompt' => $t['budget_constraints_prompt'],
            'type' => 'list',
            'header' => $t['budget_constraints_header'],
            'body' => $t['budget_constraints_body'],
            'options' => [
                ['id' => '1', 'title' => $t['very_budget']],
                ['id' => '2', 'title' => $t['moderately_budget']],
                ['id' => '3', 'title' => $t['flexible_budget']],
                ['id' => '4', 'title' => $t['no_constraints']]
            ],
            'next' => 'exercise_routine',
            'phase' => 5
        ];

        // Modify exercise frequency to include exercise timing
        $comprehensiveQuestions['exercise_frequency']['next'] = 'exercise_timing';

        // Add exercise timing after exercise frequency
        $comprehensiveQuestions['exercise_timing'] = [
            'prompt' => $t['exercise_timing_prompt'],
            'type' => 'list',
            'header' => $t['exercise_timing_header'],
            'body' => $t['exercise_timing_body'],
            'options' => [
                ['id' => '1', 'title' => $t['early_morning']],
                ['id' => '2', 'title' => $t['morning']],
                ['id' => '3', 'title' => $t['midday']],
                ['id' => '4', 'title' => $t['afternoon']],
                ['id' => '5', 'title' => $t['evening']],
                ['id' => '6', 'title' => $t['night']],
                ['id' => '7', 'title' => $t['varies']]
            ],
            'next' => 'stress_sleep',
            'phase' => 5
        ];

        // Modify stress_sleep to add sleep hours
        $comprehensiveQuestions['stress_sleep']['next'] = 'sleep_hours';

        // Add sleep hours after stress sleep
        $comprehensiveQuestions['sleep_hours'] = [
            'prompt' => $t['sleep_hours_prompt'],
            'type' => 'list',
            'header' => $t['sleep_hours_header'],
            'body' => $t['sleep_hours_body'],
            'options' => [
                ['id' => '1', 'title' => $t['less_than_5']],
                ['id' => '2', 'title' => $t['5_to_6']],
                ['id' => '3', 'title' => $t['6_to_7']],
                ['id' => '4', 'title' => $t['7_to_8']],
                ['id' => '5', 'title' => $t['8_to_9']],
                ['id' => '6', 'title' => $t['more_than_9']],
                ['id' => '7', 'title' => $t['variable_sleep']]
            ],
            'next' => 'primary_goal',
            'phase' => 5
        ];

        // Modify timeline to add commitment level
        $comprehensiveQuestions['timeline']['next'] = 'commitment_level';

        // Add commitment level, motivation, past attempts after timeline
        $comprehensiveQuestions['commitment_level'] = [
            'prompt' => $t['commitment_level_prompt'],
            'type' => 'list',
            'header' => $t['commitment_level_header'],
            'body' => $t['commitment_level_body'],
            'options' => [
                ['id' => '1', 'title' => $t['very_committed']],
                ['id' => '2', 'title' => $t['mostly']],
                ['id' => '3', 'title' => $t['moderate_commitment']],
                ['id' => '4', 'title' => $t['flexible_commitment']],
                ['id' => '5', 'title' => $t['gradual']]
            ],
            'next' => 'motivation',
            'phase' => 6
        ];

        $comprehensiveQuestions['motivation'] = [
            'prompt' => $t['motivation_prompt'],
            'type' => 'list',
            'header' => $t['motivation_header'],
            'body' => $t['motivation_body'],
            'options' => [
                ['id' => '1', 'title' => $t['better_health']],
                ['id' => '2', 'title' => $t['appearance']],
                ['id' => '3', 'title' => $t['performance']],
                ['id' => '4', 'title' => $t['energy']],
                ['id' => '5', 'title' => $t['medical']],
                ['id' => '6', 'title' => $t['family']],
                ['id' => '7', 'title' => $t['event']]
            ],
            'next' => 'past_attempts',
            'phase' => 6
        ];

        $comprehensiveQuestions['past_attempts'] = [
            'prompt' => $t['past_attempts_prompt'],
            'type' => 'list',
            'header' => $t['past_attempts_header'],
            'body' => $t['past_attempts_body'],
            'options' => [
                ['id' => '1', 'title' => $t['many_little']],
                ['id' => '2', 'title' => $t['some_mixed']],
                ['id' => '3', 'title' => $t['few_limited']],
                ['id' => '4', 'title' => $t['success_regained']],
                ['id' => '5', 'title' => $t['first_attempt']]
            ],
            'next' => 'water_intake',
            'phase' => 6
        ];

        // Modify plan_type to add more customization options
        $comprehensiveQuestions['plan_type']['next'] = 'detail_level';

        // Add detail level, recipe complexity, and meal variety after plan_type
        $comprehensiveQuestions['detail_level'] = [
            'prompt' => $t['detail_level_prompt'],
            'type' => 'list',
            'header' => $t['detail_level_header'],
            'body' => $t['detail_level_body'],
            'options' => [
                ['id' => '1', 'title' => $t['very_detailed']],
                ['id' => '2', 'title' => $t['moderately_detailed']],
                ['id' => '3', 'title' => $t['general_guidelines']],
                ['id' => '4', 'title' => $t['simple_flexible']]
            ],
            'next' => 'recipe_complexity',
            'phase' => 7
        ];

        $comprehensiveQuestions['recipe_complexity'] = [
            'prompt' => $t['recipe_complexity_prompt'],
            'type' => 'list',
            'header' => $t['recipe_complexity_header'],
            'body' => $t['recipe_complexity_body'],
            'options' => [
                ['id' => '1', 'title' => $t['very_simple']],
                ['id' => '2', 'title' => $t['moderately_simple']],
                ['id' => '3', 'title' => $t['balanced_complexity']],
                ['id' => '4', 'title' => $t['complex']]
            ],
            'next' => 'meal_variety',
            'phase' => 7
        ];

        $comprehensiveQuestions['meal_variety'] = [
            'prompt' => $t['meal_variety_prompt'],
            'type' => 'list',
            'header' => $t['meal_variety_header'],
            'body' => $t['meal_variety_body'],
            'options' => [
                ['id' => '1', 'title' => $t['high_variety']],
                ['id' => '2', 'title' => $t['moderate_variety']],
                ['id' => '3', 'title' => $t['limited_variety']],
                ['id' => '4', 'title' => $t['same_meals']]
            ],
            'next' => 'additional_requests',
            'phase' => 7
        ];

        $comprehensiveQuestions['additional_requests'] = [
            'prompt' => $t['additional_requests_prompt'],
            'type' => 'text',
            'next' => 'complete',
            'phase' => 7
        ];

        return $comprehensiveQuestions;
    }
}