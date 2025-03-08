<?php
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
        // Use Laravel's built-in translation system
        app()->setLocale($lang);  // Set the locale dynamically

        $moderateQuestions = ModerateAssessmentQuestions::getQuestions($lang);

        // Start with moderate questions
        $comprehensiveQuestions = $moderateQuestions;

        // Modify the health_conditions question flow to include medications
        $comprehensiveQuestions['health_conditions']['next_conditional']['default'] = 'medications';

        // Add medications section after health conditions
        $comprehensiveQuestions['medications'] = [
            'prompt' => __('attributes.meds_prompt'),
            'type' => 'list',
            'header' => __('attributes.meds_header'),
            'body' => __('attributes.meds_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.rx_meds')],
                ['id' => '2', 'title' => __('attributes.otc_meds')],
                ['id' => '3', 'title' => __('attributes.supplements')],
                ['id' => '4', 'title' => __('attributes.combo_meds')],
                ['id' => '5', 'title' => __('attributes.no_meds')]
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
            'prompt' => __('attributes.meds_detail_prompt'),
            'type' => 'text',
            'next' => 'allergies',
            'phase' => 2
        ];

        // Modify allergies to include recovery needs
        $comprehensiveQuestions['allergies']['next_conditional']['default'] = 'recovery_needs';

        // Add recovery needs after allergies
        $comprehensiveQuestions['recovery_needs'] = [
            'prompt' => __('attributes.recovery_needs_prompt'),
            'type' => 'list',
            'multiple' => true,
            'header' => __('attributes.recovery_needs_header'),
            'body' => __('attributes.recovery_needs_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.weight_loss_need')],
                ['id' => '2', 'title' => __('attributes.muscle_gain_need')],
                ['id' => '3', 'title' => __('attributes.digestive_health')],
                ['id' => '4', 'title' => __('attributes.energy_improvement')],
                ['id' => '5', 'title' => __('attributes.blood_sugar')],
                ['id' => '6', 'title' => __('attributes.cholesterol')],
                ['id' => '7', 'title' => __('attributes.inflammation')],
                ['id' => '8', 'title' => __('attributes.detoxification')],
                ['id' => '9', 'title' => __('attributes.immune_support')],
                ['id' => '10', 'title' => __('attributes.sleep_improvement')],
                ['id' => '11', 'title' => __('attributes.stress_management')],
                ['id' => '12', 'title' => __('attributes.hair_skin')],
                ['id' => '13', 'title' => __('attributes.hormone_balance_need')],
                ['id' => '14', 'title' => __('attributes.organ_recovery')],
                ['id' => '15', 'title' => __('attributes.post_surgery')],
                ['id' => '16', 'title' => __('attributes.none_specifically')]
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
            'prompt' => __('attributes.organ_recovery_prompt'),
            'type' => 'list',
            'multiple' => true,
            'header' => __('attributes.organ_recovery_header'),
            'body' => __('attributes.organ_recovery_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.liver_rx')],
                ['id' => '2', 'title' => __('attributes.kidneys_rx')],
                ['id' => '3', 'title' => __('attributes.heart_rx')],
                ['id' => '4', 'title' => __('attributes.lungs')],
                ['id' => '5', 'title' => __('attributes.digestive_system')],
                ['id' => '6', 'title' => __('attributes.pancreas')],
                ['id' => '7', 'title' => __('attributes.other_organ')]
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
            'prompt' => __('attributes.organ_recovery_details_prompt'),
            'type' => 'text',
            'next' => 'diet_type',
            'phase' => 2
        ];

        $comprehensiveQuestions['surgery_details'] = [
            'prompt' => __('attributes.surgery_details_prompt'),
            'type' => 'text',
            'next' => 'diet_type',
            'phase' => 2
        ];

        // Modify diet_type to include religious dietary practices
        $comprehensiveQuestions['diet_type']['next_conditional']['default'] = 'religion_diet';

        // Add religious dietary practices after diet_type
        $comprehensiveQuestions['religion_diet'] = [
            'prompt' => __('attributes.religion_diet_prompt'),
            'type' => 'list',
            'header' => __('attributes.religion_diet_header'),
            'body' => __('attributes.religion_diet_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.kosher')],
                ['id' => '2', 'title' => __('attributes.halal')],
                ['id' => '3', 'title' => __('attributes.hindu_veg')],
                ['id' => '4', 'title' => __('attributes.buddhist_veg')],
                ['id' => '5', 'title' => __('attributes.jain_veg')],
                ['id' => '6', 'title' => __('attributes.fasting')],
                ['id' => '7', 'title' => __('attributes.other_religious')],
                ['id' => '8', 'title' => __('attributes.none_religious')]
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
            'prompt' => __('attributes.religion_diet_details_prompt'),
            'type' => 'text',
            'next' => 'food_restrictions',
            'phase' => 3
        ];

        $comprehensiveQuestions['fasting_details'] = [
            'prompt' => __('attributes.fasting_details_prompt'),
            'type' => 'text',
            'next' => 'food_restrictions',
            'phase' => 3
        ];

        // Modify food_restrictions to point to cuisine_preferences
        $comprehensiveQuestions['food_restrictions']['next_conditional']['default'] = 'cuisine_preferences';

        // Add cuisine preferences after food restrictions
        $comprehensiveQuestions['cuisine_preferences'] = [
            'prompt' => __('attributes.cuisine_preferences_prompt'),
            'type' => 'list',
            'multiple' => true,
            'header' => __('attributes.cuisine_preferences_header'),
            'body' => __('attributes.cuisine_preferences_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.north_indian')],
                ['id' => '2', 'title' => __('attributes.south_indian')],
                ['id' => '3', 'title' => __('attributes.east_indian')],
                ['id' => '4', 'title' => __('attributes.west_indian')],
                ['id' => '5', 'title' => __('attributes.punjabi')],
                ['id' => '6', 'title' => __('attributes.gujarati')],
                ['id' => '7', 'title' => __('attributes.bengali')],
                ['id' => '8', 'title' => __('attributes.mediterranean_cuisine')],
                ['id' => '9', 'title' => __('attributes.chinese_cuisine')],
                ['id' => '10', 'title' => __('attributes.japanese')],
                ['id' => '11', 'title' => __('attributes.korean')],
                ['id' => '12', 'title' => __('attributes.thai')],
                ['id' => '13', 'title' => __('attributes.vietnamese')],
                ['id' => '14', 'title' => __('attributes.middle_eastern')],
                ['id' => '15', 'title' => __('attributes.mexican')],
                ['id' => '16', 'title' => __('attributes.italian_cuisine')],
                ['id' => '17', 'title' => __('attributes.continental')],
                ['id' => '18', 'title' => __('attributes.no_specific_cuisine')]
            ],
            'next' => 'meal_timing',
            'phase' => 3
        ];

        // Add meal timing after cuisine preferences
        $comprehensiveQuestions['meal_timing'] = [
            'prompt' => __('attributes.meal_timing_prompt'),
            'type' => 'list',
            'header' => __('attributes.meal_timing_header'),
            'body' => __('attributes.meal_timing_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.traditional_meals')],
                ['id' => '2', 'title' => __('attributes.small_frequent')],
                ['id' => '3', 'title' => __('attributes.intermittent_16_8')],
                ['id' => '4', 'title' => __('attributes.intermittent_18_6')],
                ['id' => '5', 'title' => __('attributes.omad')],
                ['id' => '6', 'title' => __('attributes.flexible_pattern')]
            ],
            'next' => 'meal_preferences',
            'phase' => 4
        ];

        // Add meal preferences after meal timing
        $comprehensiveQuestions['meal_preferences'] = [
            'prompt' => __('attributes.meal_preferences_prompt'),
            'type' => 'list',
            'multiple' => true,
            'header' => __('attributes.meal_preferences_header'),
            'body' => __('attributes.meal_preferences_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.high_protein')],
                ['id' => '2', 'title' => __('attributes.low_carb')],
                ['id' => '3', 'title' => __('attributes.low_fat')],
                ['id' => '4', 'title' => __('attributes.gluten_free')],
                ['id' => '5', 'title' => __('attributes.dairy_free')],
                ['id' => '6', 'title' => __('attributes.sugar_free')],
                ['id' => '7', 'title' => __('attributes.low_sodium')],
                ['id' => '8', 'title' => __('attributes.whole_foods')],
                ['id' => '9', 'title' => __('attributes.plant_based')],
                ['id' => '10', 'title' => __('attributes.seasonal')],
                ['id' => '11', 'title' => __('attributes.balanced_macros')],
                ['id' => '12', 'title' => __('attributes.home_cooking')],
                ['id' => '13', 'title' => __('attributes.no_specific_prefs')]
            ],
            'next' => 'favorite_foods',
            'phase' => 4
        ];

        // Add favorite and disliked foods
        $comprehensiveQuestions['favorite_foods'] = [
            'prompt' => __('attributes.favorite_foods_prompt'),
            'type' => 'text',
            'next' => 'disliked_foods',
            'phase' => 4
        ];

        $comprehensiveQuestions['disliked_foods'] = [
            'prompt' => __('attributes.disliked_foods_prompt'),
            'type' => 'text',
            'next' => 'nutrition_knowledge',
            'phase' => 4
        ];

        $comprehensiveQuestions['nutrition_knowledge'] = [
            'prompt' => __('attributes.nutrition_knowledge_prompt'),
            'type' => 'list',
            'header' => __('attributes.nutrition_knowledge_header'),
            'body' => __('attributes.nutrition_knowledge_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.beginner')],
                ['id' => '2', 'title' => __('attributes.basic')],
                ['id' => '3', 'title' => __('attributes.intermediate')],
                ['id' => '4', 'title' => __('attributes.advanced')],
                ['id' => '5', 'title' => __('attributes.expert')]
            ],
            'next' => 'daily_schedule',
            'phase' => 4
        ];

        // Modify daily_schedule to add work_type
        $comprehensiveQuestions['daily_schedule']['next'] = 'work_type';

        // Add work type after daily schedule
        $comprehensiveQuestions['work_type'] = [
            'prompt' => __('attributes.work_type_prompt'),
            'type' => 'list',
            'header' => __('attributes.work_type_header'),
            'body' => __('attributes.work_type_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.desk_job')],
                ['id' => '2', 'title' => __('attributes.moderate_physical')],
                ['id' => '3', 'title' => __('attributes.demanding_job')],
                ['id' => '4', 'title' => __('attributes.student')],
                ['id' => '5', 'title' => __('attributes.parent')],
                ['id' => '6', 'title' => __('attributes.retired')],
                ['id' => '7', 'title' => __('attributes.unemployed')]
            ],
            'next' => 'cooking_capability',
            'phase' => 5
        ];

        // Modify cooking_capability to add cooking_time
        $comprehensiveQuestions['cooking_capability']['next'] = 'cooking_time';

        // Add cooking time after cooking capability
        $comprehensiveQuestions['cooking_time'] = [
            'prompt' => __('attributes.cooking_time_prompt'),
            'type' => 'list',
            'header' => __('attributes.cooking_time_header'),
            'body' => __('attributes.cooking_time_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.minimal_time')],
                ['id' => '2', 'title' => __('attributes.brief_time')],
                ['id' => '3', 'title' => __('attributes.moderate_time')],
                ['id' => '4', 'title' => __('attributes.extended_time')],
                ['id' => '5', 'title' => __('attributes.batch_cooking')]
            ],
            'next' => 'grocery_access',
            'phase' => 5
        ];

        // Add grocery access after cooking time
        $comprehensiveQuestions['grocery_access'] = [
            'prompt' => __('attributes.grocery_access_prompt'),
            'type' => 'list',
            'header' => __('attributes.grocery_access_header'),
            'body' => __('attributes.grocery_access_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.excellent_variety')],
                ['id' => '2', 'title' => __('attributes.good_access')],
                ['id' => '3', 'title' => __('attributes.limited_options')],
                ['id' => '4', 'title' => __('attributes.delivery_services')],
                ['id' => '5', 'title' => __('attributes.challenging_access')]
            ],
            'next' => 'budget_constraints',
            'phase' => 5
        ];

        // Add budget constraints after grocery access
        $comprehensiveQuestions['budget_constraints'] = [
            'prompt' => __('attributes.budget_constraints_prompt'),
            'type' => 'list',
            'header' => __('attributes.budget_constraints_header'),
            'body' => __('attributes.budget_constraints_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.very_budget')],
                ['id' => '2', 'title' => __('attributes.moderately_budget')],
                ['id' => '3', 'title' => __('attributes.flexible_budget')],
                ['id' => '4', 'title' => __('attributes.no_constraints')]
            ],
            'next' => 'exercise_routine',
            'phase' => 5
        ];

        // Modify exercise frequency to include exercise timing
        $comprehensiveQuestions['exercise_frequency']['next'] = 'exercise_timing';

        // Add exercise timing after exercise frequency
        $comprehensiveQuestions['exercise_timing'] = [
            'prompt' => __('attributes.exercise_timing_prompt'),
            'type' => 'list',
            'header' => __('attributes.exercise_timing_header'),
            'body' => __('attributes.exercise_timing_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.early_morning')],
                ['id' => '2', 'title' => __('attributes.morning')],
                ['id' => '3', 'title' => __('attributes.midday')],
                ['id' => '4', 'title' => __('attributes.afternoon')],
                ['id' => '5', 'title' => __('attributes.evening')],
                ['id' => '6', 'title' => __('attributes.night')],
                ['id' => '7', 'title' => __('attributes.varies')]
            ],
            'next' => 'stress_sleep',
            'phase' => 5
        ];

        // Modify stress_sleep to add sleep hours
        $comprehensiveQuestions['stress_sleep']['next'] = 'sleep_hours';

        // Add sleep hours after stress sleep
        $comprehensiveQuestions['sleep_hours'] = [
            'prompt' => __('attributes.sleep_hours_prompt'),
            'type' => 'list',
            'header' => __('attributes.sleep_hours_header'),
            'body' => __('attributes.sleep_hours_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.less_than_5')],
                ['id' => '2', 'title' => __('attributes.5_to_6')],
                ['id' => '3', 'title' => __('attributes.6_to_7')],
                ['id' => '4', 'title' => __('attributes.7_to_8')],
                ['id' => '5', 'title' => __('attributes.8_to_9')],
                ['id' => '6', 'title' => __('attributes.more_than_9')],
                ['id' => '7', 'title' => __('attributes.variable_sleep')]
            ],
            'next' => 'primary_goal',
            'phase' => 5
        ];

        // Modify timeline to add commitment level
        $comprehensiveQuestions['timeline']['next'] = 'commitment_level';

        // Add commitment level, motivation, past attempts after timeline
        $comprehensiveQuestions['commitment_level'] = [
            'prompt' => __('attributes.commitment_level_prompt'),
            'type' => 'list',
            'header' => __('attributes.commitment_level_header'),
            'body' => __('attributes.commitment_level_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.very_committed')],
                ['id' => '2', 'title' => __('attributes.mostly')],
                ['id' => '3', 'title' => __('attributes.moderate_commitment')],
                ['id' => '4', 'title' => __('attributes.flexible_commitment')],
                ['id' => '5', 'title' => __('attributes.gradual')]
            ],
            'next' => 'motivation',
            'phase' => 6
        ];

        $comprehensiveQuestions['motivation'] = [
            'prompt' => __('attributes.motivation_prompt'),
            'type' => 'list',
            'header' => __('attributes.motivation_header'),
            'body' => __('attributes.motivation_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.better_health')],
                ['id' => '2', 'title' => __('attributes.appearance')],
                ['id' => '3', 'title' => __('attributes.performance')],
                ['id' => '4', 'title' => __('attributes.energy')],
                ['id' => '5', 'title' => __('attributes.medical')],
                ['id' => '6', 'title' => __('attributes.family')],
                ['id' => '7', 'title' => __('attributes.event')]
            ],
            'next' => 'past_attempts',
            'phase' => 6
        ];

        $comprehensiveQuestions['past_attempts'] = [
            'prompt' => __('attributes.past_attempts_prompt'),
            'type' => 'list',
            'header' => __('attributes.past_attempts_header'),
            'body' => __('attributes.past_attempts_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.many_little')],
                ['id' => '2', 'title' => __('attributes.some_mixed')],
                ['id' => '3', 'title' => __('attributes.few_limited')],
                ['id' => '4', 'title' => __('attributes.success_regained')],
                ['id' => '5', 'title' => __('attributes.first_attempt')]
            ],
            'next' => 'water_intake',
            'phase' => 6
        ];

        // Modify plan_type to add more customization options
        $comprehensiveQuestions['plan_type']['next'] = 'detail_level';

        // Add detail level, recipe complexity, and meal variety after plan_type
        $comprehensiveQuestions['detail_level'] = [
            'prompt' => __('attributes.detail_level_prompt'),
            'type' => 'list',
            'header' => __('attributes.detail_level_header'),
            'body' => __('attributes.detail_level_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.very_detailed')],
                ['id' => '2', 'title' => __('attributes.moderately_detailed')],
                ['id' => '3', 'title' => __('attributes.general_guidelines')],
                ['id' => '4', 'title' => __('attributes.simple_flexible')]
            ],
            'next' => 'recipe_complexity',
            'phase' => 7
        ];

        $comprehensiveQuestions['recipe_complexity'] = [
            'prompt' => __('attributes.recipe_complexity_prompt'),
            'type' => 'list',
            'header' => __('attributes.recipe_complexity_header'),
            'body' => __('attributes.recipe_complexity_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.very_simple')],
                ['id' => '2', 'title' => __('attributes.moderately_simple')],
                ['id' => '3', 'title' => __('attributes.balanced_complexity')],
                ['id' => '4', 'title' => __('attributes.complex')]
            ],
            'next' => 'meal_variety',
            'phase' => 7
        ];

        $comprehensiveQuestions['meal_variety'] = [
            'prompt' => __('attributes.meal_variety_prompt'),
            'type' => 'list',
            'header' => __('attributes.meal_variety_header'),
            'body' => __('attributes.meal_variety_body'),
            'options' => [
                ['id' => '1', 'title' => __('attributes.high_variety')],
                ['id' => '2', 'title' => __('attributes.moderate_variety')],
                ['id' => '3', 'title' => __('attributes.limited_variety')],
                ['id' => '4', 'title' => __('attributes.same_meals')]
            ],
            'next' => 'additional_requests',
            'phase' => 7
        ];

        $comprehensiveQuestions['additional_requests'] = [
            'prompt' => __('attributes.additional_requests_prompt'),
            'type' => 'text',
            'next' => 'complete',
            'phase' => 7
        ];

        return $comprehensiveQuestions;
    }
}