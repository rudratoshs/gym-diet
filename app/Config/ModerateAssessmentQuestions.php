<?php
namespace App\Config;

class ModerateAssessmentQuestions
{
    /**
     * Get moderate assessment questions (balance between detail and time)
     * 
     * @param string $lang Language code
     * @return array
     */
    public static function getQuestions($lang = 'en')
    {
        // Use Laravel's built-in translation system
        app()->setLocale($lang);  // Set the locale dynamically

        $quickQuestions = QuickAssessmentQuestions::getQuestions($lang);

        $moderateQuestions = [
            // PHASE 1: Basic Questions
            'activity_level' => array_merge($quickQuestions['activity_level'], [
                'next' => 'medical_history'
            ]),

            // PHASE 2: Health Assessment
            'medical_history' => [
                'prompt' => __('attributes.medical_history_prompt'),
                'type' => 'list',
                'multiple' => true,
                'header' => __('attributes.medical_history_header'),
                'body' => __('attributes.medical_history_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.heart_disease')],
                    ['id' => '2', 'title' => __('attributes.high_cholesterol')],
                    ['id' => '3', 'title' => __('attributes.hypertension')],
                    ['id' => '4', 'title' => __('attributes.diabetes')],
                    ['id' => '5', 'title' => __('attributes.cancer')],
                    ['id' => '6', 'title' => __('attributes.autoimmune')],
                    ['id' => '7', 'title' => __('attributes.gastrointestinal')],
                    ['id' => '8', 'title' => __('attributes.mental_health')],
                    ['id' => '9', 'title' => __('attributes.none_medical')]
                ],
                'next' => 'health_conditions',
                'phase' => 2
            ],
            'health_conditions' => [
                'prompt' => __('attributes.health_prompt'),
                'type' => 'list',
                'multiple' => true,
                'header' => __('attributes.health_header'),
                'body' => __('attributes.health_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.diabetes')],
                    ['id' => '2', 'title' => __('attributes.hypertension')],
                    ['id' => '3', 'title' => __('attributes.heart')],
                    ['id' => '4', 'title' => __('attributes.kidney')],
                    ['id' => '5', 'title' => __('attributes.liver')],
                    ['id' => '6', 'title' => __('attributes.digestive')],
                    ['id' => '7', 'title' => __('attributes.gerd')],
                    ['id' => '8', 'title' => __('attributes.ibs')],
                    ['id' => '9', 'title' => __('attributes.hormonal')],
                    ['id' => '10', 'title' => __('attributes.thyroid')],
                    ['id' => '11', 'title' => __('attributes.pcos')],
                    ['id' => '12', 'title' => __('attributes.respiratory')],
                    ['id' => '13', 'title' => __('attributes.joint_pain')],
                    ['id' => '14', 'title' => __('attributes.skin_conditions')],
                    ['id' => '15', 'title' => __('attributes.none_health')],
                    ['id' => '16', 'title' => __('attributes.other_health')]
                ],
                'next_conditional' => [
                    'default' => 'diet_type',
                    'conditions' => [
                        ['condition' => '!in_array("15", $responses)', 'next' => 'health_details']
                    ]
                ],
                'phase' => 2
            ],
            'health_details' => [
                'prompt' => __('attributes.health_details_prompt'),
                'type' => 'text',
                'next' => 'allergies',
                'phase' => 2
            ],

            // PHASE 3: Diet Preferences
            'allergies' => [
                'prompt' => __('attributes.allergies_prompt'),
                'type' => 'list',
                'multiple' => true,
                'header' => __('attributes.allergies_header'),
                'body' => __('attributes.allergies_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.dairy')],
                    ['id' => '2', 'title' => __('attributes.gluten')],
                    ['id' => '3', 'title' => __('attributes.tree_nuts')],
                    ['id' => '4', 'title' => __('attributes.peanuts')],
                    ['id' => '5', 'title' => __('attributes.seafood')],
                    ['id' => '6', 'title' => __('attributes.eggs')],
                    ['id' => '7', 'title' => __('attributes.soy')],
                    ['id' => '8', 'title' => __('attributes.corn')],
                    ['id' => '9', 'title' => __('attributes.fruits')],
                    ['id' => '10', 'title' => __('attributes.nightshades')],
                    ['id' => '11', 'title' => __('attributes.sulfites')],
                    ['id' => '12', 'title' => __('attributes.fodmaps')],
                    ['id' => '13', 'title' => __('attributes.other_allergy')],
                    ['id' => '14', 'title' => __('attributes.none_allergy')]
                ],
                'next_conditional' => [
                    'default' => 'diet_type',
                    'conditions' => [
                        ['condition' => 'hasOtherAllergies', 'next' => 'allergy_details']
                    ]
                ],
                'phase' => 2
            ],
            'allergy_details' => [
                'prompt' => __('attributes.allergy_details_prompt'),
                'type' => 'text',
                'next' => 'diet_type',
                'phase' => 2
            ],
            'diet_type' => [
                'prompt' => __('attributes.diet_prompt'),
                'type' => 'list',
                'header' => __('attributes.diet_header'),
                'body' => __('attributes.diet_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.omnivore')],
                    ['id' => '2', 'title' => __('attributes.vegetarian')],
                    ['id' => '3', 'title' => __('attributes.eggetarian')],
                    ['id' => '4', 'title' => __('attributes.vegan')],
                    ['id' => '5', 'title' => __('attributes.pescatarian')],
                    ['id' => '6', 'title' => __('attributes.flexitarian')],
                    ['id' => '7', 'title' => __('attributes.keto')],
                    ['id' => '8', 'title' => __('attributes.paleo')],
                    ['id' => '9', 'title' => __('attributes.jain')],
                    ['id' => '10', 'title' => __('attributes.mediterranean')],
                    ['id' => '11', 'title' => __('attributes.dash')],
                    ['id' => '12', 'title' => __('attributes.fodmap')],
                    ['id' => '13', 'title' => __('attributes.raw_food')],
                    ['id' => '14', 'title' => __('attributes.other_diet')]
                ],
                'next_conditional' => [
                    'default' => 'food_restrictions',
                    'conditions' => [
                        ['condition' => '14', 'next' => 'diet_type_other']
                    ]
                ],
                'phase' => 3
            ],
            'diet_type_other' => [
                'prompt' => __('attributes.diet_type_other_prompt'),
                'type' => 'text',
                'next' => 'food_restrictions',
                'phase' => 3
            ],
            'food_restrictions' => [
                'prompt' => __('attributes.restrictions_prompt'),
                'type' => 'list',
                'multiple' => true,
                'header' => __('attributes.restrictions_header'),
                'body' => __('attributes.restrictions_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.red_meat')],
                    ['id' => '2', 'title' => __('attributes.poultry')],
                    ['id' => '3', 'title' => __('attributes.seafood_r')],
                    ['id' => '4', 'title' => __('attributes.eggs_r')],
                    ['id' => '5', 'title' => __('attributes.dairy_r')],
                    ['id' => '6', 'title' => __('attributes.wheat_gluten')],
                    ['id' => '7', 'title' => __('attributes.corn')],
                    ['id' => '8', 'title' => __('attributes.soy_r')],
                    ['id' => '9', 'title' => __('attributes.nightshades_r')],
                    ['id' => '10', 'title' => __('attributes.onion_garlic')],
                    ['id' => '11', 'title' => __('attributes.root_veg')],
                    ['id' => '12', 'title' => __('attributes.nuts_r')],
                    ['id' => '13', 'title' => __('attributes.processed')],
                    ['id' => '14', 'title' => __('attributes.added_sugar')],
                    ['id' => '15', 'title' => __('attributes.other_restriction')],
                    ['id' => '16', 'title' => __('attributes.none_restriction')]
                ],
                'next_conditional' => [
                    'default' => 'spice_preference',
                    'conditions' => [
                        ['condition' => '15', 'next' => 'food_restrictions_other']
                    ]
                ],
                'phase' => 3
            ],
            'food_restrictions_other' => [
                'prompt' => __('attributes.food_restrictions_other_prompt'),
                'type' => 'text',
                'next' => 'spice_preference',
                'phase' => 3
            ],
            'spice_preference' => [
                'prompt' => __('attributes.spice_preference_prompt'),
                'type' => 'list',
                'header' => __('attributes.spice_preference_header'),
                'body' => __('attributes.spice_preference_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.mild_spice')],
                    ['id' => '2', 'title' => __('attributes.low_spice')],
                    ['id' => '3', 'title' => __('attributes.medium_spice')],
                    ['id' => '4', 'title' => __('attributes.spicy')],
                    ['id' => '5', 'title' => __('attributes.very_spicy')]
                ],
                'next' => 'daily_schedule',
                'phase' => 3
            ],

            // PHASE 5: Lifestyle
            'daily_schedule' => [
                'prompt' => __('attributes.schedule_prompt'),
                'type' => 'list',
                'header' => __('attributes.schedule_header'),
                'body' => __('attributes.schedule_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.early_riser')],
                    ['id' => '2', 'title' => __('attributes.standard')],
                    ['id' => '3', 'title' => __('attributes.late_riser')],
                    ['id' => '4', 'title' => __('attributes.night_shift')],
                    ['id' => '5', 'title' => __('attributes.irregular')]
                ],
                'next' => 'cooking_capability',
                'phase' => 5
            ],
            'cooking_capability' => [
                'prompt' => __('attributes.cooking_prompt'),
                'type' => 'list',
                'header' => __('attributes.cooking_header'),
                'body' => __('attributes.cooking_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.full_cooking')],
                    ['id' => '2', 'title' => __('attributes.basic_cooking')],
                    ['id' => '3', 'title' => __('attributes.minimal_cooking')],
                    ['id' => '4', 'title' => __('attributes.prepared_food')],
                    ['id' => '5', 'title' => __('attributes.cooking_help')]
                ],
                'next' => 'exercise_routine',
                'phase' => 5
            ],
            'exercise_routine' => [
                'prompt' => __('attributes.exercise_prompt'),
                'type' => 'list',
                'header' => __('attributes.exercise_header'),
                'body' => __('attributes.exercise_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.strength')],
                    ['id' => '2', 'title' => __('attributes.cardio')],
                    ['id' => '3', 'title' => __('attributes.mix_exercise')],
                    ['id' => '4', 'title' => __('attributes.yoga')],
                    ['id' => '5', 'title' => __('attributes.sport')],
                    ['id' => '6', 'title' => __('attributes.minimal_ex')]
                ],
                'next' => 'exercise_frequency',
                'phase' => 5
            ],
            'exercise_frequency' => [
                'prompt' => __('attributes.exercise_frequency_prompt'),
                'type' => 'list',
                'header' => __('attributes.exercise_frequency_header'),
                'body' => __('attributes.exercise_frequency_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.daily')],
                    ['id' => '2', 'title' => __('attributes.4to6_weekly')],
                    ['id' => '3', 'title' => __('attributes.2to3_weekly')],
                    ['id' => '4', 'title' => __('attributes.once_weekly')],
                    ['id' => '5', 'title' => __('attributes.rarely')]
                ],
                'next' => 'stress_sleep',
                'phase' => 5
            ],
            'stress_sleep' => [
                'prompt' => __('attributes.stress_prompt'),
                'type' => 'list',
                'header' => __('attributes.stress_header'),
                'body' => __('attributes.stress_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.low_good')],
                    ['id' => '2', 'title' => __('attributes.moderate_ok')],
                    ['id' => '3', 'title' => __('attributes.high_enough')],
                    ['id' => '4', 'title' => __('attributes.low_poor')],
                    ['id' => '5', 'title' => __('attributes.high_poor')]
                ],
                'next' => 'primary_goal',
                'phase' => 5
            ],

            // PHASE 6: Goals
            'primary_goal' => [
                'prompt' => __('attributes.goal_prompt'),
                'type' => 'list',
                'header' => __('attributes.goal_header'),
                'body' => __('attributes.goal_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.weight_loss')],
                    ['id' => '2', 'title' => __('attributes.muscle_gain')],
                    ['id' => '3', 'title' => __('attributes.maintain')],
                    ['id' => '4', 'title' => __('attributes.energy')],
                    ['id' => '5', 'title' => __('attributes.digestion')],
                    ['id' => '6', 'title' => __('attributes.overall_health')],
                    ['id' => '7', 'title' => __('attributes.recovery')],
                    ['id' => '8', 'title' => __('attributes.athletic')],
                    ['id' => '9', 'title' => __('attributes.longevity')],
                    ['id' => '10', 'title' => __('attributes.hormone_balance')],
                    ['id' => '11', 'title' => __('attributes.mental_clarity')]
                ],
                'next' => 'weight_goal',
                'phase' => 6
            ],
            'weight_goal' => [
                'prompt' => __('attributes.weight_goal_prompt'),
                'type' => 'list',
                'header' => __('attributes.weight_goal_header'),
                'body' => __('attributes.weight_goal_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.rapid_loss')],
                    ['id' => '2', 'title' => __('attributes.moderate_loss')],
                    ['id' => '3', 'title' => __('attributes.slow_loss')],
                    ['id' => '4', 'title' => __('attributes.maintain')],
                    ['id' => '5', 'title' => __('attributes.slight_gain')],
                    ['id' => '6', 'title' => __('attributes.moderate_gain')],
                    ['id' => '7', 'title' => __('attributes.significant_gain')]
                ],
                'next' => 'timeline',
                'phase' => 6
            ],
            'timeline' => [
                'prompt' => __('attributes.timeline_prompt'),
                'type' => 'list',
                'header' => __('attributes.timeline_header'),
                'body' => __('attributes.timeline_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.short_term')],
                    ['id' => '2', 'title' => __('attributes.medium_term')],
                    ['id' => '3', 'title' => __('attributes.long_term')],
                    ['id' => '4', 'title' => __('attributes.lifestyle')]
                ],
                'next' => 'water_intake',
                'phase' => 6
            ],
            'water_intake' => [
                'prompt' => __('attributes.water_intake_prompt'),
                'type' => 'list',
                'header' => __('attributes.water_intake_header'),
                'body' => __('attributes.water_intake_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.water_lt1')],
                    ['id' => '2', 'title' => __('attributes.water_1to2')],
                    ['id' => '3', 'title' => __('attributes.water_2to3')],
                    ['id' => '4', 'title' => __('attributes.water_gt3')],
                    ['id' => '5', 'title' => __('attributes.water_unknown')]
                ],
                'next' => 'plan_type',
                'phase' => 6
            ],

            // PHASE 7: Plan Customization
            'plan_type' => [
                'prompt' => __('attributes.plan_prompt'),
                'type' => 'button',
                'options' => [
                    ['id' => 'complete', 'title' => __('attributes.complete_plan')],
                    ['id' => 'basic', 'title' => __('attributes.basic_plan')],
                    ['id' => 'focus', 'title' => __('attributes.focus_plan')]
                ],
                'next' => 'complete',
                'phase' => 7
            ],
            'complete' => [
                'prompt' => __('attributes.complete_prompt'),
                'type' => 'text',
                'is_final' => true,
                'phase' => 7
            ]
        ];

        return $moderateQuestions;
    }
}