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

    protected $helpMediaID;
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

        $this->helpMediaID = 9942940672384649;
    }

    /**
     * Load assessment flow from configuration
     */
    private function loadAssessmentFlow($level = 'moderate')
    {
        Log::info('leve of assement' . $level);
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

        if ($session) {
            // Continue assessment
            return $this->continueAssessment($user, $session, $content);
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
        // Check for existing context
        $userContext = $this->getUserContext($user);

        if ($userContext['has_active_plan']) {
            return $this->handleExistingPlanOptions($user, $userContext['active_plan']);
        }

        if ($userContext['has_incomplete_assessment']) {
            return $this->handleIncompleteAssessment($user, $userContext['incomplete_session']);
        }

        if ($userContext['has_profile']) {
            return $this->handleExistingProfileOptions($user);
        }

        // Only reach here for completely new users
        return $this->startNewUserAssessment($user);
    }

    private function getUserContext(User $user)
    {
        $context = [
            'has_profile' => false,
            'has_active_plan' => false,
            'has_incomplete_assessment' => false,
            'active_plan' => null,
            'incomplete_session' => null,
            'last_assessment_date' => null,
            'profile_completion' => 0
        ];

        // Check for active diet plan
        $activePlan = DietPlan::where('client_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if ($activePlan) {
            $context['has_active_plan'] = true;
            $context['active_plan'] = $activePlan;
        }

        // Check for incomplete assessment
        $incompleteSession = AssessmentSession::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->where('updated_at', '>', now()->subDays(7)) // Only sessions updated in the last 7 days
            ->latest()
            ->first();

        if ($incompleteSession) {
            $context['has_incomplete_assessment'] = true;
            $context['incomplete_session'] = $incompleteSession;
        }

        // Check profile completion
        $profileFields = ['age', 'gender', 'height', 'current_weight', 'target_weight', 'activity_level'];
        $filledFields = 0;

        foreach ($profileFields as $field) {
            if (!empty($user->$field)) {
                $filledFields++;
            }
        }

        $context['profile_completion'] = ($filledFields / count($profileFields)) * 100;
        $context['has_profile'] = $context['profile_completion'] > 50; // Consider profile exists if more than 50% complete

        return $context;
    }

    /**
     * Complete an assessment session and generate a diet plan
     * 
     * @param AssessmentSession $session The assessment session to complete
     * @return AssessmentSession The completed session
     */
    private function completeAssessment(AssessmentSession $session)
    {
        try {
            $responses = $session->responses;

            // Remove any internal tracking keys
            foreach (array_keys($responses) as $key) {
                if (strpos($key, '_pagination_') === 0 || strpos($key, '_multiselect_') === 0) {
                    unset($responses[$key]);
                }
            }

            $session->status = 'completed';
            $session->completed_at = now();
            $session->responses = $responses;
            $session->save();

            $user = User::find($session->user_id);
            $userGym = $user->gyms()->first();

            // You'll need to ensure this class exists and is properly imported
            $aiService = AIServiceFactory::create($userGym);

            if (!$aiService) {
                Log::error('Failed to create AI service', ['user_id' => $user->id]);
                $this->sendTextMessage($user->whatsapp_phone, "We encountered an issue generating your diet plan. Please try again later.");
                return $session;
            }

            // Generate the diet plan
            $dietPlan = $aiService->generateDietPlan($session);

            if ($dietPlan) {
                $this->sendDietPlanSummary($user, $dietPlan);
            } else {
                $this->sendTextMessage($user->whatsapp_phone, "I'm having trouble generating your diet plan right now. Please try again later.");
            }

            return $session;
        } catch (\Exception $e) {
            Log::error('Error completing assessment', [
                'error' => $e->getMessage(),
                'user_id' => $session->user_id
            ]);

            $this->sendTextMessage(
                User::find($session->user_id)->whatsapp_phone,
                "I encountered an error while generating your diet plan. Please try again later."
            );

            return $session;
        }
    }

    /**
     * Handle options for users with incomplete assessments
     * 
     * @param User $user The user
     * @param AssessmentSession $incompleteSession The incomplete assessment session
     * @return AssessmentSession
     */
    private function handleIncompleteAssessment(User $user, AssessmentSession $incompleteSession)
    {
        // Calculate completion percentage
        $this->loadAssessmentFlow($incompleteSession->assessment_type ?? 'moderate');
        $totalQuestions = count($this->assessmentFlow['questions']);
        $answeredQuestions = count(array_filter(array_keys($incompleteSession->responses ?? []), function ($key) {
            return !str_starts_with($key, '_');
        }));

        $completionPercentage = round(($answeredQuestions / $totalQuestions) * 100);
        $lastActive = $incompleteSession->updated_at->diffForHumans();

        $this->sendTextMessage($user->whatsapp_phone, "You have an incomplete assessment that's about {$completionPercentage}% complete. You last worked on it {$lastActive}.", [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => "Would you like to continue or start over?"],
                'action' => [
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'continue', 'title' => 'Continue']],
                        ['type' => 'reply', 'reply' => ['id' => 'restart', 'title' => 'Start Over']],
                        ['type' => 'reply', 'reply' => ['id' => 'change_type', 'title' => 'Change Plan Type']]
                    ]
                ]
            ]
        ]);

        // Update the session to indicate we're waiting for a response about continuing
        $incompleteSession->current_question = 'resume_decision';
        $incompleteSession->save();

        return $incompleteSession;
    }

    /**
     * Handle options for users with existing profiles but no active plan
     * 
     * @param User $user The user with an existing profile
     * @return AssessmentSession
     */
    private function handleExistingProfileOptions(User $user)
    {
        $this->sendTextMessage($user->whatsapp_phone, "Welcome back! We already have some of your information.", [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => "What would you like to do?"],
                'action' => [
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'use_existing', 'title' => 'Use My Profile']],
                        ['type' => 'reply', 'reply' => ['id' => 'update_profile', 'title' => 'Update Profile']],
                        ['type' => 'reply', 'reply' => ['id' => 'new_assessment', 'title' => 'Start Fresh']]
                    ]
                ]
            ]
        ]);

        // Create a new session to track the user's decision
        $session = new AssessmentSession();
        $session->user_id = $user->id;
        $session->current_phase = -2; // Special phase for existing profile decision
        $session->current_question = 'existing_profile_options';
        $session->responses = [];
        $session->status = 'in_progress';
        $session->started_at = now();
        $session->save();

        return $session;
    }


    /**
     * Start a completely new assessment for a new user
     * 
     * @param User $user The new user
     * @return AssessmentSession
     */
    private function startNewUserAssessment(User $user)
    {
        $this->sendTextMessage($user->whatsapp_phone, "👋 Welcome to your personalized diet planning assistant!", [
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

        // Create a new session for plan type selection
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
    private function transitionPlanType(AssessmentSession $session, string $newType)
    {
        $currentType = $session->assessment_type ?? 'quick';
        $responses = $session->responses ?? [];

        // Store the transition in responses
        $responses['previous_assessment_type'] = $currentType;
        $responses['transitioned_at'] = now()->toDateTimeString();

        // Get question sets for both types
        $this->loadAssessmentFlow($currentType);
        $currentQuestions = $this->assessmentFlow['questions'];

        $this->loadAssessmentFlow($newType);
        $newQuestions = $this->assessmentFlow['questions'];

        // Determine which questions are new
        $newQuestionIds = array_diff(array_keys($newQuestions), array_keys($currentQuestions));

        // Update session
        $session->assessment_type = $newType;
        $session->responses = $responses;
        $session->save();

        // Inform user about the transition
        $message = "You've switched from a {$currentType} to a {$newType} assessment. ";
        $message .= "We'll use your existing information and just ask " . count($newQuestionIds) . " additional questions.";
        $this->sendTextMessage($session->user->whatsapp_phone, $message);

        // Start asking new questions if any
        if (count($newQuestionIds) > 0) {
            $this->askQuestion($session->user, $newQuestionIds[0]);
        } else {
            // If no new questions, complete the assessment
            return $this->completeAssessment($session);
        }

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

        Log::info('assessmentType-step' . $assessmentType);
        // Load the appropriate question set
        $this->loadAssessmentFlow($assessmentType);

        // Update session to start actual assessment
        $session->current_phase = 1;
        $session->current_question = 'age';
        $session->save();

        // Send welcome message
        $this->sendTextMessage($user->whatsapp_phone, "Great! You selected *{$assessmentType}* assessment. Let's start with the first question.");
        // Ask first question
        $this->askQuestion($user, 'age');

        return $session;
    }


    /**
     * Handle options for users with existing active diet plans
     * 
     * @param User $user The user
     * @param DietPlan $activePlan The user's active diet plan
     * @return AssessmentSession|null
     */
    private function handleExistingPlanOptions(User $user, DietPlan $activePlan)
    {
        $this->sendTextMessage($user->whatsapp_phone, "You already have an active diet plan created on " . $activePlan->created_at->format('M d, Y') . ".", [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => "What would you like to do?"],
                'action' => [
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'view_plan', 'title' => 'View Plan']],
                        ['type' => 'reply', 'reply' => ['id' => 'modify_plan', 'title' => 'Modify Plan']],
                        ['type' => 'reply', 'reply' => ['id' => 'new_plan', 'title' => 'Create New Plan']]
                    ]
                ]
            ]
        ]);

        // Create temporary session to track the user's response to this menu
        $session = new AssessmentSession();
        $session->user_id = $user->id;
        $session->current_phase = -1; // Special phase for existing plan options
        $session->current_question = 'existing_plan_options';
        $session->responses = [
            'active_plan_id' => $activePlan->id
        ];
        $session->status = 'in_progress';
        $session->started_at = now();
        $session->save();

        return $session;
    }

    /**
     * Ask a specific question based on its ID
     */
    private function askQuestion(User $user, string $questionId)
    {
        Log::info('aks question $questionId' . $questionId);

        $question = $this->assessmentFlow['questions'][$questionId] ?? null;
        Log::info('aks question $question', $question);

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
                Log::info('session before save in function askQuestion  for LIST', (array) $responses);
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
        // Handle special case of initial assessment type selection
        if ($session->current_phase === 0 && $session->current_question === 'assessment_type') {
            return $this->handleAssessmentTypeSelection($user, $session, $response);
        }

        // Handle resumption decision responses
        if ($session->current_question === 'resume_decision') {
            if ($response === 'continue') {
                return $this->resumeAssessment($user, $session);
            } elseif ($response === 'restart') {
                $session->status = 'abandoned';
                $session->save();
                return $this->startAssessment($user);
            } elseif ($response === 'change_type') {
                return $this->askPlanTypeChange($user, $session);
            }
        }

        // Handle plan type change requests
        if (Str::startsWith($response, 'switch_')) {
            $newType = str_replace('switch_', '', $response);
            return $this->transitionPlanType($session, $newType);
        }

        // Handle existing profile options
        if ($session->current_phase === -2 && $session->current_question === 'existing_profile_options') {
            if ($response === 'use_existing') {
                return $this->startNewAssessmentWithExistingProfile($user);
            } elseif ($response === 'update_profile') {
                return $this->startProfileUpdate($user);
            } elseif ($response === 'new_assessment') {
                $session->status = 'abandoned';
                $session->save();
                return $this->startNewUserAssessment($user);
            }
        }

        // Handle existing plan options
        if ($session->current_phase === -1 && $session->current_question === 'existing_plan_options') {
            return $this->processExistingPlanChoice($user, $response, $this->getCurrentDietPlan($user));
        }

        // Load assessment flow for current session type
        $this->loadAssessmentFlow($session->assessment_type);

        $currentQuestion = $session->current_question;
        $responses = $session->responses ?? [];

        // Handle pagination for list questions
        $paginationKey = '_pagination_' . $currentQuestion;
        $pagination = $responses[$paginationKey] ?? null;

        if ($pagination) {
            if ($response === 'next_page') {
                $responses[$paginationKey]['page']++;
            } elseif ($response === 'prev_page') {
                $responses[$paginationKey]['page'] = max(0, $pagination['page'] - 1);
            }

            $session->responses = $responses;
            $session->save();
            $this->askQuestion($user, $currentQuestion);
            return $session;
        }

        // Get current question details
        $question = $this->assessmentFlow['questions'][$currentQuestion] ?? null;

        if (!$question) {
            $this->sendTextMessage($user->whatsapp_phone, "Something went wrong. Let's start over.");
            $session->status = 'abandoned';
            $session->save();
            return $this->startAssessment($user);
        }

        // Validate user response
        if (!$this->validateResponse($response, $question)) {
            $this->sendTextMessage($user->whatsapp_phone, $question['error_message'] ?? "Please provide a valid response.");
            $this->askQuestion($user, $currentQuestion);
            return $session;
        }

        // Store response based on question type
        if (($response === 'Other' || $response === 'other') && !isset($responses[$currentQuestion . '_other'])) {
            $responses[$currentQuestion] = $response;
            $session->current_question = $currentQuestion . '_custom';
            $this->sendTextMessage($user->whatsapp_phone, "Please provide more details:");
        } elseif (strpos($currentQuestion, '_custom') !== false) {
            $baseQuestion = str_replace('_custom', '', $currentQuestion);
            $responses[$baseQuestion . '_other'] = $response;
        } elseif (isset($question['multiple']) && $question['multiple']) {
            $values = array_map('trim', explode(',', $response));
            $responses['_multiselect_' . $currentQuestion] = array_unique(array_merge($responses['_multiselect_' . $currentQuestion] ?? [], $values));
            $responses[$currentQuestion] = $responses['_multiselect_' . $currentQuestion];
        } else {
            $responses[$currentQuestion] = $response;
        }

        // Check if this is the final question
        if (isset($question['is_final']) && $question['is_final']) {
            try {
                // Clean up response data
                foreach (array_keys($responses) as $key) {
                    if (strpos($key, '_pagination_') === 0) {
                        unset($responses[$key]);
                    }
                }

                $session->status = 'completed';
                $session->completed_at = now();
                $session->responses = $responses;
                $session->save();

                // Generate diet plan
                $userGym = $user->gyms()->first();
                $aiService = AIServiceFactory::create($userGym);

                if (!$aiService) {
                    Log::error('Failed to create AI service', ['user_id' => $user->id]);
                    $this->sendTextMessage($user->whatsapp_phone, "We encountered an issue generating your diet plan. Please try again later.");
                    return $session;
                }

                $dietPlan = $aiService->generateDietPlan($session);
                if ($dietPlan) {
                    $this->sendDietPlanSummary($user, $dietPlan);
                } else {
                    $this->sendTextMessage($user->whatsapp_phone, "I'm having trouble generating your diet plan right now. Please try again later.");
                }
            } catch (\Exception $e) {
                Log::error('Error generating diet plan', ['error' => $e->getMessage(), 'user_id' => $user->id]);
                $this->sendTextMessage($user->whatsapp_phone, "I encountered an error while generating your diet plan. Please try again later.");
            }
            return $session;
        }

        // Determine next question
        $nextQuestion = $this->getNextQuestion($question, $response);

        // Check if we need to skip this question based on existing profile data
        if ($this->shouldSkipQuestion($user, $nextQuestion)) {
            // Get answer from profile and store it
            $profileAnswer = $this->getAnswerFromProfile($user, $nextQuestion);
            if ($profileAnswer !== null) {
                $responses[$nextQuestion] = $profileAnswer;

                // Log the auto-filled answer
                Log::info('Auto-filled question from profile', [
                    'question' => $nextQuestion,
                    'answer' => $profileAnswer
                ]);

                // Recursively move to the next question
                $session->responses = $responses;
                $session->current_question = $nextQuestion;
                $session->save();

                // Get next question's details
                $nextQuestionData = $this->assessmentFlow['questions'][$nextQuestion] ?? null;
                if ($nextQuestionData) {
                    $nextNextQuestion = $this->getNextQuestion($nextQuestionData, $profileAnswer);
                    if ($nextNextQuestion) {
                        return $this->continueAssessment($user, $session, $profileAnswer);
                    }
                }
            }
        }

        // If no valid next question, start over
        if (!$nextQuestion) {
            $this->sendTextMessage($user->whatsapp_phone, "I'm not sure what question to ask next. Let's start over.");
            $session->status = 'abandoned';
            $session->save();
            return $this->startAssessment($user);
        }

        // Ask the next question and update session
        $this->askQuestion($user, $nextQuestion);
        $session->responses = $responses;
        $session->current_question = $nextQuestion;
        $session->save();

        return $session;
    }


    /**
     * Process the user's choice for their existing plan
     * 
     * @param User $user The user
     * @param string $choice The user's choice (view_plan, modify_plan, new_plan)
     * @param DietPlan $activePlan The user's active plan
     * @return mixed Result of processing
     */
    private function processExistingPlanChoice(User $user, string $choice, DietPlan $activePlan)
    {
        switch ($choice) {
            case 'view_plan':
                return $this->sendDietPlanSummary($user, $activePlan);

            case 'modify_plan':
                return $this->startPlanModification($user, $activePlan);

            case 'new_plan':
                // Archive current plan
                $activePlan->status = 'archived';
                $activePlan->save();

                // Start new assessment with context
                return $this->startNewAssessmentWithContext($user);

            default:
                $this->sendTextMessage($user->whatsapp_phone, "I didn't understand your choice. Please try again.");
                return $this->handleExistingPlanOptions($user, $activePlan);
        }
    }

    /**
     * Start a new assessment with context from a previous plan
     * 
     * @param User $user The user
     * @return AssessmentSession The new session
     */
    private function startNewAssessmentWithContext(User $user)
    {
        // Create a new session
        $session = new AssessmentSession();
        $session->user_id = $user->id;
        $session->current_phase = 0;
        $session->current_question = 'assessment_type';
        $session->responses = [];
        $session->status = 'in_progress';
        $session->started_at = now();
        $session->save();

        // Ask for assessment type
        $this->sendTextMessage($user->whatsapp_phone, "Great! Let's create a new diet plan. First, let's select an assessment type:", [
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

    /**
     * Start the process of modifying an existing plan
     * 
     * @param User $user The user
     * @param DietPlan $plan The plan to modify
     * @return AssessmentSession The new session for modification
     */
    private function startPlanModification(User $user, DietPlan $plan)
    {
        $this->sendTextMessage($user->whatsapp_phone, "What would you like to modify in your plan?", [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => "Please select what you'd like to change:"],
                'action' => [
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'calories', 'title' => 'Calorie Goal']],
                        ['type' => 'reply', 'reply' => ['id' => 'macros', 'title' => 'Macro Ratios']],
                        ['type' => 'reply', 'reply' => ['id' => 'meal_types', 'title' => 'Meal Structure']]
                    ]
                ]
            ]
        ]);

        // Create modification session
        $session = new AssessmentSession();
        $session->user_id = $user->id;
        $session->current_phase = -3; // Special phase for plan modification
        $session->current_question = 'plan_modification_type';
        $session->responses = [
            'plan_id' => $plan->id,
            'modification_started_at' => now()->toDateTimeString()
        ];
        $session->status = 'in_progress';
        $session->started_at = now();
        $session->save();

        return $session;
    }

    /**
     * Resume an assessment from where the user left off
     * 
     * @param User $user The user
     * @param AssessmentSession $session The session to resume
     * @return AssessmentSession The updated session
     */
    private function resumeAssessment(User $user, AssessmentSession $session)
    {
        // Check if the session has timed out
        if ($this->handleSessionTimeout($session, $user)) {
            return null;
        }

        // Calculate progress so far
        $this->loadAssessmentFlow($session->assessment_type ?? 'moderate');
        $totalQuestions = count($this->assessmentFlow['questions']);
        $answeredQuestions = count(array_filter(array_keys($session->responses ?? []), function ($key) {
            return !str_starts_with($key, '_');
        }));

        $progress = round(($answeredQuestions / $totalQuestions) * 100);

        // Generate a summary of key answers so far
        $summary = $this->generateResponseSummary($session);

        // Send a welcome back message with progress and summary
        $message = "Welcome back to your assessment! You're {$progress}% complete.\n\n";
        if (!empty($summary)) {
            $message .= "Here's what you've told me so far:\n";
            $message .= $summary;
            $message .= "\n\n";
        }
        $message .= "Let's continue with the next question:";

        $this->sendTextMessage($user->whatsapp_phone, $message);

        // Ask the current question again
        $this->askQuestion($user, $session->current_question);

        return $session;
    }

    /**
     * Check if a session has timed out and handle accordingly
     * 
     * @param AssessmentSession $session The session to check
     * @param User $user The user
     * @return bool Whether the session has timed out
     */
    private function handleSessionTimeout(AssessmentSession $session, User $user): bool
    {
        $timeoutDays = 7; // Sessions expire after 7 days

        if ($session->updated_at->diffInDays(now()) > $timeoutDays) {
            $this->sendTextMessage(
                $user->whatsapp_phone,
                "Your previous assessment has expired. Let's start a fresh one to ensure we have your most current information."
            );

            // Mark old session as expired
            $session->status = 'expired';
            $session->save();

            // Start new assessment
            $this->startAssessment($user);
            return true;
        }

        return false;
    }

    /**
     * Generate a summary of the key information provided in the assessment
     * 
     * @param AssessmentSession $session The session
     * @return string A formatted summary
     */
    private function generateResponseSummary(AssessmentSession $session): string
    {
        $responses = $session->responses ?? [];
        $summary = "";

        // Include only key information in the summary
        $keyFields = [
            'age' => 'Age',
            'gender' => 'Gender',
            'current_weight' => 'Current weight',
            'target_weight' => 'Target weight',
            'primary_goal' => 'Primary goal',
            'diet_type' => 'Diet type'
        ];

        foreach ($keyFields as $field => $label) {
            if (isset($responses[$field])) {
                $summary .= "• {$label}: {$responses[$field]}\n";
            }
        }

        return $summary;
    }

    /**
     * Determine if a question should be skipped based on existing profile data
     */
    private function shouldSkipQuestion(User $user, string $questionId): bool
    {
        // Only skip questions for non-dynamic fields
        $dynamicFields = ['current_weight', 'target_weight', 'primary_goal', 'allergies'];
        if (in_array($questionId, $dynamicFields)) {
            return false;
        }

        // Map question IDs to profile fields
        $fieldMapping = [
            'age' => 'age',
            'gender' => 'gender',
            'height' => 'height',
            'body_type' => 'body_type',
            // Add other mappings as needed
        ];

        // Only check fields that we have a mapping for
        if (!isset($fieldMapping[$questionId])) {
            return false;
        }

        $profileField = $fieldMapping[$questionId];
        $profileData = $user->getAttributes();

        // Skip if we have valid profile data for this field
        return !empty($profileData[$profileField]) && $this->isDataRecent($questionId, $user);
    }

    /**
     * Get answer from user profile for a specific question
     */
    private function getAnswerFromProfile(User $user, string $questionId): ?string
    {
        // Map question IDs to profile fields
        $fieldMapping = [
            'age' => 'age',
            'gender' => 'gender',
            'height' => 'height',
            'body_type' => 'body_type',
            'activity_level' => 'activity_level',
            // Add other mappings as needed
        ];

        if (!isset($fieldMapping[$questionId])) {
            return null;
        }

        $profileField = $fieldMapping[$questionId];
        $profileData = $user->getAttributes();

        return $profileData[$profileField] ?? null;
    }

    /**
     * Check if data for a particular field is recent enough to be trusted
     */
    private function isDataRecent(string $field, User $user): bool
    {
        // Define how recent data needs to be for different fields
        $recencyRequirements = [
            'current_weight' => 30, // Days
            'target_weight' => 60,
            'activity_level' => 60,
            'health_conditions' => 90,
            // Default for other fields
            'default' => 180
        ];

        // For this implementation, we'll just consider profile data recent enough
        // In a full implementation, you'd check when the profile was last updated
        return true;
    }

    /**
     * Start a new assessment using the existing profile data
     */
    private function startNewAssessmentWithExistingProfile(User $user)
    {
        // Create a new session
        $session = new AssessmentSession();
        $session->user_id = $user->id;
        $session->current_phase = 0;
        $session->current_question = 'assessment_type';
        $session->responses = [];
        $session->status = 'in_progress';
        $session->started_at = now();
        $session->save();

        // Ask for assessment type
        $this->sendTextMessage($user->whatsapp_phone, "Great! We'll use your existing profile information. First, let's select an assessment type:", [
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

    /**
     * Start a profile update process
     */
    private function startProfileUpdate(User $user)
    {
        // Create a new session specifically for profile updates
        $session = new AssessmentSession();
        $session->user_id = $user->id;
        $session->current_phase = -3; // Special phase for profile updates
        $session->current_question = 'profile_update_start';
        $session->responses = [];
        $session->status = 'in_progress';
        $session->started_at = now();
        $session->save();

        // Ask what profile information they want to update
        $this->sendTextMessage($user->whatsapp_phone, "What information would you like to update?", [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => "Please select what to update:"],
                'action' => [
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'update_weight', 'title' => 'Current Weight']],
                        ['type' => 'reply', 'reply' => ['id' => 'update_goals', 'title' => 'Goals']],
                        ['type' => 'reply', 'reply' => ['id' => 'update_health', 'title' => 'Health Info']]
                    ]
                ]
            ]
        ]);

        return $session;
    }

    /**
     * Ask user which plan type they want to change to
     */
    private function askPlanTypeChange(User $user)
    {
        $this->sendTextMessage($user->whatsapp_phone, "Which assessment type would you like to switch to?", [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => "Please select a new assessment type:"],
                'action' => [
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'switch_quick', 'title' => 'Quick (2 min)']],
                        ['type' => 'reply', 'reply' => ['id' => 'switch_moderate', 'title' => 'Detailed (5 min)']],
                        ['type' => 'reply', 'reply' => ['id' => 'switch_comprehensive', 'title' => 'Complete (10 min)']]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Handle plan type change request
     */
    private function handlePlanTypeChange(User $user, string $newType)
    {
        // Clean up the input
        $newType = strtolower(trim($newType));

        // Map button IDs to plan types
        if (Str::startsWith($newType, 'switch_')) {
            $newType = str_replace('switch_', '', $newType);
        }

        // Validate plan type
        $validTypes = ['quick', 'moderate', 'comprehensive'];
        if (!in_array($newType, $validTypes)) {
            $this->sendTextMessage(
                $user->whatsapp_phone,
                "I didn't recognize that plan type. Please select quick, moderate, or comprehensive."
            );
            return null;
        }

        // Find active session
        $session = AssessmentSession::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if (!$session) {
            // No active session, start a new one with the requested type
            return $this->startNewAssessmentWithType($user, $newType);
        }

        // Don't transition if already using the requested type
        if ($session->assessment_type === $newType) {
            $this->sendTextMessage(
                $user->whatsapp_phone,
                "You're already using the {$newType} assessment type."
            );
            return $session;
        }

        // Call the existing transition method
        return $this->transitionPlanType($session, $newType);
    }

    /**
     * Start a new assessment with a specific type
     */
    private function startNewAssessmentWithType(User $user, string $type)
    {
        $session = new AssessmentSession();
        $session->user_id = $user->id;
        $session->current_phase = 0;
        $session->current_question = 'assessment_type';
        $session->responses = ['assessment_type' => $type];
        $session->status = 'in_progress';
        $session->started_at = now();
        $session->save();

        $this->loadAssessmentFlow($type);

        // Update session to start actual assessment
        $session->current_phase = 1;
        $session->current_question = 'age';
        $session->save();

        $this->sendTextMessage(
            $user->whatsapp_phone,
            "Great! You selected *{$type}* assessment. Let's start with the first question."
        );

        // Ask first question
        $this->askQuestion($user, 'age');

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
        Log::info('content', $content);
        $type = $content['type'] ?? null;

        if ($type === 'button_reply') {
            $buttonId = $content['button_reply']['id'] ?? null;
            $buttonText = $content['button_reply']['title'] ?? null;

            // Otherwise, check for commands
            $lowerContent = strtolower(trim($buttonText));

            // Remove emojis and special characters
            $lowerContent = preg_replace('/[^\p{L}\p{N}\s]/u', '', $lowerContent);

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

    private function uploadMedia(string $filePath, string $mimeType)
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->attach('file', file_get_contents($filePath), basename($filePath), ['Content-Type' => $mimeType])
                ->post("{$this->apiUrl}/{$this->phoneNumberId}/media", [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimeType
                ]);

            if ($response->successful()) {
                return $response->json('id'); // Return the MEDIA_OBJECT_ID
            } else {
                Log::error('WhatsApp Media Upload Error', [
                    'error' => $response->body(),
                    'file_path' => $filePath
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp Media Upload Exception', [
                'error' => $e->getMessage(),
                'file_path' => $filePath
            ]);
            return false;
        }
    }

    /**
     * Send the diet plan summary to the user
     */
    private function sendDietPlanSummary(User $user, DietPlan $dietPlan)
    {
        $mediaId = $this->uploadMedia(public_path('images/diet-plan-banner.jpg'), 'image/jpeg');

        if ($mediaId) {
            Log::info("Uploaded successfully! Media ID: $mediaId");
        } else {
            Log::info("Upload failed!");
        }

        if (!$mediaId) {
            // Handle the error appropriately
            $this->sendTextMessage($user->whatsapp_phone, "We're unable to load your diet plan image at the moment, but here's your plan:");
        }

        // Construct the interactive message payload
        $interactive = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'header' => [
                    'type' => 'image',
                    'image' => [
                        'id' => $mediaId
                    ]
                ],
                'body' => [
                    'text' => "🎉 *Your Diet Plan is Ready!* 🎉\n\n" .
                        "*{$dietPlan->title}*\n" .
                        "{$dietPlan->description}\n\n" .
                        "Daily targets:\n" .
                        "• Calories: *{$dietPlan->daily_calories}* kcal\n" .
                        "• Protein: *{$dietPlan->protein_grams}g* ({$this->calculateMacroPercentage($dietPlan, 'protein')}%)\n" .
                        "• Carbs: *{$dietPlan->carbs_grams}g* ({$this->calculateMacroPercentage($dietPlan, 'carbs')}%)\n" .
                        "• Fats: *{$dietPlan->fats_grams}g* ({$this->calculateMacroPercentage($dietPlan, 'fats')}%)\n\n" .
                        "I've created meal plans for every day of the week. Type 'plan' anytime to see your current plan or 'day' followed by the day (e.g., 'day monday') to see a specific day's meals."
                ],
                'footer' => [
                    'text' => "Need assistance? Type 'help' anytime."
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'view_plan',
                                'title' => '📜 View Full Plan'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'adjust_goal',
                                'title' => '🎯 Adjust My Goal'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'todays_meals',
                                'title' => '🍽 Today’s Meals'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        // Send the interactive message
        $this->sendTextMessage($user->whatsapp_phone, '', $interactive);

        // Send first day's meal plan as an example
        $this->sendDayMealPlan($user, $dietPlan, 'monday');

        // Send follow-up schedule
        $followUpMessage = "I'll check in with you:\n" .
            "• Daily for quick status updates\n" .
            "• Weekly for detailed assessments\n" .
            "• Monthly for plan adjustments\n\n" .
            "Type 'help' anytime to see available commands.";
        $this->sendTextMessage($user->whatsapp_phone, $followUpMessage);
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
        // Construct the interactive help message
        $bodyText = "🤖 *Need Help? Use These Commands* 🤖\n\n" .
            "🔹 *start* - Begin a new assessment\n" .
            "🔹 *plan* - View your current diet plan\n" .
            "🔹 *day [day]* - View a specific day's meal plan (e.g., 'day monday')\n" .
            "🔹 *recipe [meal] [day]* - View recipe for a specific meal\n" .
            "🔹 *progress* - View your progress\n" .
            "🔹 *checkin* - Submit daily check-in\n\n" .
            "📊 *Nutrition:* Get details about your meals\n\n" .
            "🛒 *Grocery List:* Manage your shopping list\n\n" .
            "📅 *Progress Tracking:* Log weight, meals, exercise, and water intake\n\n" .
            "🎯 *Goal Tracking:* Set and update your goals\n\n" .
            "🗓️ *Calendar:* Sync your meal plan to your calendar\n\n" .
            "💬 Reply anytime with your question, and I'll assist you!";

        // Ensure text does not exceed 1024 characters
        $maxLength = 1024;
        $bodyText = mb_strimwidth($bodyText, 0, $maxLength, "...");

        $interactive = [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'header' => [
                    'type' => 'image',
                    'image' => [
                        'id' => $this->helpMediaID
                    ]
                ],
                'body' => [
                    'text' => $bodyText
                ],
                'action' => [
                    'buttons' => [
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'view_plan',
                                'title' => '📜 View Plan'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'track_progress',
                                'title' => '📊 Track Progress'
                            ]
                        ],
                        [
                            'type' => 'reply',
                            'reply' => [
                                'id' => 'grocery_list',
                                'title' => '🛒 Grocery List'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->sendTextMessage($user->whatsapp_phone, '', $interactive);
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
                $payload = array_merge($payload, $interactive);
                // Ensure interactive message has a valid body text
                if (!isset($payload['interactive']['body']['text']) || empty(trim($payload['interactive']['body']['text']))) {
                    $payload['interactive']['body']['text'] = "📌 Here are your available commands:";
                }
            } else {
                // Ensure message is not empty and does not exceed 1024 characters
                $maxLength = 1024;
                $message = mb_strimwidth($message ?: "📌 No message content provided.", 0, $maxLength, "...");

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
                    'payload' => $payload,
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