<?php
// app/Services/WhatsAppService.php
namespace App\Services;

use App\Models\AssessmentSession;
use App\Models\DietPlan;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use App\Models\WhatsappConversation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppService
{
    protected $apiKey;
    protected $apiUrl;
    protected $phoneNumberId;

    // Define the assessment flow structure
    protected $assessmentFlow = [
        'phases' => [
            1 => ['name' => 'Basic Information', 'first_question' => 'age'],
            2 => ['name' => 'Health Assessment', 'first_question' => 'health_conditions'],
            3 => ['name' => 'Diet Preferences', 'first_question' => 'diet_type'],
            4 => ['name' => 'Lifestyle', 'first_question' => 'daily_schedule'],
            5 => ['name' => 'Goals', 'first_question' => 'primary_goal'],
            6 => ['name' => 'Plan Customization', 'first_question' => 'plan_type']
        ],
        'questions' => [
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
                'next' => 'health_conditions',
                'phase' => 1
            ],

            // PHASE 2: Health Assessment
            'health_conditions' => [
                'prompt' => 'Do you have any of these health conditions? (Select all that apply)',
                'type' => 'list',
                'multiple' => true,
                'header' => 'Health Conditions',
                'body' => 'Select all that apply to you',
                'options' => [
                    ['id' => '1', 'title' => 'Diabetes'],
                    ['id' => '2', 'title' => 'Hypertension'],
                    ['id' => '3', 'title' => 'Heart disease'],
                    ['id' => '4', 'title' => 'Kidney issues'],
                    ['id' => '5', 'title' => 'Liver problems'],
                    ['id' => '6', 'title' => 'Digestive disorders'],
                    ['id' => '7', 'title' => 'Hormonal imbalances'],
                    ['id' => '8', 'title' => 'Respiratory issues'],
                    ['id' => '9', 'title' => 'None of the above']
                ],
                'next_conditional' => [
                    'default' => 'allergies',
                    'conditions' => [
                        // If user selects any health condition, ask for details
                        [
                            'condition' => function ($response) {
                                    return $response != '9' && $response != 'None of the above';
                                },
                            'next' => 'health_details'
                        ]
                    ]
                ],
                'phase' => 2
            ],
            'health_details' => [
                'prompt' => 'Could you provide more details about your health conditions:\n- Duration\n- Medications\n- Severity level (mild/moderate/severe)',
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
                    ['id' => '2', 'title' => 'Gluten'],
                    ['id' => '3', 'title' => 'Nuts'],
                    ['id' => '4', 'title' => 'Seafood'],
                    ['id' => '5', 'title' => 'Eggs'],
                    ['id' => '6', 'title' => 'Soy'],
                    ['id' => '7', 'title' => 'None']
                ],
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
                    ['id' => '5', 'title' => 'Detoxification'],
                    ['id' => '6', 'title' => 'Sleep improvement'],
                    ['id' => '7', 'title' => 'Hair/skin health'],
                    ['id' => '8', 'title' => 'Organ recovery'],
                    ['id' => '9', 'title' => 'None specifically']
                ],
                'next_conditional' => [
                    'default' => 'diet_type',
                    'conditions' => [
                        [
                            'condition' => function ($response) {
                                    return $response == '8' || $response == 'Organ recovery';
                                },
                            'next' => 'organ_recovery'
                        ]
                    ]
                ],
                'phase' => 2
            ],
            'organ_recovery' => [
                'prompt' => 'Could you specify which organs you\'re focusing on?',
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
                    ['id' => '3', 'title' => 'Vegan', 'description' => 'No animal products'],
                    ['id' => '4', 'title' => 'Pescatarian', 'description' => 'Vegetarian + seafood'],
                    ['id' => '5', 'title' => 'Flexitarian', 'description' => 'Mostly plant-based'],
                    ['id' => '6', 'title' => 'Keto', 'description' => 'Low carb, high fat'],
                    ['id' => '7', 'title' => 'Paleo', 'description' => 'Whole foods, no grains/dairy'],
                    ['id' => '8', 'title' => 'Other', 'description' => 'Custom diet']
                ],
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
                    ['id' => '5', 'title' => 'Mediterranean'],
                    ['id' => '6', 'title' => 'East Asian'],
                    ['id' => '7', 'title' => 'Middle Eastern'],
                    ['id' => '8', 'title' => 'Continental'],
                    ['id' => '9', 'title' => 'No specific preference']
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
                    ['id' => '3', 'title' => 'Intermittent fasting'],
                    ['id' => '4', 'title' => 'OMAD (one meal a day)'],
                    ['id' => '5', 'title' => 'Flexible/No specific pattern']
                ],
                'next' => 'food_restrictions',
                'phase' => 3
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
                    ['id' => '6', 'title' => 'Wheat'],
                    ['id' => '7', 'title' => 'Nightshades'],
                    ['id' => '8', 'title' => 'Processed foods'],
                    ['id' => '9', 'title' => 'None']
                ],
                'next' => 'daily_schedule',
                'phase' => 3
            ],

            // PHASE 4: Lifestyle
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
                'next' => 'cooking_capability',
                'phase' => 4
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
                'next' => 'exercise_routine',
                'phase' => 4
            ],
            'exercise_routine' => [
                'prompt' => 'What\'s your current exercise pattern?',
                'type' => 'list',
                'header' => 'Exercise Pattern',
                'body' => 'Select your typical exercise approach',
                'options' => [
                    ['id' => '1', 'title' => 'Strength training focused'],
                    ['id' => '2', 'title' => 'Cardio focused'],
                    ['id' => '3', 'title' => 'Mixed strength & cardio'],
                    ['id' => '4', 'title' => 'Yoga/low-impact'],
                    ['id' => '5', 'title' => 'Sport-specific training'],
                    ['id' => '6', 'title' => 'Minimal/no regular exercise']
                ],
                'next' => 'stress_sleep',
                'phase' => 4
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
                'next' => 'primary_goal',
                'phase' => 4
            ],

            // PHASE 5: Goal Setting
            'primary_goal' => [
                'prompt' => 'What\'s your primary health goal?',
                'type' => 'list',
                'header' => 'Primary Goal',
                'body' => 'Select your most important health objective',
                'options' => [
                    ['id' => '1', 'title' => 'Weight loss'],
                    ['id' => '2', 'title' => 'Muscle gain'],
                    ['id' => '3', 'title' => 'Better energy levels'],
                    ['id' => '4', 'title' => 'Improved overall health'],
                    ['id' => '5', 'title' => 'Recovery from condition'],
                    ['id' => '6', 'title' => 'Athletic performance'],
                    ['id' => '7', 'title' => 'Longevity & prevention']
                ],
                'next' => 'timeline',
                'phase' => 5
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
                'phase' => 5
            ],
            'measurement_preference' => [
                'prompt' => 'How would you like to track progress?',
                'type' => 'list',
                'header' => 'Tracking Method',
                'body' => 'Select your preferred way to measure results',
                'options' => [
                    ['id' => '1', 'title' => 'Weight on scale'],
                    ['id' => '2', 'title' => 'Body measurements'],
                    ['id' => '3', 'title' => 'Energy levels'],
                    ['id' => '4', 'title' => 'Performance metrics'],
                    ['id' => '5', 'title' => 'Medical indicators'],
                    ['id' => '6', 'title' => 'Combination approach']
                ],
                'next' => 'plan_type',
                'phase' => 6
            ],

            // PHASE 6: Plan Customization
            'plan_type' => [
                'prompt' => 'Based on your inputs, I\'ll now create your personalized plan. Would you like:',
                'type' => 'button',
                'options' => [
                    ['id' => 'complete', 'title' => 'Complete Plan'],
                    ['id' => 'basic', 'title' => 'Phased Approach'],
                    ['id' => 'focus', 'title' => 'Focus on Diet']
                ],
                'next' => 'complete',
                'phase' => 6
            ],
            'complete' => [
                'prompt' => 'Thank you for completing the assessment! I\'m now generating your personalized diet plan. This may take a minute...',
                'type' => 'text',
                'is_final' => true,
                'phase' => 6
            ]
        ]
    ];

    public function __construct()
    {
        $this->apiKey = config('services.whatsapp.api_key');
        $this->apiUrl = config('services.whatsapp.api_url');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
    }

    /**
     * Process text message
     */
    private function processTextMessage(User $user, string $content)
    {
        // Check if user is in an assessment session
        $session = AssessmentSession::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if ($session) {
            // Continue assessment
            return $this->continueAssessment($user, $session, $content);
        }

        // Otherwise, check for commands
        $lowerContent = strtolower(trim($content));

        if ($lowerContent === 'start' || $lowerContent === 'begin' || $lowerContent === 'hi' || $lowerContent === 'hello') {
            // Start new assessment
            return $this->startAssessment($user);
        } elseif ($lowerContent === 'help') {
            return $this->sendHelpMessage($user);
        } elseif ($lowerContent === 'plan' || $lowerContent === 'my plan') {
            return $this->sendCurrentPlan($user);
        } elseif ($lowerContent === 'progress') {
            return $this->sendProgressUpdate($user);
        } else {
            // Default response
            $this->sendTextMessage($user->whatsapp_phone, "I'm not sure what you mean. Type 'help' for a list of commands or 'start' to begin a new assessment.");
            return null;
        }
    }

    /**
     * Start a new assessment session
     */
    private function startAssessment(User $user)
    {
        // Check if there's an assessment in progress
        $existingSession = AssessmentSession::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->first();

        if ($existingSession) {
            $this->sendTextMessage($user->whatsapp_phone, "You already have an assessment in progress. Would you like to continue or start over?", [
                'type' => 'button',
                'buttons' => [
                    ['type' => 'reply', 'reply' => ['id' => 'continue', 'title' => 'Continue']],
                    ['type' => 'reply', 'reply' => ['id' => 'restart', 'title' => 'Start Over']]
                ]
            ]);

            return null;
        }

        // Create new assessment session
        $session = new AssessmentSession();
        $session->user_id = $user->id;
        $session->current_phase = 1;
        $session->current_question = 'age';
        $session->responses = [];
        $session->status = 'in_progress';
        $session->started_at = now();
        $session->save();

        // Send welcome message
        $this->sendTextMessage($user->whatsapp_phone, "👋 Welcome to your personalized diet planning assistant! I'll ask a series of questions to understand your needs and create a tailored plan. Let's get started!");

        // Ask first question
        $this->askQuestion($user, 'age');

        return $session;
    }

    /**
     * Ask a specific question based on its ID
     */
    private function askQuestion(User $user, string $questionId)
    {
        $question = $this->assessmentFlow['questions'][$questionId] ?? null;

        if (!$question) {
            $this->sendTextMessage($user->whatsapp_phone, "Something went wrong. Please type 'start' to begin again.");
            return;
        }

        $prompt = $question['prompt'];

        switch ($question['type']) {
            case 'text':
                $this->sendTextMessage($user->whatsapp_phone, $prompt);
                break;

            case 'button':
                $buttons = [];
                foreach ($question['options'] as $option) {
                    $buttons[] = [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $option['id'],
                            'title' => $option['title']
                        ]
                    ];
                }

                $this->sendTextMessage($user->whatsapp_phone, $prompt, [
                    'type' => 'button',
                    'buttons' => $buttons
                ]);
                break;

            case 'list':
                $rows = [];
                foreach ($question['options'] as $option) {
                    $row = [
                        'id' => $option['id'],
                        'title' => $option['title']
                    ];

                    if (isset($option['description'])) {
                        $row['description'] = $option['description'];
                    }

                    $rows[] = $row;
                }

                $this->sendTextMessage($user->whatsapp_phone, $prompt, [
                    'type' => 'list',
                    'header' => [
                        'type' => 'text',
                        'text' => $question['header'] ?? 'Please select'
                    ],
                    'body' => [
                        'text' => $question['body'] ?? 'Choose from the options below'
                    ],
                    'action' => [
                        'button' => 'Select'
                    ],
                    'sections' => [
                        [
                            'title' => $question['header'] ?? 'Options',
                            'rows' => $rows
                        ]
                    ]
                ]);
                break;
        }

        // Update session with current question
        $session = AssessmentSession::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if ($session) {
            $session->current_question = $questionId;
            $session->current_phase = $question['phase'] ?? $session->current_phase;
            $session->save();
        }
    }

    /**
     * Continue the assessment with refactored approach
     */
    private function continueAssessment(User $user, AssessmentSession $session, string $response, string $displayText = null)
    {
        $currentQuestion = $session->current_question;
        $responses = $session->responses ?? [];

        // Handle restart request
        if ($response === 'restart') {
            $session->status = 'abandoned';
            $session->save();

            return $this->startAssessment($user);
        }

        // Get the current question definition
        $question = $this->assessmentFlow['questions'][$currentQuestion] ?? null;

        if (!$question) {
            // Unknown question, restart assessment
            $this->sendTextMessage($user->whatsapp_phone, "Something went wrong with your assessment. Let's start over.");
            $session->status = 'abandoned';
            $session->save();

            return $this->startAssessment($user);
        }

        // Validate the response
        $isValid = $this->validateResponse($response, $question);

        if (!$isValid) {
            // Re-ask the question with error message
            $this->sendTextMessage(
                $user->whatsapp_phone,
                $question['error_message'] ?? "Please provide a valid response."
            );
            $this->askQuestion($user, $currentQuestion);
            return $session;
        }

        // Store the response
        $responses[$currentQuestion] = $response;

        // Check if this is the final question
        if (isset($question['is_final']) && $question['is_final']) {
            // Complete the assessment
            try {
                // Mark session as completed
                $session->status = 'completed';
                $session->completed_at = now();
                $session->responses = $responses;
                $session->save();

                // Generate diet plan using AI
                $userGym = $user->gyms()->first();
                $aiService = AIServiceFactory::create($userGym);

                $dietPlan = $aiService->generateDietPlan($session);

                if ($dietPlan) {
                    // Send diet plan
                    $this->sendDietPlanSummary($user, $dietPlan);
                } else {
                    $this->sendTextMessage($user->whatsapp_phone, "I'm having trouble generating your diet plan right now. Please try again later or contact support.");
                }
            } catch (\Exception $e) {
                Log::error('Error generating diet plan', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id
                ]);

                $this->sendTextMessage($user->whatsapp_phone, "I encountered an error while generating your diet plan. Please try again later or contact support.");
            }

            return $session;
        }

        // Determine the next question
        $nextQuestion = $this->getNextQuestion($question, $response);

        if (!$nextQuestion) {
            $this->sendTextMessage($user->whatsapp_phone, "I'm not sure what question to ask next. Let's start over.");
            $session->status = 'abandoned';
            $session->save();

            return $this->startAssessment($user);
        }

        // Ask the next question
        $this->askQuestion($user, $nextQuestion);

        // Update session responses
        $session->responses = $responses;
        $session->save();

        return $session;
    }

    /**
     * Validate response based on question type and rules
     */
    private function validateResponse(string $response, array $question)
    {
        $type = $question['type'] ?? 'text';

        switch ($type) {
            case 'text':
                if (isset($question['validation'])) {
                    if ($question['validation'] === 'numeric|min:12|max:120') {
                        // Age validation
                        return is_numeric($response) && (int) $response >= 12 && (int) $response <= 120;
                    }
                    // Add more validation rules as needed
                }
                return true;

            case 'button':
                $allowedValues = array_column($question['options'], 'id');
                return in_array($response, $allowedValues);

            case 'list':
                $allowedValues = array_column($question['options'], 'id');
                // For multi-select lists, response could be comma-separated
                if (isset($question['multiple']) && $question['multiple']) {
                    $values = explode(',', $response);
                    foreach ($values as $value) {
                        if (!in_array(trim($value), $allowedValues)) {
                            return false;
                        }
                    }
                    return true;
                }
                return in_array($response, $allowedValues);

            default:
                return true;
        }
    }

    /**
     * Determine the next question based on current question and response
     */
    private function getNextQuestion(array $question, string $response)
    {
        // Check for conditional next question
        if (isset($question['next_conditional'])) {
            $conditionals = $question['next_conditional']['conditions'] ?? [];

            foreach ($conditionals as $conditional) {
                $condition = $conditional['condition'];

                // If condition is a closure, check it
                if (is_callable($condition) && $condition($response)) {
                    return $conditional['next'];
                }
                // If condition is a simple value match
                elseif (is_string($condition) && $response === $condition) {
                    return $conditional['next'];
                }
                // If condition is an array of values to match
                elseif (is_array($condition) && in_array($response, $condition)) {
                    return $conditional['next'];
                }
            }

            // If no conditions matched, use default
            return $question['next_conditional']['default'] ?? null;
        }

        // Simple next question
        return $question['next'] ?? null;
    }

    /**
     * Handle incoming WhatsApp message
     */
    public function handleIncomingMessage(array $payload)
    {
        Log::info('Incoming WhatsApp message', ['payload' => $payload]);

        try {
            $entry = $payload['entry'][0] ?? null;

            if (!$entry) {
                return null;
            }

            $changes = $entry['changes'][0] ?? null;

            if (!$changes || ($changes['field'] !== 'messages')) {
                return null;
            }

            $value = $changes['value'] ?? null;
            $messages = $value['messages'] ?? null;

            if (!$messages || empty($messages)) {
                return null;
            }

            $message = $messages[0];
            $fromNumber = $value['contacts'][0]['wa_id'] ?? null;

            if (!$fromNumber) {
                return null;
            }
            // Find or create the user based on WhatsApp number
            $user = $this->findOrCreateUserByWhatsAppNumber($fromNumber);

            if (!$user) {
                Log::error('Could not find or create user', ['wa_number' => $fromNumber]);
                return null;
            }

            // Store the message
            $this->storeMessage($message, $user, 'incoming');

            // Process the message based on message type
            $messageType = array_key_first($message);
            $messageContent = $message[$messageType];

            switch ($messageType) {
                case 'text':
                    return $this->processTextMessage($user, $messageContent['body']);

                case 'interactive':
                    return $this->processInteractiveMessage($user, $messageContent);

                case 'button':
                    return $this->processButtonMessage($user, $messageContent);

                case 'image':
                    return $this->processImageMessage($user, $messageContent);

                default:
                    // Send default response for unsupported message types
                    $this->sendTextMessage($user->whatsapp_phone, "I can only process text messages, buttons, and interactive messages at the moment.");
                    return null;
            }
        } catch (\Exception $e) {
            Log::error('Error handling WhatsApp message', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Process interactive message (buttons/lists)
     */
    private function processInteractiveMessage(User $user, array $content)
    {
        $type = $content['type'] ?? null;

        if ($type === 'button_reply') {
            $buttonId = $content['button_reply']['id'] ?? null;
            $buttonText = $content['button_reply']['title'] ?? null;

            // Process button response
            $session = AssessmentSession::where('user_id', $user->id)
                ->where('status', 'in_progress')
                ->latest()
                ->first();

            if ($session) {
                return $this->continueAssessment($user, $session, $buttonId, $buttonText);
            }
        } elseif ($type === 'list_reply') {
            $listId = $content['list_reply']['id'] ?? null;
            $listTitle = $content['list_reply']['title'] ?? null;

            // Process list response
            $session = AssessmentSession::where('user_id', $user->id)
                ->where('status', 'in_progress')
                ->latest()
                ->first();

            if ($session) {
                return $this->continueAssessment($user, $session, $listId, $listTitle);
            }
        }

        // Default response
        $this->sendTextMessage($user->whatsapp_phone, "I'm not sure what you selected. Type 'help' for assistance or 'start' to begin a new assessment.");
        return null;
    }

    /**
     * Process button message
     */
    private function processButtonMessage(User $user, array $content)
    {
        $buttonText = $content['text'] ?? null;

        // Process button response similar to text message
        $session = AssessmentSession::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if ($session && $buttonText) {
            return $this->continueAssessment($user, $session, $buttonText);
        }

        // Default response
        $this->sendTextMessage($user->whatsapp_phone, "I received your button response, but I'm not sure how to process it right now. Type 'help' for assistance.");
        return null;
    }

    /**
     * Process image message
     */
    private function processImageMessage(User $user, array $content)
    {
        // Get image information
        $imageId = $content['id'] ?? null;
        $caption = $content['caption'] ?? '';

        // Check if user is in progress tracking mode
        $progressTracking = false; // This would come from a user setting or state

        if ($progressTracking) {
            // Store image as progress photo
            // This would handle storing the image for progress tracking
            $this->sendTextMessage($user->whatsapp_phone, "Thanks for sharing your progress photo! It has been saved to your profile.");
        } else {
            // Generic response for images
            $this->sendTextMessage($user->whatsapp_phone, "Thanks for sharing the image. Currently, I can only process text and interactive messages fully. If you have a question about your diet plan, please type it out.");
        }

        return null;
    }

    /**
     * Send the diet plan summary to the user
     */
    private function sendDietPlanSummary(User $user, DietPlan $dietPlan)
    {
        // Send summary message
        $message = "🎉 *Your Diet Plan is Ready!* 🎉\n\n";
        $message .= "*{$dietPlan->title}*\n";
        $message .= "{$dietPlan->description}\n\n";
        $message .= "Daily targets:\n";
        $message .= "• Calories: *{$dietPlan->daily_calories}* kcal\n";
        $message .= "• Protein: *{$dietPlan->protein_grams}g* ({$this->calculateMacroPercentage($dietPlan, 'protein')}%)\n";
        $message .= "• Carbs: *{$dietPlan->carbs_grams}g* ({$this->calculateMacroPercentage($dietPlan, 'carbs')}%)\n";
        $message .= "• Fats: *{$dietPlan->fats_grams}g* ({$this->calculateMacroPercentage($dietPlan, 'fats')}%)\n\n";
        $message .= "I've created meal plans for every day of the week. Type 'plan' anytime to see your current plan or 'day' followed by the day (e.g., 'day monday') to see a specific day's meals.";

        $this->sendTextMessage($user->whatsapp_phone, $message);

        // Send first day's meal plan as an example
        $this->sendDayMealPlan($user, $dietPlan, 'monday');

        // Send follow-up schedule
        $this->sendTextMessage($user->whatsapp_phone, "I'll check in with you:\n• Daily for quick status updates\n• Weekly for detailed assessments\n• Monthly for plan adjustments\n\nType 'help' anytime to see available commands.");
    }

    /**
     * Calculate macro percentage for display
     */
    private function calculateMacroPercentage(DietPlan $dietPlan, string $macro)
    {
        $caloriesFromMacro = 0;

        if ($macro === 'protein') {
            $caloriesFromMacro = $dietPlan->protein_grams * 4;
        } elseif ($macro === 'carbs') {
            $caloriesFromMacro = $dietPlan->carbs_grams * 4;
        } elseif ($macro === 'fats') {
            $caloriesFromMacro = $dietPlan->fats_grams * 9;
        }

        if ($dietPlan->daily_calories > 0) {
            return round(($caloriesFromMacro / $dietPlan->daily_calories) * 100);
        }

        return 0;
    }

    /**
     * Send a specific day's meal plan
     */
    private function sendDayMealPlan(User $user, DietPlan $dietPlan, string $day)
    {
        $mealPlan = $dietPlan->mealPlans()->where('day_of_week', $day)->first();

        if (!$mealPlan) {
            $this->sendTextMessage($user->whatsapp_phone, "I couldn't find a meal plan for {$day}. Please try another day.");
            return;
        }

        $message = "🍽️ *" . ucfirst($day) . "'s Meal Plan* 🍽️\n\n";

        $meals = $mealPlan->meals()->orderByRaw("FIELD(meal_type, 'breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack')")->get();

        $totalCalories = 0;
        $totalProtein = 0;
        $totalCarbs = 0;
        $totalFats = 0;

        foreach ($meals as $meal) {
            $mealTypeDisplay = $this->getMealTypeDisplay($meal->meal_type);

            $message .= "*{$mealTypeDisplay}* ({$meal->time_of_day})\n";
            $message .= "{$meal->title}\n";
            $message .= "{$meal->description}\n";
            $message .= "• Calories: {$meal->calories} kcal\n";
            $message .= "• Protein: {$meal->protein_grams}g | Carbs: {$meal->carbs_grams}g | Fats: {$meal->fats_grams}g\n\n";

            $totalCalories += $meal->calories;
            $totalProtein += $meal->protein_grams;
            $totalCarbs += $meal->carbs_grams;
            $totalFats += $meal->fats_grams;
        }

        $message .= "Total: {$totalCalories} kcal | P: {$totalProtein}g | C: {$totalCarbs}g | F: {$totalFats}g\n\n";
        $message .= "To see recipe details, type 'recipe' followed by the meal type and day (e.g., 'recipe breakfast monday')";

        $this->sendTextMessage($user->whatsapp_phone, $message);
    }

    /**
     * Get display name for meal type
     */
    private function getMealTypeDisplay(string $mealType)
    {
        $map = [
            'breakfast' => 'Breakfast',
            'morning_snack' => 'Morning Snack',
            'lunch' => 'Lunch',
            'afternoon_snack' => 'Afternoon Snack',
            'dinner' => 'Dinner',
            'evening_snack' => 'Evening Snack',
            'pre_workout' => 'Pre-Workout',
            'post_workout' => 'Post-Workout'
        ];

        return $map[$mealType] ?? ucfirst($mealType);
    }

    /**
     * Send a help message with available commands
     */
    private function sendHelpMessage(User $user)
    {
        $message = "🤖 *Available Commands* 🤖\n\n";
        $message .= "• *start* - Begin a new assessment\n";
        $message .= "• *plan* - View your current diet plan\n";
        $message .= "• *day [day]* - View a specific day's meal plan (e.g., 'day monday')\n";
        $message .= "• *recipe [meal] [day]* - View recipe for a specific meal (e.g., 'recipe breakfast monday')\n";
        $message .= "• *progress* - View your progress\n";
        $message .= "• *checkin* - Submit daily check-in\n";
        $message .= "• *help* - Show this help message\n\n";
        $message .= "Reply anytime with your question or concern, and I'll do my best to assist you!";

        $this->sendTextMessage($user->whatsapp_phone, $message);
    }

    /**
     * Send current plan to user
     */
    private function sendCurrentPlan(User $user)
    {
        $dietPlan = DietPlan::where('client_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$dietPlan) {
            $this->sendTextMessage($user->whatsapp_phone, "You don't have an active diet plan yet. Type 'start' to begin the assessment process and create your personalized plan.");
            return;
        }

        $this->sendDietPlanSummary($user, $dietPlan);
    }

    /**
     * Send progress update to user
     */
    private function sendProgressUpdate(User $user)
    {
        // In a full implementation, you would query progress data
        // For now, send a placeholder message
        $this->sendTextMessage($user->whatsapp_phone, "Progress tracking is coming soon! You'll be able to see your weight changes, compliance rate, and other metrics here.");
    }

    /**
     * Store WhatsApp message in database
     */
    private function storeMessage(array $message, User $user, string $direction)
    {
        try {
            $messageType = array_key_first($message);
            $content = '';

            switch ($messageType) {
                case 'text':
                    $content = $message['text']['body'] ?? '';
                    break;

                case 'interactive':
                    $interactiveType = $message['interactive']['type'] ?? '';
                    if ($interactiveType === 'button_reply') {
                        $content = $message['interactive']['button_reply']['title'] ?? '';
                    } elseif ($interactiveType === 'list_reply') {
                        $content = $message['interactive']['list_reply']['title'] ?? '';
                    }
                    break;

                default:
                    $content = json_encode($message);
            }

            $conversation = new WhatsappConversation();
            $conversation->user_id = $user->id;
            $conversation->wa_message_id = $message['id'] ?? null;
            $conversation->direction = $direction;
            $conversation->message_type = $messageType;
            $conversation->content = $content;
            $conversation->metadata = $message;

            if ($direction === 'incoming') {
                $conversation->status = 'read';
                $conversation->read_at = now();
            } else {
                $conversation->status = 'sent';
                $conversation->sent_at = now();
            }

            $conversation->save();

            return $conversation;
        } catch (\Exception $e) {
            Log::error('Error storing WhatsApp message', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);

            return null;
        }
    }

    /**
     * Find or create user by WhatsApp number
     */
    private function findOrCreateUserByWhatsAppNumber(string $whatsappNumber)
    {
        $user = User::where('whatsapp_phone', $whatsappNumber)->first();

        if ($user) {
            return $user;
        }

        // Create new user
        $user = new User();
        $user->name = "WhatsApp User";
        $user->email = "wa_{$whatsappNumber}@example.com"; // Temporary email
        $user->password = bcrypt(Str::random(16));
        $user->whatsapp_phone = $whatsappNumber;
        $user->status = 'active';
        $user->save();

        // Assign client role
        $user->assignRole('client');

        return $user;
    }

    /**
     * Send text message to WhatsApp
     */
    public function sendTextMessage(string $to, string $message, array $interactive = null)
    {
        try {
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
            ];

            if ($interactive) {
                $payload['type'] = 'interactive';
                $payload['interactive'] = $interactive;
            } else {
                $payload['type'] = 'text';
                $payload['text'] = [
                    'preview_url' => false,
                    'body' => $message
                ];
            }

            $response = Http::withToken($this->apiKey)
                ->post("{$this->apiUrl}/{$this->phoneNumberId}/messages", $payload);

            if (!$response->successful()) {
                Log::error('WhatsApp API error', [
                    'error' => $response->body(),
                    'to' => $to
                ]);

                return false;
            }

            // Find user
            $user = User::where('whatsapp_phone', $to)->first();

            if ($user) {
                // Store message
                $this->storeMessage([
                    'text' => [
                        'body' => $message
                    ],
                    'id' => $response->json('messages.0.id') ?? null
                ], $user, 'outgoing');
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Error sending WhatsApp message', [
                'error' => $e->getMessage(),
                'to' => $to
            ]);

            return false;
        }
    }
}