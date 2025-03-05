<?php
// app/Config/QuickAssessmentQuestions.php

namespace App\Config;

class QuickAssessmentQuestions
{
    /**
     * Get quick assessment questions (minimal set for fastest completion)
     * 
     * @param string $lang Language code
     * @return array
     */
    public static function getQuestions($lang = 'en')
    {
        $t = LanguageTranslations::getTranslations($lang);

        return [
            // PHASE 1: Basic Information
            'age' => [
                'prompt' => $t['age_prompt'],
                'type' => 'text',
                'validation' => 'numeric|min:12|max:120',
                'error_message' => $t['age_error'],
                'next' => 'country',
                'phase' => 1
            ],
            'country' => [
                'prompt' => $t['country_prompt'],
                'type' => 'text',
                'next' => 'state',
                'phase' => 2
            ],
            'state' => [
                'prompt' => $t['state_prompt'],
                'type' => 'text',
                'next' => 'city',
                'phase' => 2
            ],
            'city' => [
                'prompt' => $t['city_prompt'],
                'type' => 'text',
                'next' => 'gender',
                'phase' => 2
            ],
            'gender' => [
                'prompt' => $t['gender_prompt'],
                'type' => 'button',
                'options' => [
                    ['id' => 'male', 'title' => $t['male']],
                    ['id' => 'female', 'title' => $t['female']],
                    ['id' => 'other', 'title' => $t['other']]
                ],
                'next' => 'height',
                'phase' => 1
            ],
            'height' => [
                'prompt' => $t['height_prompt'],
                'type' => 'text',
                'next' => 'current_weight',
                'phase' => 1
            ],
            'current_weight' => [
                'prompt' => $t['weight_prompt'],
                'type' => 'text',
                'next' => 'target_weight',
                'phase' => 1
            ],
            'target_weight' => [
                'prompt' => $t['target_weight_prompt'],
                'type' => 'text',
                'next' => 'body_type',
                'phase' => 1
            ],
            'body_type' => [
                'prompt' => $t['body_type_prompt'],
                'type' => 'list',
                'header' => $t['body_type_header'],
                'body' => $t['body_type_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['ectomorph']],
                    ['id' => '2', 'title' => $t['mesomorph']],
                    ['id' => '3', 'title' => $t['endomorph']],
                    ['id' => '4', 'title' => $t['combination']],
                    ['id' => '5', 'title' => $t['not_sure']]
                ],
                'next' => 'activity_level',
                'phase' => 1
            ],
            'activity_level' => [
                'prompt' => $t['activity_prompt'],
                'type' => 'list',
                'header' => $t['activity_header'],
                'body' => $t['activity_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['sedentary']],
                    ['id' => '2', 'title' => $t['light_active']],
                    ['id' => '3', 'title' => $t['mod_active']],
                    ['id' => '4', 'title' => $t['very_active']],
                    ['id' => '5', 'title' => $t['extreme_active']]
                ],
                'next' => 'medical_history',
                'phase' => 1
            ],
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
                'next' => 'diet_type',
                'phase' => 1
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
                    ['id' => '5', 'title' => $t['jain']],
                    ['id' => '6', 'title' => $t['keto']],
                    ['id' => '7', 'title' => $t['other_diet']]
                ],
                'next' => 'allergies',
                'phase' => 3
            ],
            'allergies' => [
                'prompt' => $t['allergies_prompt'],
                'type' => 'list',
                'multiple' => true,
                'header' => $t['allergies_header'],
                'body' => $t['allergies_body'],
                'options' => [
                    ['id' => '1', 'title' => $t['dairy']],
                    ['id' => '2', 'title' => $t['gluten']],
                    ['id' => '3', 'title' => $t['nuts']],
                    ['id' => '4', 'title' => $t['seafood']],
                    ['id' => '5', 'title' => $t['eggs']],
                    ['id' => '6', 'title' => $t['soy']],
                    ['id' => '7', 'title' => $t['other_allergy']],
                    ['id' => '8', 'title' => $t['none']]
                ],
                'next' => 'primary_goal',
                'phase' => 3
            ],
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
                    ['id' => '5', 'title' => $t['health']],
                    ['id' => '6', 'title' => $t['other_goal']]
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
    }
}