<?php
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
        // Use Laravel's built-in translation system
        app()->setLocale($lang);  // Set the locale dynamically

        return [
            // PHASE 1: Basic Information
            'age' => [
                'prompt' => __('attributes.age_prompt'),
                'type' => 'text',
                'validation' => 'numeric|min:12|max:120',
                'error_message' => __('attributes.age_error'),
                'next' => 'country',
                'phase' => 1
            ],
            'country' => [
                'prompt' => __('attributes.country_prompt'),
                'type' => 'text',
                'next' => 'state',
                'phase' => 2
            ],
            'state' => [
                'prompt' => __('attributes.state_prompt'),
                'type' => 'text',
                'next' => 'city',
                'phase' => 2
            ],
            'city' => [
                'prompt' => __('attributes.city_prompt'),
                'type' => 'text',
                'next' => 'gender',
                'phase' => 2
            ],
            'gender' => [
                'prompt' => __('attributes.gender_prompt'),
                'type' => 'button',
                'options' => [
                    ['id' => 'male', 'title' => __('attributes.male')],
                    ['id' => 'female', 'title' => __('attributes.female')],
                    ['id' => 'other', 'title' => __('attributes.other')]
                ],
                'next' => 'height',
                'phase' => 1
            ],
            'height' => [
                'prompt' => __('attributes.height_prompt'),
                'type' => 'text',
                'next' => 'current_weight',
                'phase' => 1
            ],
            'current_weight' => [
                'prompt' => __('attributes.weight_prompt'),
                'type' => 'text',
                'next' => 'target_weight',
                'phase' => 1
            ],
            'target_weight' => [
                'prompt' => __('attributes.target_weight_prompt'),
                'type' => 'text',
                'next' => 'body_type',
                'phase' => 1
            ],
            'body_type' => [
                'prompt' => __('attributes.body_type_prompt'),
                'type' => 'list',
                'header' => __('attributes.body_type_header'),
                'body' => __('attributes.body_type_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.ectomorph')],
                    ['id' => '2', 'title' => __('attributes.mesomorph')],
                    ['id' => '3', 'title' => __('attributes.endomorph')],
                    ['id' => '4', 'title' => __('attributes.combination')],
                    ['id' => '5', 'title' => __('attributes.not_sure')]
                ],
                'next' => 'activity_level',
                'phase' => 1
            ],
            'activity_level' => [
                'prompt' => __('attributes.activity_prompt'),
                'type' => 'list',
                'header' => __('attributes.activity_header'),
                'body' => __('attributes.activity_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.sedentary')],
                    ['id' => '2', 'title' => __('attributes.light_active')],
                    ['id' => '3', 'title' => __('attributes.mod_active')],
                    ['id' => '4', 'title' => __('attributes.very_active')],
                    ['id' => '5', 'title' => __('attributes.extreme_active')]
                ],
                'next' => 'diet_type',
                'phase' => 1
            ],
            // 'medical_history' => [
            //     'prompt' => __('attributes.medical_history_prompt'),
            //     'type' => 'list',
            //     'multiple' => true,
            //     'header' => __('attributes.medical_history_header'),
            //     'body' => __('attributes.medical_history_body'),
            //     'options' => [
            //         ['id' => '1', 'title' => __('attributes.heart_disease')],
            //         ['id' => '2', 'title' => __('attributes.high_cholesterol')],
            //         ['id' => '3', 'title' => __('attributes.hypertension')],
            //         ['id' => '4', 'title' => __('attributes.diabetes')],
            //         ['id' => '5', 'title' => __('attributes.cancer')],
            //         ['id' => '6', 'title' => __('attributes.autoimmune')],
            //         ['id' => '7', 'title' => __('attributes.gastrointestinal')],
            //         ['id' => '8', 'title' => __('attributes.mental_health')],
            //         ['id' => '9', 'title' => __('attributes.none_medical')]
            //     ],
            //     'next' => 'diet_type',
            //     'phase' => 1
            // ],
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
                    ['id' => '5', 'title' => __('attributes.jain')],
                    ['id' => '6', 'title' => __('attributes.keto')],
                    ['id' => '7', 'title' => __('attributes.other_diet')]
                ],
                'next' => 'allergies',
                'phase' => 2
            ],
            'allergies' => [
                'prompt' => __('attributes.allergies_prompt'),
                'type' => 'list',
                'multiple' => true,
                'header' => __('attributes.allergies_header'),
                'body' => __('attributes.allergies_body'),
                'options' => [
                    ['id' => '1', 'title' => __('attributes.dairy')],
                    ['id' => '2', 'title' => __('attributes.gluten')],
                    ['id' => '3', 'title' => __('attributes.nuts')],
                    ['id' => '4', 'title' => __('attributes.seafood')],
                    ['id' => '5', 'title' => __('attributes.eggs')],
                    ['id' => '6', 'title' => __('attributes.soy')],
                    ['id' => '7', 'title' => __('attributes.other_allergy')],
                    ['id' => '8', 'title' => __('attributes.none')]
                ],
                'next' => 'primary_goal',
                'phase' => 3
            ],
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
                    ['id' => '5', 'title' => __('attributes.health')],
                    ['id' => '6', 'title' => __('attributes.other_goal')]
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
    }

    /**
     * Convert legacy form fields array to WhatsApp Flow format
     * 
     * @param array $formFields The legacy form fields array
     * @return array The WhatsApp Flow configuration array
     */
    public static function convertToWhatsAppFlowFormat($formFields)
    {
        $result = [
            'meta' => [
                'version' => '7.0',
                'data_api_version' => '3.0',
                'title' => 'Health Assessment',
                'description' => 'Health and wellness assessment flow'
            ],
            'screens' => [],
            'flow' => []
        ];

        $phases = [];
        foreach ($formFields as $fieldId => $field) {
            $phase = $field['phase'] ?? 1;
            $phases[$phase][$fieldId] = $field;
        }
        ksort($phases);

        $screenIds = [];

        foreach ($phases as $phase => $fields) {
            foreach ($fields as $fieldId => $field) {
                $screenId = strtoupper($fieldId . '_SCREEN');
                $screenIds[] = $screenId;
                $screenTitle = isset($field['header']) ? $field['header'] : $field['prompt'];

                $components = [];

                if (isset($field['header'])) {
                    $components[] = ['type' => 'TextHeading', 'text' => $field['header']];
                }
                if (isset($field['body'])) {
                    $components[] = ['type' => 'TextBody', 'text' => $field['body']];
                }

                $components[] = self::createComponentFromField($fieldId, $field);

                $nextScreen = isset($field['next']) ? strtoupper($field['next'] . '_SCREEN') : null;

                $footer = [
                    'type' => 'Footer',
                    'label' => 'Continue',
                    'on-click-action' => [
                        'name' => 'navigate',
                        'next' => ['type' => 'screen', 'name' => $nextScreen],
                        'payload' => [$fieldId => '${form.' . $fieldId . '}']
                    ]
                ];

                $result['screens'][$screenId] = [
                    'id' => $screenId,
                    'title' => $screenTitle,
                    'layout' => [
                        'type' => 'SingleColumnLayout',
                        'children' => array_merge($components, [$footer])
                    ]
                ];
            }
        }

        $confirmationScreenId = 'CONFIRMATION_SCREEN';
        $screenIds[] = $confirmationScreenId;

        $result['screens'][$confirmationScreenId] = [
            'id' => $confirmationScreenId,
            'title' => 'Thank You',
            'layout' => [
                'type' => 'SingleColumnLayout',
                'children' => [
                    ['type' => 'TextHeading', 'text' => 'Thank You!'],
                    ['type' => 'TextBody', 'text' => 'Your information has been collected successfully.']
                ]
            ],
            'footer' => [
                'type' => 'Footer',
                'label' => 'Complete',
                'on-click-action' => ['name' => 'complete']
            ]
        ];

        $result['flow'] = $screenIds;

        return $result;
    }

    /**
     * Create a component from a field
     *
     * @param string $fieldId The field identifier
     * @param array $field The field configuration
     * @return array The component configuration
     */
    private static function createComponentFromField($fieldId, $field)
    {
        $component = [];

        switch ($field['type']) {
            case 'text':
                $component = [
                    'type' => 'TextInput',
                    'name' => $fieldId,
                    'label' => $field['prompt'],
                    'required' => true
                ];
                break;

            case 'button':
                $component = [
                    'type' => 'RadioButtonsGroup',
                    'name' => $fieldId,
                    'label' => $field['prompt'],
                    'required' => true,
                    'data-source' => array_map(fn($option) => ['id' => $option['id'], 'title' => $option['title']], $field['options'] ?? [])
                ];
                break;

            case 'list':
                $component = [
                    'type' => 'NavigationList',
                    'name' => $fieldId,
                    'label' => $field['prompt'],
                    'list-items' => array_map(fn($option) => [
                        'id' => $option['id'],
                        'main-content' => ['title' => $option['title']],
                        'on-click-action' => ['name' => 'navigate', 'next' => ['type' => 'screen', 'name' => 'CONFIRMATION_SCREEN']]
                    ], $field['options'] ?? [])
                ];
                break;
        }

        return $component;
    }
}