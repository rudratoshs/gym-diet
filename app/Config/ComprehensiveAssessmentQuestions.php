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

        // Enhance health section by adding more options
        $comprehensiveQuestions['health_conditions']['options'] = [
            ['id' => '1', 'title' => $t['diabetes']],
            ['id' => '2', 'title' => $t['hypertension']],
            ['id' => '3', 'title' => $t['heart']],
            ['id' => '4', 'title' => $t['kidney']],
            ['id' => '5', 'title' => $t['liver']],
            ['id' => '6', 'title' => $t['digestive']],
            ['id' => '7', 'title' => $t['thyroid']],
            ['id' => '8', 'title' => $t['none_health']]
        ];

        // Add medication questions after health details
        $comprehensiveQuestions['health_details']['next'] = 'medications';

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
                'default' => 'diet_type',
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
            'next' => 'diet_type',
            'phase' => 2
        ];

        // Add cuisine preferences after diet type
        $comprehensiveQuestions['diet_type']['next'] = 'cuisine_preferences';

        $comprehensiveQuestions['cuisine_preferences'] = [
            'prompt' => $t['cuisine_prompt'],
            'type' => 'list',
            'multiple' => true,
            'header' => $t['cuisine_header'],
            'body' => $t['cuisine_body'],
            'options' => [
                ['id' => '1', 'title' => $t['north_indian']],
                ['id' => '2', 'title' => $t['south_indian']],
                ['id' => '3', 'title' => $t['punjabi']],
                ['id' => '4', 'title' => $t['gujarati']],
                ['id' => '5', 'title' => $t['chinese']],
                ['id' => '6', 'title' => $t['italian']],
                ['id' => '7', 'title' => $t['continental']],
                ['id' => '8', 'title' => $t['no_pref']]
            ],
            'next' => 'meal_timing',
            'phase' => 3
        ];

        $comprehensiveQuestions['meal_timing'] = [
            'prompt' => $t['timing_prompt'],
            'type' => 'list',
            'header' => $t['timing_header'],
            'body' => $t['timing_body'],
            'options' => [
                ['id' => '1', 'title' => $t['traditional']],
                ['id' => '2', 'title' => $t['small_frequent']],
                ['id' => '3', 'title' => $t['intermittent']],
                ['id' => '4', 'title' => $t['omad']],
                ['id' => '5', 'title' => $t['flexible']]
            ],
            'next' => 'food_restrictions',
            'phase' => 3
        ];

        // Add daily schedule after exercise
        $comprehensiveQuestions['exercise_routine']['next'] = 'daily_schedule';

        $comprehensiveQuestions['daily_schedule'] = [
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
        ];

        $comprehensiveQuestions['cooking_capability'] = [
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
            'next' => 'stress_sleep',
            'phase' => 5
        ];

        $comprehensiveQuestions['stress_sleep'] = [
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
        ];

        // Add commitment level after timeline
        $comprehensiveQuestions['timeline']['next'] = 'commitment_level';

        $comprehensiveQuestions['commitment_level'] = [
            'prompt' => $t['commitment_prompt'],
            'type' => 'list',
            'header' => $t['commitment_header'],
            'body' => $t['commitment_body'],
            'options' => [
                ['id' => '1', 'title' => $t['very_committed']],
                ['id' => '2', 'title' => $t['mostly']],
                ['id' => '3', 'title' => $t['moderate']],
                ['id' => '4', 'title' => $t['flexible']],
                ['id' => '5', 'title' => $t['gradual']]
            ],
            'next' => 'additional_requests',
            'phase' => 6
        ];

        $comprehensiveQuestions['additional_requests'] = [
            'prompt' => $t['additional_prompt'],
            'type' => 'text',
            'next' => 'plan_type',
            'phase' => 6
        ];

        // Add meal variety after plan type
        $comprehensiveQuestions['plan_type']['next'] = 'meal_variety';

        $comprehensiveQuestions['meal_variety'] = [
            'prompt' => $t['variety_prompt'],
            'type' => 'list',
            'header' => $t['variety_header'],
            'body' => $t['variety_body'],
            'options' => [
                ['id' => '1', 'title' => $t['high_variety']],
                ['id' => '2', 'title' => $t['moderate_var']],
                ['id' => '3', 'title' => $t['limited_var']],
                ['id' => '4', 'title' => $t['repetitive']]
            ],
            'next' => 'complete',
            'phase' => 7
        ];

        return $comprehensiveQuestions;
    }
}