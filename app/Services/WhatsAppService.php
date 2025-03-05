<?php
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

    protected $groceryListService;
    protected $nutritionInfoService;
    protected $conditionalChecks;
    protected $progressTrackingService;

    protected $assessmentFlow;

    public function __construct(
        GroceryListService $groceryListService,
        NutritionInfoService $nutritionInfoService,
        WhatsAppProgressTrackingService $progressTrackingService
    ) {
        $this->apiKey = config('services.whatsapp.api_key');
        $this->apiUrl = config('services.whatsapp.api_url');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');

        // Initialize services or create them if null
        $this->groceryListService = $groceryListService;
        $this->nutritionInfoService = $nutritionInfoService;
        $this->progressTrackingService = $progressTrackingService;

        // Load assessment flow from configuration
        $this->loadAssessmentFlow();

        // Initialize conditional checks
        $this->conditionalChecks = \App\Config\DietAssessmentFlow::getConditionalChecks();
    }

    /**
     * Load assessment flow from configuration
     */
    private function loadAssessmentFlow($level = 'moderate')
    {
        // Get user language preference (from database or session)
        $userLang = 'en'; // Default to English

        // Load questions based on level and language
        $this->assessmentFlow = [
            'phases' => \App\Config\DietAssessmentFlow::getPhases($userLang),
            'questions' => \App\Config\DietAssessmentFlow::getQuestions($level, $userLang)
        ];
    }

    /**
     * Handle validation and processing of multiple selections
     * 
     * @param string $response The response from the user
     * @param array $options The available options
     * @return array The processed selections
     */
    private function processMultipleSelections(string $response): array
    {
        // Split by commas for multiple selections
        if (strpos($response, ',') !== false) {
            $selections = array_map('trim', explode(',', $response));
        } else {
            $selections = [$response];
        }

        return $selections;
    }

    // Lazy load WhatsAppProgressTrackingService to avoid circular dependency
    public function getProgressTrackingService()
    {
        return app(WhatsAppProgressTrackingService::class);
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

        // First, check for standard commands
        if ($lowerContent === 'start' || $lowerContent === 'begin' || $lowerContent === 'hi' || $lowerContent === 'hello') {
            // Start new assessment
            return $this->startAssessment($user);
        } elseif ($lowerContent === 'help') {
            return $this->sendHelpMessage($user);
        } elseif ($lowerContent === 'plan' || $lowerContent === 'my plan') {
            return $this->sendCurrentPlan($user);
        } elseif ($lowerContent === 'progress') {
            return $this->sendProgressUpdate($user);
        } elseif (Str::startsWith($lowerContent, 'day ')) {
            $day = trim(substr($lowerContent, 4));
            return $this->sendDayMealPlan($user, $this->getCurrentDietPlan($user), $day);
        }

        // Check for grocery list commands
        $groceryResponse = $this->groceryListService->processGroceryCommand($user, $lowerContent);
        if ($groceryResponse !== null) {
            $this->sendTextMessage($user->whatsapp_phone, $groceryResponse);
            return null;
        }

        // Check for nutrition commands
        $nutritionResponse = $this->nutritionInfoService->processNutritionCommand($user, $lowerContent);
        if ($nutritionResponse !== null) {
            $this->sendTextMessage($user->whatsapp_phone, $nutritionResponse);
            return null;
        }

        // Check for progress tracking commands
        $progressResponse = $this->getProgressTrackingService()->processProgressCommand($user, $lowerContent);
        if ($progressResponse !== null) {
            $this->sendTextMessage($user->whatsapp_phone, $progressResponse);
            return null;
        }

        // Check for recipe commands
        if (Str::startsWith($lowerContent, 'recipe ')) {
            $params = explode(' ', substr($lowerContent, 7), 2);
            if (count($params) == 2) {
                return $this->sendRecipe($user, $params[0], $params[1]);
            }
        }

        // Check for calendar commands
        if ($lowerContent === 'calendar sync' || $lowerContent === 'sync calendar') {
            $syncResult = $this->getProgressTrackingService()->createCalendarEntries($user);
            $this->sendTextMessage($user->whatsapp_phone, $syncResult);
            return null;
        }

        // Default response
        $this->sendTextMessage($user->whatsapp_phone, "I'm not sure what you mean. Type 'help' for a list of commands or 'start' to begin a new assessment.");
        return null;
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
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => ['text' => "You already have an assessment in progress. Would you like to continue or start over?"],
                    'action' => [
                        'buttons' => [
                            ['type' => 'reply', 'reply' => ['id' => 'continue', 'title' => 'Continue']],
                            ['type' => 'reply', 'reply' => ['id' => 'restart', 'title' => 'Start Over']]
                        ]
                    ]
                ]
            ]);

            return null;
        }

        // Ask the user which assessment plan they prefer before starting
        $this->sendTextMessage($user->whatsapp_phone, "👋 Welcome to your personalized diet planning assistant!", [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => "Please select an assessment type:"],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'quick',
                                'title' => 'Quick (2 min)'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'moderate',
                                'title' => 'Detailed (5 min)'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'comprehensive',
                                'title' => 'Complete (10 min)'
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Create a temporary session to track that we're waiting for assessment type selection
        $session = new AssessmentSession();
        $session->user_id = $user->id;
        $session->current_phase = 0; // Special phase 0 for plan selection
        $session->current_question = 'assessment_type';
        $session->responses = [];
        $session->status = 'in_progress';
        $session->started_at = now();
        $session->save();

        return $session;
    }

    // Add a new method to handle the assessment type selection
    private function handleAssessmentTypeSelection(User $user, AssessmentSession $session, string $assessmentType)
    {
        // Valid assessment types
        $validTypes = ['quick', 'moderate', 'comprehensive'];

        // Validate the selection
        if (!in_array($assessmentType, $validTypes)) {
            $this->sendTextMessage($user->whatsapp_phone, "Please select a valid assessment type.");

            // Re-ask the question
            $this->sendTextMessage($user->whatsapp_phone, "Please select an assessment type:", [
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => ['text' => "Please select an assessment type:"],
                    'action' => [
                        'buttons' => [
                            ['type' => 'reply', 'reply' => ['id' => 'quick', 'title' => 'Quick (2 min)']],
                            ['type' => 'reply', 'reply' => ['id' => 'moderate', 'title' => 'Detailed (5 min)']],
                            ['type' => 'reply', 'reply' => ['id' => 'comprehensive', 'title' => 'Complete (10 min)']]
                        ]
                    ]
                ]
            ]);

            return $session;
        }

        // Get current responses, modify, and assign back
        $responses = $session->responses ?? [];
        $responses['assessment_type'] = $assessmentType;
        $session->responses = $responses;

        // Load the appropriate question set
        $this->loadAssessmentFlow($assessmentType);

        // Update session to start actual assessment
        $session->current_phase = 1;
        $session->current_question = 'age';
        $session->save();

        // Send welcome message
        $this->sendTextMessage($user->whatsapp_phone, "Great! I'll now ask you a series of questions to create your personalized diet plan. Let's get started!");

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
        Log::info('Next question payload:', ['payload' => $question]);

        $interactivePayload = null;

        switch ($question['type']) {
            case 'text':
                $this->sendTextMessage($user->whatsapp_phone, $prompt);
                return;

            case 'button':
                // WhatsApp limits buttons to 3
                $buttons = [];
                $options = array_slice($question['options'], 0, 3); // Ensure max 3 buttons

                foreach ($options as $option) {
                    $buttons[] = [
                        'type' => 'reply',
                        'reply' => [
                            'id' => $option['id'],
                            'title' => substr($option['title'], 0, 20) // WhatsApp limit
                        ]
                    ];
                }

                $interactivePayload = [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => ['text' => $prompt],
                        'action' => ['buttons' => $buttons]
                    ]
                ];
                break;

            case 'list':
                // Get session to store pagination info
                $session = AssessmentSession::where('user_id', $user->id)
                    ->where('status', 'in_progress')
                    ->latest()
                    ->first();

                $responses = $session->responses ?? [];

                // Check if we need pagination
                $allOptions = $question['options'];
                $totalOptions = count($allOptions);

                // Get current page from responses
                $paginationKey = '_pagination_' . $questionId;
                $page = $responses[$paginationKey]['page'] ?? 0;

                // Calculate start and end indices for options
                $startIndex = $page * 8; // We need space for "next" and "prev" buttons
                $displayOptions = array_slice($allOptions, $startIndex, 8);

                // Check if pagination controls are needed
                $showPrevious = $page > 0;
                $showNext = ($page + 1) * 8 < $totalOptions;

                // Add pagination controls if needed (within the 10 item limit)
                if ($showPrevious) {
                    array_unshift($displayOptions, [
                        'id' => 'prev_page',
                        'title' => '« Previous options',
                        'description' => 'Show previous set of options'
                    ]);
                }

                if ($showNext) {
                    $displayOptions[] = [
                        'id' => 'next_page',
                        'title' => 'More options »',
                        'description' => 'Show more options'
                    ];
                }

                // Update pagination info in session
                $responses[$paginationKey] = [
                    'page' => $page,
                    'total_pages' => ceil($totalOptions / 8)
                ];
                $session->responses = $responses;
                Log::info('session before save in function askQuestion  for LIST',(array)$responses);
                $session->save();

                // Create rows for the list
                $rows = [];
                foreach ($displayOptions as $option) {
                    $row = [
                        'id' => $option['id'],
                        'title' => substr($option['title'], 0, 24) // WhatsApp character limit
                    ];

                    if (isset($option['description'])) {
                        $row['description'] = substr($option['description'], 0, 72); // WhatsApp limit
                    }

                    $rows[] = $row;
                }

                $interactivePayload = [
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'list',
                        'header' => ['type' => 'text', 'text' => substr($question['header'] ?? 'Please select', 0, 60)],
                        'body' => ['text' => substr($question['body'] ?? 'Choose from the options below', 0, 1024)],
                        'action' => [
                            'button' => 'Select',
                            'sections' => [
                                [
                                    'title' => substr($question['header'] ?? 'Options', 0, 24),
                                    'rows' => $rows
                                ]
                            ]
                        ]
                    ]
                ];

                // Log the number of rows to ensure we're under the limit
                Log::info('List rows count:', ['count' => count($rows)]);
                break;
        }

        if ($interactivePayload) {
            $this->sendTextMessage($user->whatsapp_phone, $prompt, $interactivePayload);
        }

        // Update session with current question
        $session = AssessmentSession::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        Log::info('session', (array) $session);
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
        // Special handling for assessment type selection (Phase 0)
        if ($session->current_phase === 0 && $session->current_question === 'assessment_type') {
            return $this->handleAssessmentTypeSelection($user, $session, $response);
        }

        $currentQuestion = $session->current_question;
        $responses = $session->responses ?? [];

        // Check for pagination in responses
        $paginationKey = '_pagination_' . $currentQuestion;
        $pagination = $responses[$paginationKey] ?? null;

        // Handle pagination navigation if present
        if ($pagination && $response === 'next_page') {
            $page = $pagination['page'] + 1;
            $responses[$paginationKey]['page'] = $page;
            $session->responses = $responses;
            $session->save();
            $this->askQuestion($user, $currentQuestion);
            return $session;
        } elseif ($pagination && $response === 'prev_page') {
            $page = $pagination['page'] - 1;
            if ($page < 0)
                $page = 0;
            $responses[$paginationKey]['page'] = $page;
            $session->responses = $responses;
            $session->save();
            $this->askQuestion($user, $currentQuestion);
            return $session;
        }

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

        Log::info('$response', (array) $response);
        Log::info('$question', (array) $question);

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

        // Handle "Other" selections that require custom input
        if (
            ($response === 'Other' || $response === 'other') &&
            strpos($currentQuestion, '_custom') === false &&
            !isset($responses[$currentQuestion . '_other'])
        ) {

            // Store the initial response
            $responses[$currentQuestion] = $response;

            // Create a custom question ID for the follow-up
            $customQuestionId = $currentQuestion . '_custom';

            // Ask for custom input
            $this->sendTextMessage($user->whatsapp_phone, "Please provide more details about your selection:");

            // Update session with custom question and responses
            $session->current_question = $customQuestionId;
            $session->responses = $responses;
            $session->save();

            return $session;
        }

        // Handle custom input follow-up
        // Handle custom input follow-up
        if (strpos($currentQuestion, '_custom') !== false) {
            // Get the base question
            $baseQuestion = str_replace('_custom', '', $currentQuestion);

            // Log for debugging
            Log::info("Processing custom input", [
                'baseQuestion' => $baseQuestion,
                'customQuestion' => $currentQuestion,
                'response' => $response
            ]);

            // Store both the original selection and the custom input
            // Don't try to parse comma-separated values for custom inputs
            $responses[$currentQuestion] = $response;
            $responses[$baseQuestion . '_other'] = $response;

            // Get the base question details to determine next question
            $question = $this->assessmentFlow['questions'][$baseQuestion] ?? null;

            // If base question not found, handle the error gracefully
            if (!$question) {
                Log::error("Base question not found for custom input", [
                    'baseQuestion' => $baseQuestion,
                    'currentQuestion' => $currentQuestion
                ]);

                // Send an appropriate message
                $this->sendTextMessage(
                    $user->whatsapp_phone,
                    "Thank you for your detailed input. Let's continue with the assessment."
                );

                // Try to recover by getting the last valid question
                foreach ($this->assessmentFlow['questions'] as $qId => $qData) {
                    if (strpos($qId, '_custom') === false && isset($qData['phase'])) {
                        $question = $qData;
                        $currentQuestion = $qId;
                        break;
                    }
                }

                // If still no valid question, restart
                if (!$question) {
                    $this->sendTextMessage($user->whatsapp_phone, "I'm having trouble with the assessment. Let's start over.");
                    $session->status = 'abandoned';
                    $session->save();
                    return $this->startAssessment($user);
                }
            }

            // Override current question to be the base question for proper flow
            $currentQuestion = $baseQuestion;
        } else {
            // For regular (non-custom) responses

            // Check if this is a multiple selection question and handle comma-separated values
            if (isset($question['multiple']) && $question['multiple']) {
                $values = array_map('trim', explode(',', $response));

                if (!isset($responses['_multiselect_' . $currentQuestion])) {
                    $responses['_multiselect_' . $currentQuestion] = [];
                }

                foreach ($values as $value) {
                    if (!in_array($value, $responses['_multiselect_' . $currentQuestion])) {
                        $responses['_multiselect_' . $currentQuestion][] = $value;
                    }
                }

                // Store the selected options as JSON string or array
                $responses[$currentQuestion] = $responses['_multiselect_' . $currentQuestion];
            } else {
                $responses[$currentQuestion] = $response;
            }
        }

        Log::info("responses ", $responses);
        Log::info("questiondddd ", $question);

        // Check if this is the final question
        if (isset($question['is_final']) && $question['is_final']) {
            // Complete the assessment
            try {
                // Clean up internal pagination keys before completing
                foreach (array_keys($responses) as $key) {
                    if (strpos($key, '_pagination_') === 0) {
                        unset($responses[$key]);
                    }
                }

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
        Log::info("Current question: $currentQuestion, Response: $response, Next question: $nextQuestion");
        Log::info("Session responses before save:", ['responses' => $session->responses]);

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
        $session->current_question = $nextQuestion;
        Log::info('session before save in function assement default',(array)$responses);

        $session->save();
        Log::info("Session responses after save:", ['responses' => $session->responses]);

        return $session;
    }

    /**
     * Validate response based on question type and rules
     */
    private function validateResponse(string $response, array $question)
    {
        $type = $question['type'] ?? 'text';

        Log::info('question', $question);
        Log::info('step 1: ' . $type);

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
            case 'interactive':
                $allowedValues = array_column($question['options'], 'id');
                return in_array($response, $allowedValues);

            case 'list':
                $allowedValues = array_column($question['options'], 'id');
                $response = trim($response); // Trim input to avoid space issues

                if (isset($question['multiple']) && $question['multiple']) {
                    $values = array_map('trim', explode(',', $response));

                    foreach ($values as $value) {
                        if (!in_array($value, $allowedValues)) {
                            Log::info("Invalid option found", ['value' => $value]);
                            return false; // Invalid option detected
                        }
                    }
                    return true; // All options are valid
                }

                // Single select list validation
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
                $condition = $conditional['condition'] ?? null;

                // Skip if condition is not set
                if ($condition === null) {
                    continue;
                }

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
                // If condition refers to checking health conditions
                elseif ($condition === 'hasHealthCondition' && $this->hasHealthCondition($response)) {
                    return $conditional['next'];
                }
                // If condition refers to checking organ recovery needs
                elseif ($condition === 'hasOrganRecovery' && $this->hasOrganRecovery($response)) {
                    return $conditional['next'];
                }
                // If condition refers to checking post-surgery nutrition
                elseif ($condition === 'hasPostSurgery' && $this->hasPostSurgery($response)) {
                    return $conditional['next'];
                }
                // If condition refers to checking other allergies
                elseif ($condition === 'hasOtherAllergies' && $this->hasOtherAllergies($response)) {
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
        Log::info('Processing incoming WhatsApp message', ['payload' => $payload]);

        try {
            $entry = $payload['entry'][0] ?? null;
            if (!$entry) {
                Log::info('No entry in payload');
                return null;
            }

            $changes = $entry['changes'][0] ?? null;
            if (!$changes || ($changes['field'] !== 'messages')) {
                Log::info('No message changes in payload');
                return null;
            }

            $value = $changes['value'] ?? null;
            $messages = $value['messages'] ?? null;
            if (!$messages || empty($messages)) {
                Log::info('No messages in payload');
                return null;
            }

            $message = $messages[0];
            $fromNumber = $value['contacts'][0]['wa_id'] ?? null;
            if (!$fromNumber) {
                Log::info('No sender number in payload');
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
            $messageType = $message['type'] ?? null;
            if (!$messageType) {
                Log::error('Message type missing or invalid', ['message' => $message]);
                return null;
            }

            $messageContent = $message[$messageType] ?? null;

            Log::info('messageType' . $messageType);

            switch ($messageType) {
                case 'text':
                    return $this->processTextMessage($user, $messageContent['body'] ?? '');

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

        // Nutrition commands
        $message .= "*Nutrition:*\n";
        $message .= "• *nutrition [meal] [day]* - Get detailed nutrition for a meal\n";
        $message .= "• *macros [day]* - View macronutrient summary for a day\n";
        $message .= "• *calories* - View your daily calorie target\n\n";

        // Grocery list commands
        $message .= "*Grocery List:*\n";
        $message .= "• *grocery* - Show your current grocery list\n";
        $message .= "• *bought [item]* - Mark an item as purchased\n";
        $message .= "• *reset grocery* - Reset your grocery list\n\n";

        // Progress tracking commands
        $message .= "*Progress Tracking:*\n";
        $message .= "• *checkin* - Start daily check-in process\n";
        $message .= "• *progress* - View your progress report\n";
        $message .= "• *weight [value]* - Log your current weight\n";
        $message .= "• *water [amount]* - Log your water intake\n";
        $message .= "• *meal done* - Mark a meal as completed\n";
        $message .= "• *exercise done* - Log exercise completion\n\n";

        // Goal tracking commands
        $message .= "*Goal Tracking:*\n";
        $message .= "• *goal* - View your active goals\n";
        $message .= "• *goal new [description]* - Create a new goal\n";
        $message .= "• *goal update [value]* - Update goal progress\n\n";

        // Calendar commands
        $message .= "*Calendar:*\n";
        $message .= "• *calendar sync* - Sync meal plan to calendar\n\n";

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
            // Log the message for debugging
            Log::info('mymessage', $message);

            // Get message type directly from the type field
            $messageType = $message['type'] ?? 'text';

            // Ensure message_type is one of the allowed enum values
            if (!in_array($messageType, ['text', 'image', 'template', 'interactive', 'location'])) {
                $messageType = 'text'; // Default to text if unknown type
            }

            // Handle content extraction based on message type
            $content = '';
            switch ($messageType) {
                case 'text':
                    $content = $message['text']['body'] ?? 'No text content';
                    break;

                case 'interactive':
                    $interactive = $message['interactive'] ?? [];
                    $interactiveType = $interactive['type'] ?? '';

                    if ($interactiveType === 'button_reply') {
                        $content = $interactive['button_reply']['title'] ?? '';
                    } elseif ($interactiveType === 'list_reply') {
                        $content = $interactive['list_reply']['title'] ?? '';
                    } else {
                        $content = json_encode($interactive);
                    }
                    break;

                case 'image':
                    $content = 'Image message';
                    break;

                case 'template':
                    $content = 'Template message';
                    break;

                case 'location':
                    $content = 'Location message';
                    break;

                default:
                    $content = json_encode($message);
            }

            // Ensure content is not empty (database constraint)
            if (empty($content)) {
                $content = 'Empty message';
            }

            // Create and save the conversation record
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
                $payload = array_merge($payload, $interactive); // Merging interactive message data
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

    private function getCurrentDietPlan(User $user): ?DietPlan
    {
        return DietPlan::where('client_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();
    }

    private function sendRecipe(User $user, string $mealType, string $day): ?string
    {
        $dietPlan = $this->getCurrentDietPlan($user);

        if (!$dietPlan) {
            $this->sendTextMessage($user->whatsapp_phone, "You don't have an active diet plan. Type 'start' to begin the assessment process.");
            return null;
        }

        // Find the meal
        $mealPlan = $dietPlan->mealPlans()->where('day_of_week', $day)->first();

        if (!$mealPlan) {
            $this->sendTextMessage($user->whatsapp_phone, "I couldn't find a meal plan for {$day}.");
            return null;
        }

        $meal = $mealPlan->meals()->where('meal_type', $mealType)->first();

        if (!$meal) {
            $this->sendTextMessage($user->whatsapp_phone, "I couldn't find a {$mealType} meal for {$day}.");
            return null;
        }

        // Format recipe message
        $recipes = $meal->recipes;

        // Handle both string (JSON) and array representations
        if (is_string($recipes)) {
            $recipes = json_decode($recipes, true);
        }

        if (!$recipes || !isset($recipes['ingredients']) || !is_array($recipes['ingredients'])) {
            $this->sendTextMessage($user->whatsapp_phone, "No recipe found for this meal.");
            return null;
        }

        $message = "🍳 *Recipe: {$meal->title}* 🍳\n\n";
        $message .= "{$meal->description}\n\n";

        $message .= "*Ingredients:*\n";
        foreach ($recipes['ingredients'] as $ingredient) {
            // If the ingredient is an array, try extracting the name or a meaningful string
            if (is_array($ingredient)) {
                $ingredientText = implode(' ', $ingredient);
            } else {
                $ingredientText = $ingredient;
            }

            $message .= "• {$ingredientText}\n";
        }

        $message .= "\n*Instructions:*\n";
        if (isset($recipes['instructions']) && is_array($recipes['instructions'])) {
            $step = 1;
            foreach ($recipes['instructions'] as $instruction) {
                $message .= "{$step}. {$instruction}\n";
                $step++;
            }
        } else {
            $message .= "Simple preparation: Combine ingredients and cook to your preference.";
        }

        // Add nutrition information
        $message .= "\n\n*Nutrition Information:*\n";
        $message .= "Calories: {$meal->calories} kcal | P: {$meal->protein_grams}g | C: {$meal->carbs_grams}g | F: {$meal->fats_grams}g\n\n";
        $message .= "Type 'nutrition {$mealType} {$day}' for detailed nutrition breakdown.";

        // Add grocery list suggestion
        $message .= "\n\nType 'grocery' to add these ingredients to your shopping list.";

        $this->sendTextMessage($user->whatsapp_phone, $message);
        return null;
    }


    /**
     * Check if the response mentions organ recovery
     * 
     * @param string $response The response from the user
     * @return bool Whether the response indicates organ recovery
     */
    private function hasOrganRecovery(string $response): bool
    {
        // Convert string response to array for consistent processing
        $selections = $this->convertResponseToArray($response);

        // Check if any selection indicates organ recovery
        return in_array('8', $selections) || in_array('Organ recovery', $selections);
    }

    /**
     * Convert a string response to an array of selections
     * 
     * @param string $response The response from the user
     * @return array The array of selections
     */
    private function convertResponseToArray(string $response): array
    {
        // For multiple selections (comma-separated)
        if (strpos($response, ',') !== false) {
            return array_map('trim', explode(',', $response));
        }

        // For single selection
        return [$response];
    }


    /**
     * Check if the response indicates health conditions (not "None of the above")
     */
    private function hasHealthCondition(string $response): bool
    {
        $selections = $this->convertResponseToArray($response);

        // If selections contain "None of the above", return false
        if (in_array('15', $selections) || in_array('None of the above', $selections)) {
            return false;
        }

        // Otherwise return true if there are any selections
        return !empty($selections);
    }
    /**
     * Check if the response mentions post-surgery nutrition
     */
    private function hasPostSurgery(string $response): bool
    {
        $selections = $this->convertResponseToArray($response);
        return in_array('15', $selections) || in_array('Post-surgery nutrition', $selections);
    }

    /**
     * Check if the response mentions other allergies
     */
    private function hasOtherAllergies(string $response): bool
    {
        $selections = $this->convertResponseToArray($response);
        return in_array('13', $selections) || in_array('Other', $selections);
    }

}