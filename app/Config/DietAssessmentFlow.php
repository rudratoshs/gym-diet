<?php
// app/Config/DietAssessmentFlow.php

namespace App\Config;

class DietAssessmentFlow
{
    /**
     * Get the assessment phases configuration
     */
    public static function getPhases()
    {
        return [
            1 => ['name' => 'Basic Information', 'first_question' => 'age'],
            2 => ['name' => 'Health Assessment', 'first_question' => 'health_conditions'],
            3 => ['name' => 'Diet Preferences', 'first_question' => 'diet_type'],
            4 => ['name' => 'Food Details', 'first_question' => 'meal_preferences'],
            5 => ['name' => 'Lifestyle', 'first_question' => 'daily_schedule'],
            6 => ['name' => 'Goals', 'first_question' => 'primary_goal'],
            7 => ['name' => 'Plan Customization', 'first_question' => 'plan_type']
        ];
    }

    /**
     * Get the assessment questions configuration
     */
    public static function getQuestions()
    {
        return [
            // PHASE 1: Basic Information
            'age' => [
                'prompt' => 'Please share your age:',
                'type' => 'text',
                'validation' => 'numeric|min:12|max:120',
                'error_message' => 'Please enter a valid age between 12 and 120',
                'next' => 'gender',
                'phase' => 1
            ],
            'gender' => [
                'prompt' => 'Please select your gender:',
                'type' => 'button',
                'options' => [
                    ['id' => 'male', 'title' => 'Male'],
                    ['id' => 'female', 'title' => 'Female'],
                    ['id' => 'other', 'title' => 'Other']
                ],
                'next' => 'height',
                'phase' => 1
            ],
            'height' => [
                'prompt' => 'Please share your height (in cm or feet-inches, e.g., 175 or 5\'9"):',
                'type' => 'text',
                'next' => 'current_weight',
                'phase' => 1
            ],
            'current_weight' => [
                'prompt' => 'Please share your current weight (in kg or lbs):',
                'type' => 'text',
                'next' => 'target_weight',
                'phase' => 1
            ],
            'target_weight' => [
                'prompt' => 'Please share your target weight (in kg or lbs), or type \'same\' if you want to maintain your current weight:',
                'type' => 'text',
                'next' => 'body_type',
                'phase' => 1
            ],
            'body_type' => [
                'prompt' => 'Which best describes your body type?',
                'type' => 'list',
                'header' => 'Body Type',
                'body' => 'Select the option that best matches your natural physique',
                'options' => [
                    ['id' => '1', 'title' => 'Ectomorph', 'description' => 'Naturally thin, struggles to gain weight'],
                    ['id' => '2', 'title' => 'Mesomorph', 'description' => 'Athletic build, gains/loses weight easily'],
                    ['id' => '3', 'title' => 'Endomorph', 'description' => 'Naturally stocky, gains weight easily'],
                    ['id' => '4', 'title' => 'Combination', 'description' => 'Mix of body types'],
                    ['id' => '5', 'title' => 'Not sure', 'description' => 'Uncertain of body type']
                ],
                'next' => 'activity_level',
                'phase' => 1
            ],
            'activity_level' => [
                'prompt' => 'Which best describes your activity level?',
                'type' => 'list',
                'header' => 'Activity Level',
                'body' => 'Select the option that best matches your typical week',
                'options' => [
                    ['id' => '1', 'title' => 'Sedentary', 'description' => 'Desk job, little exercise'],
                    ['id' => '2', 'title' => 'Lightly active', 'description' => 'Light exercise 1-3 days/week'],
                    ['id' => '3', 'title' => 'Moderately active', 'description' => 'Moderate exercise 3-5 days/week'],
                    ['id' => '4', 'title' => 'Very active', 'description' => 'Hard exercise 6-7 days/week'],
                    ['id' => '5', 'title' => 'Extremely active', 'description' => 'Physical job + intense exercise']
                ],
                'next' => 'medical_history',
                'phase' => 1
            ],
            'medical_history' => [
                'prompt' => 'Have you been diagnosed with any medical conditions in the past?',
                'type' => 'list',
                'multiple' => true,
                'header' => 'Medical History',
                'body' => 'Select all that apply to you',
                'options' => [
                    ['id' => '1', 'title' => 'Heart disease'],
                    ['id' => '2', 'title' => 'High cholesterol'],
                    ['id' => '3', 'title' => 'Hypertension'],
                    ['id' => '4', 'title' => 'Diabetes'],
                    ['id' => '5', 'title' => 'Cancer'],
                    ['id' => '6', 'title' => 'Autoimmune issue'],
                    ['id' => '7', 'title' => 'Gastrointestinal'],
                    ['id' => '8', 'title' => 'Mental health issue'],
                    ['id' => '9', 'title' => 'None of the above']
                ],
                'next' => 'health_conditions',
                'phase' => 2
            ],

            // PHASE 2: Health Assessment
            'health_conditions' => [
                'prompt' => 'Do you currently have any of these health conditions? (Select all that apply)',
                'type' => 'list',
                'multiple' => true,
                'header' => 'Current Health Conditions',
                'body' => 'Select all that apply to you',
                'options' => [
                    ['id' => '1', 'title' => 'Diabetes'],
                    ['id' => '2', 'title' => 'Hypertension'],
                    ['id' => '3', 'title' => 'Heart disease'],
                    ['id' => '4', 'title' => 'Kidney issues'],
                    ['id' => '5', 'title' => 'Liver problems'],
                    ['id' => '6', 'title' => 'Digestive disorders'],
                    ['id' => '7', 'title' => 'GERD/Acid reflux'],
                    ['id' => '8', 'title' => 'IBS/IBD'],
                    ['id' => '9', 'title' => 'Hormonal imbalances'],
                    ['id' => '10', 'title' => 'Thyroid issues'],
                    ['id' => '11', 'title' => 'PCOS'],
                    ['id' => '12', 'title' => 'Respiratory issues'],
                    ['id' => '13', 'title' => 'Joint pain/Arthritis'],
                    ['id' => '14', 'title' => 'Skin conditions'],
                    ['id' => '15', 'title' => 'None of the above'],
                    ['id' => '16', 'title' => 'Other']
                ],
                'next_conditional' => [
                    'default' => 'medications',  // If "None of the above" is selected, go to medications
                    'conditions' => [
                        [
                            'condition' => '!in_array("16", $responses)', // If user did NOT select "None of the above"
                            'next' => 'health_details'
                        ]
                    ]
                ],
                'phase' => 2
            ],
            'health_details' => [
                'prompt' => 'Could you provide more details about your health conditions:\n- Duration\n- Medications\n- Severity level (mild/moderate/severe)',
                'type' => 'text',
                'next' => 'medications',
                'phase' => 2
            ],
            'medications' => [
                'prompt' => 'Are you currently taking any medications or supplements?',
                'type' => 'list',
                'header' => 'Medications',
                'body' => 'Select an option',
                'options' => [
                    ['id' => '1', 'title' => 'Yes, prescription medications'],
                    ['id' => '2', 'title' => 'Yes, over-the-counter medications'],
                    ['id' => '3', 'title' => 'Yes, supplements only'],
                    ['id' => '4', 'title' => 'Yes, combination of the above'],
                    ['id' => '5', 'title' => 'No, none currently']
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
            ],
            'medication_details' => [
                'prompt' => 'Please list the medications/supplements you take regularly:',
                'type' => 'text',
                'next' => 'allergies',
                'phase' => 2
            ],
            'allergies' => [
                'prompt' => 'Do you have any food allergies or intolerances?',
                'type' => 'list',
                'multiple' => true,
                'header' => 'Food Allergies',
                'body' => 'Select all that apply to you',
                'options' => [
                    ['id' => '1', 'title' => 'Dairy'],
                    ['id' => '2', 'title' => 'Gluten/Wheat'],
                    ['id' => '3', 'title' => 'Tree nuts'],
                    ['id' => '4', 'title' => 'Peanuts'],
                    ['id' => '5', 'title' => 'Seafood/Shellfish'],
                    ['id' => '6', 'title' => 'Eggs'],
                    ['id' => '7', 'title' => 'Soy'],
                    ['id' => '8', 'title' => 'Corn'],
                    ['id' => '9', 'title' => 'Fruits'],
                    ['id' => '10', 'title' => 'Nightshades'],
                    ['id' => '11', 'title' => 'Sulfites'],
                    ['id' => '12', 'title' => 'FODMAPs'],
                    ['id' => '13', 'title' => 'Other'],
                    ['id' => '14', 'title' => 'None']
                ],
                'next_conditional' => [
                    'default' => 'recovery_needs',
                    'conditions' => [
                        [
                            'condition' => 'hasOtherAllergies',
                            'next' => 'allergy_details'
                        ]
                    ]
                ],
                'phase' => 2
            ],
            'allergy_details' => [
                'prompt' => 'Please provide details about your specific food allergies or intolerances:',
                'type' => 'text',
                'next' => 'recovery_needs',
                'phase' => 2
            ],
            'recovery_needs' => [
                'prompt' => 'Are you looking to address any specific health concerns?',
                'type' => 'list',
                'multiple' => true,
                'header' => 'Health Goals',
                'body' => 'Select all that apply to you',
                'options' => [
                    ['id' => '1', 'title' => 'Weight loss'],
                    ['id' => '2', 'title' => 'Muscle gain'],
                    ['id' => '3', 'title' => 'Digestive health'],
                    ['id' => '4', 'title' => 'Energy improvement'],
                    ['id' => '5', 'title' => 'Blood sugar management'],
                    ['id' => '6', 'title' => 'Cholesterol management'],
                    ['id' => '7', 'title' => 'Inflammation reduction'],
                    ['id' => '8', 'title' => 'Detoxification'],
                    ['id' => '9', 'title' => 'Immune support'],
                    ['id' => '10', 'title' => 'Sleep improvement'],
                    ['id' => '11', 'title' => 'Stress management'],
                    ['id' => '12', 'title' => 'Hair/skin health'],
                    ['id' => '13', 'title' => 'Hormone balance'],
                    ['id' => '14', 'title' => 'Organ recovery'],
                    ['id' => '15', 'title' => 'Post-surgery nutrition'],
                    ['id' => '16', 'title' => 'None specifically']
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
            ],
            'organ_recovery' => [
                'prompt' => 'Which organs are you focusing on for recovery?',
                'type' => 'list',
                'multiple' => true,
                'header' => 'Organ Recovery',
                'body' => 'Select all that apply',
                'options' => [
                    ['id' => '1', 'title' => 'Liver'],
                    ['id' => '2', 'title' => 'Kidneys'],
                    ['id' => '3', 'title' => 'Heart'],
                    ['id' => '4', 'title' => 'Lungs'],
                    ['id' => '5', 'title' => 'Digestive system'],
                    ['id' => '6', 'title' => 'Pancreas'],
                    ['id' => '7', 'title' => 'Other']
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
            ],
            'organ_recovery_details' => [
                'prompt' => 'Please provide more details about the organs you\'re focusing on:',
                'type' => 'text',
                'next' => 'diet_type',
                'phase' => 2
            ],
            'surgery_details' => [
                'prompt' => 'Please share details about your surgery and when it occurred:',
                'type' => 'text',
                'next' => 'diet_type',
                'phase' => 2
            ],

            // PHASE 3: Diet Preferences
            'diet_type' => [
                'prompt' => 'What type of diet do you follow?',
                'type' => 'list',
                'header' => 'Diet Type',
                'body' => 'Select your preferred eating style',
                'options' => [
                    ['id' => '1', 'title' => 'Omnivore', 'description' => 'Eats everything'],
                    ['id' => '2', 'title' => 'Vegetarian', 'description' => 'No meat'],
                    ['id' => '3', 'title' => 'Eggetarian', 'description' => 'Vegetarian + eggs'],
                    ['id' => '4', 'title' => 'Vegan', 'description' => 'No animal products'],
                    ['id' => '5', 'title' => 'Pescatarian', 'description' => 'Vegetarian + seafood'],
                    ['id' => '6', 'title' => 'Flexitarian', 'description' => 'Mostly plant-based'],
                    ['id' => '7', 'title' => 'Keto', 'description' => 'Low carb, high fat'],
                    ['id' => '8', 'title' => 'Paleo', 'description' => 'Whole foods, no grains/dairy'],
                    ['id' => '9', 'title' => 'Jain', 'description' => 'No root vegetables, honey, etc.'],
                    ['id' => '10', 'title' => 'Mediterranean', 'description' => 'Olive oil, fish, vegetables'],
                    ['id' => '11', 'title' => 'DASH', 'description' => 'Heart-healthy, low sodium'],
                    ['id' => '12', 'title' => 'FODMAP', 'description' => 'IBS-friendly'],
                    ['id' => '13', 'title' => 'Raw food', 'description' => 'Mostly uncooked foods'],
                    ['id' => '14', 'title' => 'Other', 'description' => 'Custom diet']
                ],
                'next_conditional' => [
                    'default' => 'religion_diet',
                    'conditions' => [
                        [
                            'condition' => '14',
                            'next' => 'diet_type_other'
                        ],
                        [
                            'condition' => '9',
                            'next' => 'jain_preferences'
                        ]
                    ]
                ],
                'phase' => 3
            ],
            'diet_type_other' => [
                'prompt' => 'Please describe your diet type:',
                'type' => 'text',
                'next' => 'religion_diet',
                'phase' => 3
            ],
            'jain_preferences' => [
                'prompt' => 'What specific Jain dietary restrictions do you follow?',
                'type' => 'list',
                'multiple' => true,
                'header' => 'Jain Preferences',
                'body' => 'Select all that apply',
                'options' => [
                    ['id' => '1', 'title' => 'No root vegetables'],
                    ['id' => '2', 'title' => 'No fermented foods'],
                    ['id' => '3', 'title' => 'No honey'],
                    ['id' => '4', 'title' => 'Strictly vegetarian'],
                    ['id' => '5', 'title' => 'No leafy greens at night'],
                    ['id' => '6', 'title' => 'No eggs/dairy'],
                    ['id' => '7', 'title' => 'No onion/garlic'],
                    ['id' => '8', 'title' => 'Water only after sunrise'],
                    ['id' => '9', 'title' => 'Eat before sunset']
                ],
                'next' => 'religion_diet',
                'phase' => 3
            ],
            'religion_diet' => [
                'prompt' => 'Do you follow any religious dietary practices?',
                'type' => 'list',
                'header' => 'Religious Diet',
                'body' => 'Select if applicable',
                'options' => [
                    ['id' => '1', 'title' => 'Kosher (Jewish)'],
                    ['id' => '2', 'title' => 'Halal (Islamic)'],
                    ['id' => '3', 'title' => 'Hindu vegetarian'],
                    ['id' => '4', 'title' => 'Buddhist vegetarian'],
                    ['id' => '5', 'title' => 'Jain vegetarian'],
                    ['id' => '6', 'title' => 'Fasting periods'],
                    ['id' => '7', 'title' => 'Other religious practice'],
                    ['id' => '8', 'title' => 'None']
                ],
                'next_conditional' => [
                    'default' => 'cuisine_preferences',
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
            ],
            'religion_diet_details' => [
                'prompt' => 'Please describe your religious dietary practices:',
                'type' => 'text',
                'next' => 'cuisine_preferences',
                'phase' => 3
            ],
            'fasting_details' => [
                'prompt' => 'Please describe your fasting practices (frequency, allowed foods, etc.):',
                'type' => 'text',
                'next' => 'cuisine_preferences',
                'phase' => 3
            ],
            'cuisine_preferences' => [
                'prompt' => 'Which cuisines do you prefer? (Select up to 3)',
                'type' => 'list',
                'multiple' => true,
                'header' => 'Cuisine Preferences',
                'body' => 'Select up to 3 cuisines you enjoy most',
                'options' => [
                    ['id' => '1', 'title' => 'North Indian'],
                    ['id' => '2', 'title' => 'South Indian'],
                    ['id' => '3', 'title' => 'East Indian'],
                    ['id' => '4', 'title' => 'West Indian'],
                    ['id' => '5', 'title' => 'Punjabi'],
                    ['id' => '6', 'title' => 'Gujarati'],
                    ['id' => '7', 'title' => 'Bengali'],
                    ['id' => '8', 'title' => 'Mediterranean'],
                    ['id' => '9', 'title' => 'Chinese'],
                    ['id' => '10', 'title' => 'Japanese'],
                    ['id' => '11', 'title' => 'Korean'],
                    ['id' => '12', 'title' => 'Thai'],
                    ['id' => '13', 'title' => 'Vietnamese'],
                    ['id' => '14', 'title' => 'Middle Eastern'],
                    ['id' => '15', 'title' => 'Mexican'],
                    ['id' => '16', 'title' => 'Italian'],
                    ['id' => '17', 'title' => 'Continental'],
                    ['id' => '18', 'title' => 'No specific preference']
                ],
                'next' => 'spice_preference',
                'phase' => 3
            ],
            'spice_preference' => [
                'prompt' => 'What is your spice preference?',
                'type' => 'list',
                'header' => 'Spice Level',
                'body' => 'Select your preferred spice level',
                'options' => [
                    ['id' => '1', 'title' => 'Mild (no spice)'],
                    ['id' => '2', 'title' => 'Low spice'],
                    ['id' => '3', 'title' => 'Medium spice'],
                    ['id' => '4', 'title' => 'Spicy'],
                    ['id' => '5', 'title' => 'Very spicy']
                ],
                'next' => 'meal_timing',
                'phase' => 3
            ],
            'meal_timing' => [
                'prompt' => 'What\'s your preferred meal schedule?',
                'type' => 'list',
                'header' => 'Meal Timing',
                'body' => 'Select your preferred meal pattern',
                'options' => [
                    ['id' => '1', 'title' => 'Traditional (3 meals)'],
                    ['id' => '2', 'title' => 'Small frequent meals (5-6)'],
                    ['id' => '3', 'title' => 'Intermittent fasting 16:8'],
                    ['id' => '4', 'title' => 'Intermittent fasting 18:6'],
                    ['id' => '5', 'title' => 'OMAD (one meal a day)'],
                    ['id' => '6', 'title' => 'Flexible/No specific pattern']
                ],
                'next' => 'meal_preferences',
                'phase' => 4
            ],

            // PHASE 4: Food Details
            'meal_preferences' => [
                'prompt' => 'What are your meal preferences?',
                'type' => 'list',
                'multiple' => true,
                'header' => 'Meal Preferences',
                'body' => 'Select all that apply',
                'options' => [
                    ['id' => '1', 'title' => 'High protein'],
                    ['id' => '2', 'title' => 'Low carb'],
                    ['id' => '3', 'title' => 'Low fat'],
                    ['id' => '4', 'title' => 'Gluten-free'],
                    ['id' => '5', 'title' => 'Dairy-free'],
                    ['id' => '6', 'title' => 'Sugar-free'],
                    ['id' => '7', 'title' => 'Low sodium'],
                    ['id' => '8', 'title' => 'Whole foods focus'],
                    ['id' => '9', 'title' => 'Plant-based focus'],
                    ['id' => '10', 'title' => 'Local/Seasonal focus'],
                    ['id' => '11', 'title' => 'Balanced macros'],
                    ['id' => '12', 'title' => 'Traditional home cooking'],
                    ['id' => '13', 'title' => 'No specific preferences']
                ],
                'next' => 'food_restrictions',
                'phase' => 4
            ],
            'food_restrictions' => [
                'prompt' => 'Are there specific foods you avoid?',
                'type' => 'list',
                'multiple' => true,
                'header' => 'Food Restrictions',
                'body' => 'Select all that you avoid',
                'options' => [
                    ['id' => '1', 'title' => 'Red meat'],
                    ['id' => '2', 'title' => 'Poultry'],
                    ['id' => '3', 'title' => 'Seafood'],
                    ['id' => '4', 'title' => 'Eggs'],
                    ['id' => '5', 'title' => 'Dairy'],
                    ['id' => '6', 'title' => 'Wheat/Gluten'],
                    ['id' => '7', 'title' => 'Corn'],
                    ['id' => '8', 'title' => 'Soy'],
                    ['id' => '9', 'title' => 'Nightshades'],
                    ['id' => '10', 'title' => 'Onions/Garlic'],
                    ['id' => '11', 'title' => 'Root vegetables'],
                    ['id' => '12', 'title' => 'Nuts'],
                    ['id' => '13', 'title' => 'Processed foods'],
                    ['id' => '14', 'title' => 'Added sugar'],
                    ['id' => '15', 'title' => 'Other'],
                    ['id' => '16', 'title' => 'None']
                ],
                'next_conditional' => [
                    'default' => 'favorite_foods',
                    'conditions' => [
                        [
                            'condition' => '15',
                            'next' => 'food_restrictions_other'
                        ]
                    ]
                ],
                'phase' => 4
            ],
            'food_restrictions_other' => [
                'prompt' => 'Please specify other foods you avoid:',
                'type' => 'text',
                'next' => 'favorite_foods',
                'phase' => 4
            ],
            'favorite_foods' => [
                'prompt' => 'Please share some of your favorite foods or dishes:',
                'type' => 'text',
                'next' => 'disliked_foods',
                'phase' => 4
            ],
            'disliked_foods' => [
                'prompt' => 'Are there any foods you strongly dislike?',
                'type' => 'text',
                'next' => 'nutrition_knowledge',
                'phase' => 4
            ],
            'nutrition_knowledge' => [
                'prompt' => 'How would you rate your nutrition knowledge?',
                'type' => 'list',
                'header' => 'Nutrition Knowledge',
                'body' => 'Select your level',
                'options' => [
                    ['id' => '1', 'title' => 'Beginner', 'description' => 'Little knowledge'],
                    ['id' => '2', 'title' => 'Basic', 'description' => 'Understand fundamentals'],
                    ['id' => '3', 'title' => 'Intermediate', 'description' => 'Good understanding'],
                    ['id' => '4', 'title' => 'Advanced', 'description' => 'Detailed knowledge'],
                    ['id' => '5', 'title' => 'Expert', 'description' => 'Professional level']
                ],
                'next' => 'daily_schedule',
                'phase' => 5
            ],

            // PHASE 5: Lifestyle
            'daily_schedule' => [
                'prompt' => 'What\'s your typical daily schedule?',
                'type' => 'list',
                'header' => 'Daily Schedule',
                'body' => 'Select when you typically start your day',
                'options' => [
                    ['id' => '1', 'title' => 'Early riser (5-7am)'],
                    ['id' => '2', 'title' => 'Standard hours (7-9am)'],
                    ['id' => '3', 'title' => 'Late riser (after 9am)'],
                    ['id' => '4', 'title' => 'Night shift worker'],
                    ['id' => '5', 'title' => 'Irregular schedule']
                ],
                'next' => 'work_type',
                'phase' => 5
            ],
            'work_type' => [
                'prompt' => 'What type of work do you do?',
                'type' => 'list',
                'header' => 'Work Type',
                'body' => 'Select the best match',
                'options' => [
                    ['id' => '1', 'title' => 'Desk job'],
                    ['id' => '2', 'title' => 'Moderate physical work'],
                    ['id' => '3', 'title' => 'Physically demanding job'],
                    ['id' => '4', 'title' => 'Student'],
                    ['id' => '5', 'title' => 'Stay at home parent'],
                    ['id' => '6', 'title' => 'Retired'],
                    ['id' => '7', 'title' => 'Unemployed/Other']
                ],
                'next' => 'cooking_capability',
                'phase' => 5
            ],
            'cooking_capability' => [
                'prompt' => 'What\'s your cooking situation?',
                'type' => 'list',
                'header' => 'Cooking Situation',
                'body' => 'Select the option that best describes you',
                'options' => [
                    ['id' => '1', 'title' => 'Full kitchen, enjoy cooking'],
                    ['id' => '2', 'title' => 'Basic kitchen, limited time'],
                    ['id' => '3', 'title' => 'Minimal cooking, simple meals'],
                    ['id' => '4', 'title' => 'Rely on prepared foods'],
                    ['id' => '5', 'title' => 'Have cooking help']
                ],
                'next' => 'cooking_time',
                'phase' => 5
            ],
            'cooking_time' => [
                'prompt' => 'How much time can you dedicate to meal preparation daily?',
                'type' => 'list',
                'header' => 'Cooking Time',
                'body' => 'Select your available time',
                'options' => [
                    ['id' => '1', 'title' => 'Minimal (0-15 minutes)'],
                    ['id' => '2', 'title' => 'Brief (15-30 minutes)'],
                    ['id' => '3', 'title' => 'Moderate (30-60 minutes)'],
                    ['id' => '4', 'title' => 'Extended (60+ minutes)'],
                    ['id' => '5', 'title' => 'Batch cooking on weekends']
                ],
                'next' => 'grocery_access',
                'phase' => 5
            ],
            'grocery_access' => [
                'prompt' => 'How would you describe your access to groceries?',
                'type' => 'list',
                'header' => 'Grocery Access',
                'body' => 'Select your situation',
                'options' => [
                    ['id' => '1', 'title' => 'Excellent variety nearby'],
                    ['id' => '2', 'title' => 'Good access to basics'],
                    ['id' => '3', 'title' => 'Limited options nearby'],
                    ['id' => '4', 'title' => 'Rely on delivery services'],
                    ['id' => '5', 'title' => 'Challenging to get groceries']
                ],
                'next' => 'budget_constraints',
                'phase' => 5
            ],
            'budget_constraints' => [
                'prompt' => 'Do you have any budget constraints for your meal plan?',
                'type' => 'list',
                'header' => 'Budget',
                'body' => 'Select your situation',
                'options' => [
                    ['id' => '1', 'title' => 'Very budget conscious'],
                    ['id' => '2', 'title' => 'Moderately budget conscious'],
                    ['id' => '3', 'title' => 'Flexible budget'],
                    ['id' => '4', 'title' => 'No significant constraints']
                ],
                'next' => 'exercise_routine',
                'phase' => 5
            ],
            'exercise_routine' => [
                'prompt' => 'What\'s your current exercise pattern?',
                'type' => 'list',
                'header' => 'Exercise Pattern',
                'body' => 'Select your typical approach',
                'options' => [
                    ['id' => '1', 'title' => 'Strength training focused'],
                    ['id' => '2', 'title' => 'Cardio focused'],
                    ['id' => '3', 'title' => 'Mixed strength & cardio'],
                    ['id' => '4', 'title' => 'Yoga/low-impact'],
                    ['id' => '5', 'title' => 'Sport-specific training'],
                    ['id' => '6', 'title' => 'Minimal/no regular exercise']
                ],
                'next' => 'exercise_frequency',
                'phase' => 5
            ],
            'exercise_frequency' => [
                'prompt' => 'How often do you exercise?',
                'type' => 'list',
                'header' => 'Exercise Frequency',
                'body' => 'Select your typical schedule',
                'options' => [
                    ['id' => '1', 'title' => 'Daily'],
                    ['id' => '2', 'title' => '4-6 times per week'],
                    ['id' => '3', 'title' => '2-3 times per week'],
                    ['id' => '4', 'title' => 'Once per week'],
                    ['id' => '5', 'title' => 'Rarely/never']
                ],
                'next' => 'exercise_timing',
                'phase' => 5
            ],
            'exercise_timing' => [
                'prompt' => 'When do you typically exercise?',
                'type' => 'list',
                'header' => 'Exercise Timing',
                'body' => 'Select your usual time',
                'options' => [
                    ['id' => '1', 'title' => 'Early morning (5-8am)'],
                    ['id' => '2', 'title' => 'Morning (8-11am)'],
                    ['id' => '3', 'title' => 'Midday (11am-2pm)'],
                    ['id' => '4', 'title' => 'Afternoon (2-5pm)'],
                    ['id' => '5', 'title' => 'Evening (5-8pm)'],
                    ['id' => '6', 'title' => 'Night (8-11pm)'],
                    ['id' => '7', 'title' => 'Varies/inconsistent']
                ],
                'next' => 'stress_sleep',
                'phase' => 5
            ],
            'stress_sleep' => [
                'prompt' => 'How would you rate your stress & sleep?',
                'type' => 'list',
                'header' => 'Stress & Sleep',
                'body' => 'Select the option that best describes you',
                'options' => [
                    ['id' => '1', 'title' => 'Low stress, good sleep'],
                    ['id' => '2', 'title' => 'Moderate stress, adequate sleep'],
                    ['id' => '3', 'title' => 'High stress, sufficient sleep'],
                    ['id' => '4', 'title' => 'Low stress, poor sleep'],
                    ['id' => '5', 'title' => 'High stress, poor sleep']
                ],
                'next' => 'sleep_hours',
                'phase' => 5
            ],
            'sleep_hours' => [
                'prompt' => 'How many hours do you typically sleep per night?',
                'type' => 'list',
                'header' => 'Sleep Duration',
                'body' => 'Select your average',
                'options' => [
                    ['id' => '1', 'title' => 'Less than 5 hours'],
                    ['id' => '2', 'title' => '5-6 hours'],
                    ['id' => '3', 'title' => '6-7 hours'],
                    ['id' => '4', 'title' => '7-8 hours'],
                    ['id' => '5', 'title' => '8-9 hours'],
                    ['id' => '6', 'title' => 'More than 9 hours'],
                    ['id' => '7', 'title' => 'Highly variable']
                ],
                'next' => 'water_intake',
                'phase' => 5
            ],
            'water_intake' => [
                'prompt' => 'How much water do you typically drink daily?',
                'type' => 'list',
                'header' => 'Water Intake',
                'body' => 'Select your typical consumption',
                'options' => [
                    ['id' => '1', 'title' => 'Less than 1 liter'],
                    ['id' => '2', 'title' => '1-2 liters'],
                    ['id' => '3', 'title' => '2-3 liters'],
                    ['id' => '4', 'title' => 'More than 3 liters'],
                    ['id' => '5', 'title' => 'Don\'t track water intake']
                ],
                'next' => 'primary_goal',
                'phase' => 6
            ],

            // PHASE 6: Goal Setting
            'primary_goal' => [
                'prompt' => 'What\'s your primary health goal?',
                'type' => 'list',
                'header' => 'Primary Goal',
                'body' => 'Select your most important health objective',
                'options' => [
                    ['id' => '1', 'title' => 'Weight loss'],
                    ['id' => '2', 'title' => 'Muscle gain'],
                    ['id' => '3', 'title' => 'Maintain current weight'],
                    ['id' => '4', 'title' => 'Better energy levels'],
                    ['id' => '5', 'title' => 'Improved digestion'],
                    ['id' => '6', 'title' => 'Improved overall health'],
                    ['id' => '7', 'title' => 'Recovery from condition'],
                    ['id' => '8', 'title' => 'Athletic performance'],
                    ['id' => '9', 'title' => 'Longevity & prevention'],
                    ['id' => '10', 'title' => 'Hormone balance'],
                    ['id' => '11', 'title' => 'Mental clarity']
                ],
                'next' => 'weight_goal',
                'phase' => 6
            ],
            'weight_goal' => [
                'prompt' => 'What is your desired rate of weight change?',
                'type' => 'list',
                'header' => 'Weight Goal',
                'body' => 'Select your preferred pace',
                'options' => [
                    ['id' => '1', 'title' => 'Rapid weight loss'],
                    ['id' => '2', 'title' => 'Moderate weight loss'],
                    ['id' => '3', 'title' => 'Slow, steady weight loss'],
                    ['id' => '4', 'title' => 'Maintain current weight'],
                    ['id' => '5', 'title' => 'Slight weight gain'],
                    ['id' => '6', 'title' => 'Moderate weight gain'],
                    ['id' => '7', 'title' => 'Significant weight gain']
                ],
                'next' => 'timeline',
                'phase' => 6
            ],
            'timeline' => [
                'prompt' => 'What\'s your timeline for results?',
                'type' => 'list',
                'header' => 'Timeline',
                'body' => 'Select your expected timeframe',
                'options' => [
                    ['id' => '1', 'title' => 'Short-term (1-4 weeks)'],
                    ['id' => '2', 'title' => 'Medium-term (1-3 months)'],
                    ['id' => '3', 'title' => 'Long-term (3+ months)'],
                    ['id' => '4', 'title' => 'Lifestyle change (ongoing)']
                ],
                'next' => 'measurement_preference',
                'phase' => 6
            ],
            'measurement_preference' => [
                'prompt' => 'How would you like to track progress?',
                'type' => 'list',
                'header' => 'Tracking Method',
                'body' => 'Select your preferred way to measure results',
                'options' => [
                    ['id' => '1', 'title' => 'Weight on scale'],
                    ['id' => '2', 'title' => 'Body measurements'],
                    ['id' => '3', 'title' => 'Body fat percentage'],
                    ['id' => '4', 'title' => 'Energy levels'],
                    ['id' => '5', 'title' => 'Mood and mental clarity'],
                    ['id' => '6', 'title' => 'Performance metrics'],
                    ['id' => '7', 'title' => 'Medical indicators'],
                    ['id' => '8', 'title' => 'Clothing fit'],
                    ['id' => '9', 'title' => 'Progress photos'],
                    ['id' => '10', 'title' => 'Combination approach']
                ],
                'next' => 'motivation',
                'phase' => 6
            ],
            'motivation' => [
                'prompt' => 'What\'s your primary motivation for making dietary changes?',
                'type' => 'list',
                'header' => 'Motivation',
                'body' => 'Select your main driver',
                'options' => [
                    ['id' => '1', 'title' => 'Better health/longevity'],
                    ['id' => '2', 'title' => 'Appearance/body composition'],
                    ['id' => '3', 'title' => 'Performance improvement'],
                    ['id' => '4', 'title' => 'Energy and vitality'],
                    ['id' => '5', 'title' => 'Medical necessity'],
                    ['id' => '6', 'title' => 'Family/social support'],
                    ['id' => '7', 'title' => 'Specific event/occasion']
                ],
                'next' => 'commitment_level',
                'phase' => 6
            ],
            'commitment_level' => [
                'prompt' => 'How would you rate your commitment to following a structured plan?',
                'type' => 'list',
                'header' => 'Commitment Level',
                'body' => 'Be honest about your commitment',
                'options' => [
                    ['id' => '1', 'title' => 'Very committed (strict)'],
                    ['id' => '2', 'title' => 'Mostly committed (90/10)'],
                    ['id' => '3', 'title' => 'Moderately (80/20)'],
                    ['id' => '4', 'title' => 'Flexible commitment'],
                    ['id' => '5', 'title' => 'Gradual implementation']
                ],
                'next' => 'past_attempts',
                'phase' => 6
            ],
            'past_attempts' => [
                'prompt' => 'Have you tried other diet plans before?',
                'type' => 'list',
                'header' => 'Past Attempts',
                'body' => 'Select your experience',
                'options' => [
                    ['id' => '1', 'title' => 'Many attempts, little success'],
                    ['id' => '2', 'title' => 'Some attempts, mixed results'],
                    ['id' => '3', 'title' => 'Few attempts, limited success'],
                    ['id' => '4', 'title' => 'Past success, but regained'],
                    ['id' => '5', 'title' => 'First serious attempt']
                ],
                'next' => 'plan_type',
                'phase' => 7
            ],

            // PHASE 7: Plan Customization
            'plan_type' => [
                'prompt' => 'Based on your inputs, I\'ll now create your personalized plan. Would you like:',
                'type' => 'button',
                'options' => [
                    ['id' => 'complete', 'title' => 'Complete Plan'],
                    ['id' => 'basic', 'title' => 'Phased Approach'],
                    ['id' => 'focus', 'title' => 'Focus on Diet']
                ],
                'next' => 'detail_level',
                'phase' => 7
            ],
            'detail_level' => [
                'prompt' => 'How detailed would you like your meal plans to be?',
                'type' => 'list',
                'header' => 'Detail Level',
                'body' => 'Select your preference',
                'options' => [
                    ['id' => '1', 'title' => 'Very detailed (exact amounts)'],
                    ['id' => '2', 'title' => 'Moderately detailed'],
                    ['id' => '3', 'title' => 'General guidelines'],
                    ['id' => '4', 'title' => 'Simple & flexible']
                ],
                'next' => 'recipe_complexity',
                'phase' => 7
            ],
            'recipe_complexity' => [
                'prompt' => 'What level of recipe complexity do you prefer?',
                'type' => 'list',
                'header' => 'Recipe Complexity',
                'body' => 'Select your preference',
                'options' => [
                    ['id' => '1', 'title' => 'Very simple (few ingredients)'],
                    ['id' => '2', 'title' => 'Moderately simple'],
                    ['id' => '3', 'title' => 'Balanced complexity'],
                    ['id' => '4', 'title' => 'Complex (gourmet)']
                ],
                'next' => 'meal_variety',
                'phase' => 7
            ],
            'meal_variety' => [
                'prompt' => 'How much meal variety would you like?',
                'type' => 'list',
                'header' => 'Meal Variety',
                'body' => 'Select your preference',
                'options' => [
                    ['id' => '1', 'title' => 'High variety (different daily)'],
                    ['id' => '2', 'title' => 'Moderate variety'],
                    ['id' => '3', 'title' => 'Limited variety (simple)'],
                    ['id' => '4', 'title' => 'Same meals repeated']
                ],
                'next' => 'additional_requests',
                'phase' => 7
            ],
            'additional_requests' => [
                'prompt' => 'Do you have any additional requests or information you\'d like to share?',
                'type' => 'text',
                'next' => 'complete',
                'phase' => 7
            ],
            'complete' => [
                'prompt' => 'Thank you for completing the assessment! I\'m now generating your personalized diet plan. This may take a minute...',
                'type' => 'text',
                'is_final' => true,
                'phase' => 7
            ]
        ];
    }

    /**
     * Get conditional checks for assessment flows
     */
    public static function getConditionalChecks()
    {
        return [
            // Health condition checks
            'hasHealthCondition' => function ($response) {
                if (strpos($response, ',') !== false) {
                    $options = explode(',', $response);
                    return !in_array('15', array_map('trim', $options)) &&
                        !in_array('None of the above', array_map('trim', $options));
                }
                return $response != '15' && $response != 'None of the above';
            },

            // Organ recovery check
            'hasOrganRecovery' => function ($response) {
                if (strpos($response, ',') !== false) {
                    $options = explode(',', $response);
                    return in_array('14', array_map('trim', $options)) ||
                        in_array('Organ recovery', array_map('trim', $options));
                }
                return $response == '14' || $response == 'Organ recovery';
            },

            // Post-surgery check
            'hasPostSurgery' => function ($response) {
                if (strpos($response, ',') !== false) {
                    $options = explode(',', $response);
                    return in_array('15', array_map('trim', $options)) ||
                        in_array('Post-surgery nutrition', array_map('trim', $options));
                }
                return $response == '15' || $response == 'Post-surgery nutrition';
            },

            // Other allergies check
            'hasOtherAllergies' => function ($response) {
                if (strpos($response, ',') !== false) {
                    $options = explode(',', $response);
                    return in_array('13', array_map('trim', $options)) ||
                        in_array('Other', array_map('trim', $options));
                }
                return $response == '13' || $response == 'Other';
            }
        ];
    }
}