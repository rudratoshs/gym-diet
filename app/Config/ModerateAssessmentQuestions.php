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
        $basicQuestions = QuickAssessmentQuestions::getQuestions($lang);

        // Start with basic questions
        $moderateQuestions = $basicQuestions;

        // Modify activity level to point to health conditions
        $moderateQuestions['activity_level']['next'] = 'health_conditions';

        // Add health conditions questions
        $moderateQuestions['health_conditions'] = [
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
                ['id' => '7', 'title' => $t['thyroid']],
                ['id' => '8', 'title' => $t['none_health']]
            ],
            'next_conditional' => [
                'default' => 'diet_type',
                'conditions' => [
                    [
                        'condition' => 'hasHealthCondition',
                        'next' => 'health_details'
                    ]
                ]
            ],
            'phase' => 2
        ];

        $moderateQuestions['health_details'] = [
            'prompt' => $t['health_details_prompt'],
            'type' => 'text',
            'next' => 'diet_type',
            'phase' => 2
        ];

        // Add food restrictions after diet type
        $moderateQuestions['diet_type']['next'] = 'food_restrictions';

        $moderateQuestions['food_restrictions'] = [
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
                ['id' => '6', 'title' => $t['onion_garlic']],
                ['id' => '7', 'title' => $t['processed']],
                ['id' => '8', 'title' => $t['none_r']]
            ],
            'next' => 'allergies',
            'phase' => 3
        ];

        // Add exercise routine after allergies
        $moderateQuestions['allergies']['next'] = 'exercise_routine';

        $moderateQuestions['exercise_routine'] = [
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
            'next' => 'primary_goal',
            'phase' => 5
        ];

        // Add timeline after goal
        $moderateQuestions['primary_goal']['next'] = 'timeline';

        $moderateQuestions['timeline'] = [
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
            'next' => 'plan_type',
            'phase' => 6
        ];

        return $moderateQuestions;
    }
}