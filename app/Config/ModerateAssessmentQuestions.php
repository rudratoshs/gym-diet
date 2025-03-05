<?php
// app/Config/ModerateAssessmentQuestions.php

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
        $t = LanguageTranslations::getTranslations($lang);
        $quickQuestions = QuickAssessmentQuestions::getQuestions($lang);

        $moderateQuestions = [
            // PHASE 1: Basic Questions
            'activity_level' => array_merge($quickQuestions['activity_level'], [
                'next' => 'medical_history'
            ]),

            // PHASE 2: Health Assessment
            'medical_history' => [
                'prompt' => $t['medical_history_prompt'],
                'type' => 'list',
                'multiple' => true,
                'header' => $t['medical_history_header'],
                'body' => $t['medical_history_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['heart_disease']],
                    ['id' => '2', 'title' => $t['high_cholesterol']],
                    ['id' => '3', 'title' => $t['hypertension']],
                    ['id' => '4', 'title' => $t['diabetes']],
                    ['id' => '5', 'title' => $t['cancer']],
                    ['id' => '6', 'title' => $t['autoimmune']],
                    ['id' => '7', 'title' => $t['gastrointestinal']],
                    ['id' => '8', 'title' => $t['mental_health']],
                    ['id' => '9', 'title' => $t['none_medical']]
                ],
                'next' => 'health_conditions',
                'phase' => 2
            ],
            'health_conditions' => [
                'prompt' => $t['health_prompt'],
                'type' => 'list',
                'multiple' => true,
                'header' => $t['health_header'],
                'body' => $t['health_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['diabetes']],
                    ['id' => '2', 'title' => $t['hypertension']],
                    ['id' => '3', 'title' => $t['heart']],
                    ['id' => '4', 'title' => $t['kidney']],
                    ['id' => '5', 'title' => $t['liver']],
                    ['id' => '6', 'title' => $t['digestive']],
                    ['id' => '7', 'title' => $t['gerd']],
                    ['id' => '8', 'title' => $t['ibs']],
                    ['id' => '9', 'title' => $t['hormonal']],
                    ['id' => '10', 'title' => $t['thyroid']],
                    ['id' => '11', 'title' => $t['pcos']],
                    ['id' => '12', 'title' => $t['respiratory']],
                    ['id' => '13', 'title' => $t['joint_pain']],
                    ['id' => '14', 'title' => $t['skin_conditions']],
                    ['id' => '15', 'title' => $t['none_health']],
                    ['id' => '16', 'title' => $t['other_health']]
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
                'prompt' => $t['health_details_prompt'],
                'type' => 'text',
                'next' => 'allergies',
                'phase' => 2
            ],

            // PHASE 3: Diet Preferences
            'allergies' => [
                'prompt' => $t['allergies_prompt'],
                'type' => 'list',
                'multiple' => true,
                'header' => $t['allergies_header'],
                'body' => $t['allergies_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['dairy']],
                    ['id' => '2', 'title' => $t['gluten']],
                    ['id' => '3', 'title' => $t['tree_nuts']],
                    ['id' => '4', 'title' => $t['peanuts']],
                    ['id' => '5', 'title' => $t['seafood']],
                    ['id' => '6', 'title' => $t['eggs']],
                    ['id' => '7', 'title' => $t['soy']],
                    ['id' => '8', 'title' => $t['corn']],
                    ['id' => '9', 'title' => $t['fruits']],
                    ['id' => '10', 'title' => $t['nightshades']],
                    ['id' => '11', 'title' => $t['sulfites']],
                    ['id' => '12', 'title' => $t['fodmaps']],
                    ['id' => '13', 'title' => $t['other_allergy']],
                    ['id' => '14', 'title' => $t['none_allergy']]
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
                'prompt' => $t['allergy_details_prompt'],
                'type' => 'text',
                'next' => 'diet_type',
                'phase' => 2
            ],
            'diet_type' => [
                'prompt' => $t['diet_prompt'],
                'type' => 'list',
                'header' => $t['diet_header'],
                'body' => $t['diet_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['omnivore']],
                    ['id' => '2', 'title' => $t['vegetarian']],
                    ['id' => '3', 'title' => $t['eggetarian']],
                    ['id' => '4', 'title' => $t['vegan']],
                    ['id' => '5', 'title' => $t['pescatarian']],
                    ['id' => '6', 'title' => $t['flexitarian']],
                    ['id' => '7', 'title' => $t['keto']],
                    ['id' => '8', 'title' => $t['paleo']],
                    ['id' => '9', 'title' => $t['jain']],
                    ['id' => '10', 'title' => $t['mediterranean']],
                    ['id' => '11', 'title' => $t['dash']],
                    ['id' => '12', 'title' => $t['fodmap']],
                    ['id' => '13', 'title' => $t['raw_food']],
                    ['id' => '14', 'title' => $t['other_diet']]
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
                'prompt' => $t['diet_type_other_prompt'],
                'type' => 'text',
                'next' => 'food_restrictions',
                'phase' => 3
            ],
            'food_restrictions' => [
                'prompt' => $t['restrictions_prompt'],
                'type' => 'list',
                'multiple' => true,
                'header' => $t['restrictions_header'],
                'body' => $t['restrictions_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['red_meat']],
                    ['id' => '2', 'title' => $t['poultry']],
                    ['id' => '3', 'title' => $t['seafood_r']],
                    ['id' => '4', 'title' => $t['eggs_r']],
                    ['id' => '5', 'title' => $t['dairy_r']],
                    ['id' => '6', 'title' => $t['wheat_gluten']],
                    ['id' => '7', 'title' => $t['corn']],
                    ['id' => '8', 'title' => $t['soy_r']],
                    ['id' => '9', 'title' => $t['nightshades_r']],
                    ['id' => '10', 'title' => $t['onion_garlic']],
                    ['id' => '11', 'title' => $t['root_veg']],
                    ['id' => '12', 'title' => $t['nuts_r']],
                    ['id' => '13', 'title' => $t['processed']],
                    ['id' => '14', 'title' => $t['added_sugar']],
                    ['id' => '15', 'title' => $t['other_restriction']],
                    ['id' => '16', 'title' => $t['none_restriction']]
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
                'prompt' => $t['food_restrictions_other_prompt'],
                'type' => 'text',
                'next' => 'spice_preference',
                'phase' => 3
            ],
            'spice_preference' => [
                'prompt' => $t['spice_preference_prompt'],
                'type' => 'list',
                'header' => $t['spice_preference_header'],
                'body' => $t['spice_preference_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['mild_spice']],
                    ['id' => '2', 'title' => $t['low_spice']],
                    ['id' => '3', 'title' => $t['medium_spice']],
                    ['id' => '4', 'title' => $t['spicy']],
                    ['id' => '5', 'title' => $t['very_spicy']]
                ],
                'next' => 'daily_schedule',
                'phase' => 3
            ],

            // PHASE 5: Lifestyle
            'daily_schedule' => [
                'prompt' => $t['schedule_prompt'],
                'type' => 'list',
                'header' => $t['schedule_header'],
                'body' => $t['schedule_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['early_riser']],
                    ['id' => '2', 'title' => $t['standard']],
                    ['id' => '3', 'title' => $t['late_riser']],
                    ['id' => '4', 'title' => $t['night_shift']],
                    ['id' => '5', 'title' => $t['irregular']]
                ],
                'next' => 'cooking_capability',
                'phase' => 5
            ],
            'cooking_capability' => [
                'prompt' => $t['cooking_prompt'],
                'type' => 'list',
                'header' => $t['cooking_header'],
                'body' => $t['cooking_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['full_cooking']],
                    ['id' => '2', 'title' => $t['basic_cooking']],
                    ['id' => '3', 'title' => $t['minimal_cooking']],
                    ['id' => '4', 'title' => $t['prepared_food']],
                    ['id' => '5', 'title' => $t['cooking_help']]
                ],
                'next' => 'exercise_routine',
                'phase' => 5
            ],
            'exercise_routine' => [
                'prompt' => $t['exercise_prompt'],
                'type' => 'list',
                'header' => $t['exercise_header'],
                'body' => $t['exercise_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['strength']],
                    ['id' => '2', 'title' => $t['cardio']],
                    ['id' => '3', 'title' => $t['mix_exercise']],
                    ['id' => '4', 'title' => $t['yoga']],
                    ['id' => '5', 'title' => $t['sport']],
                    ['id' => '6', 'title' => $t['minimal_ex']]
                ],
                'next' => 'exercise_frequency',
                'phase' => 5
            ],
            'exercise_frequency' => [
                'prompt' => $t['exercise_frequency_prompt'],
                'type' => 'list',
                'header' => $t['exercise_frequency_header'],
                'body' => $t['exercise_frequency_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['daily']],
                    ['id' => '2', 'title' => $t['4to6_weekly']],
                    ['id' => '3', 'title' => $t['2to3_weekly']],
                    ['id' => '4', 'title' => $t['once_weekly']],
                    ['id' => '5', 'title' => $t['rarely']]
                ],
                'next' => 'stress_sleep',
                'phase' => 5
            ],
            'stress_sleep' => [
                'prompt' => $t['stress_prompt'],
                'type' => 'list',
                'header' => $t['stress_header'],
                'body' => $t['stress_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['low_good']],
                    ['id' => '2', 'title' => $t['moderate_ok']],
                    ['id' => '3', 'title' => $t['high_enough']],
                    ['id' => '4', 'title' => $t['low_poor']],
                    ['id' => '5', 'title' => $t['high_poor']]
                ],
                'next' => 'primary_goal',
                'phase' => 5
            ],

            // PHASE 6: Goals
            'primary_goal' => [
                'prompt' => $t['goal_prompt'],
                'type' => 'list',
                'header' => $t['goal_header'],
                'body' => $t['goal_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['weight_loss']],
                    ['id' => '2', 'title' => $t['muscle_gain']],
                    ['id' => '3', 'title' => $t['maintain']],
                    ['id' => '4', 'title' => $t['energy']],
                    ['id' => '5', 'title' => $t['digestion']],
                    ['id' => '6', 'title' => $t['overall_health']],
                    ['id' => '7', 'title' => $t['recovery']],
                    ['id' => '8', 'title' => $t['athletic']],
                    ['id' => '9', 'title' => $t['longevity']],
                    ['id' => '10', 'title' => $t['hormone_balance']],
                    ['id' => '11', 'title' => $t['mental_clarity']]
                ],
                'next' => 'weight_goal',
                'phase' => 6
            ],
            'weight_goal' => [
                'prompt' => $t['weight_goal_prompt'],
                'type' => 'list',
                'header' => $t['weight_goal_header'],
                'body' => $t['weight_goal_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['rapid_loss']],
                    ['id' => '2', 'title' => $t['moderate_loss']],
                    ['id' => '3', 'title' => $t['slow_loss']],
                    ['id' => '4', 'title' => $t['maintain']],
                    ['id' => '5', 'title' => $t['slight_gain']],
                    ['id' => '6', 'title' => $t['moderate_gain']],
                    ['id' => '7', 'title' => $t['significant_gain']]
                ],
                'next' => 'timeline',
                'phase' => 6
            ],
            'timeline' => [
                'prompt' => $t['timeline_prompt'],
                'type' => 'list',
                'header' => $t['timeline_header'],
                'body' => $t['timeline_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['short_term']],
                    ['id' => '2', 'title' => $t['medium_term']],
                    ['id' => '3', 'title' => $t['long_term']],
                    ['id' => '4', 'title' => $t['lifestyle']]
                ],
                'next' => 'water_intake',
                'phase' => 6
            ],
            'water_intake' => [
                'prompt' => $t['water_intake_prompt'],
                'type' => 'list',
                'header' => $t['water_intake_header'],
                'body' => $t['water_intake_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['water_lt1']],
                    ['id' => '2', 'title' => $t['water_1to2']],
                    ['id' => '3', 'title' => $t['water_2to3']],
                    ['id' => '4', 'title' => $t['water_gt3']],
                    ['id' => '5', 'title' => $t['water_unknown']]
                ],
                'next' => 'plan_type',
                'phase' => 6
            ],

            // PHASE 7: Plan Customization
            'plan_type' => [
                'prompt' => $t['plan_prompt'],
                'type' => 'button',
                'options' => [
                    ['id' => 'complete', 'title' => $t['complete_plan']],
                    ['id' => 'basic', 'title' => $t['basic_plan']],
                    ['id' => 'focus', 'title' => $t['focus_plan']]
                ],
                'next' => 'complete',
                'phase' => 7
            ],
            'complete' => [
                'prompt' => $t['complete_prompt'],
                'type' => 'text',
                'is_final' => true,
                'phase' => 7
            ]
        ];

        return $moderateQuestions;
    }
}