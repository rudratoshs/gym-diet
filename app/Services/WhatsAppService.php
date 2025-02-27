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

    public function __construct()
    {
        $this->apiKey = config('services.whatsapp.api_key');
        $this->apiUrl = config('services.whatsapp.api_url');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
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
        $session->current_question = 'introduction';
        $session->responses = [];
        $session->status = 'in_progress';
        $session->started_at = now();
        $session->save();

        // Send welcome message
        $this->sendTextMessage($user->whatsapp_phone, "👋 Welcome to your personalized diet planning assistant! I'll ask a series of questions to understand your needs and create a tailored plan. Let's get started!");

        // Send first question (age and gender)
        $this->sendTextMessage($user->whatsapp_phone, "Please share your age:");

        // Update session
        $session->current_question = 'age';
        $session->save();

        return $session;
    }

    /**
     * Continue the assessment
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

        // Process response based on current question
        switch ($currentQuestion) {
            case 'age':
                // Validate age
                if (!is_numeric($response) || (int) $response < 12 || (int) $response > 120) {
                    $this->sendTextMessage($user->whatsapp_phone, "Please enter a valid age between 12 and 120:");
                    return $session;
                }

                $responses['age'] = (int) $response;

                // Ask gender
                $this->sendTextMessage($user->whatsapp_phone, "Please select your gender:", [
                    'type' => 'button',
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'male', 'title' => 'Male']],
                        ['type' => 'reply', 'reply' => ['id' => 'female', 'title' => 'Female']],
                        ['type' => 'reply', 'reply' => ['id' => 'other', 'title' => 'Other']]
                    ]
                ]);

                $session->current_question = 'gender';
                break;

            case 'gender':
                $validGenders = ['male', 'female', 'other'];
                $gender = strtolower($response);

                if (!in_array($gender, $validGenders)) {
                    $this->sendTextMessage($user->whatsapp_phone, "Please select a valid gender option:", [
                        'type' => 'button',
                        'buttons' => [
                            ['type' => 'reply', 'reply' => ['id' => 'male', 'title' => 'Male']],
                            ['type' => 'reply', 'reply' => ['id' => 'female', 'title' => 'Female']],
                            ['type' => 'reply', 'reply' => ['id' => 'other', 'title' => 'Other']]
                        ]
                    ]);
                    return $session;
                }

                $responses['gender'] = $gender;

                // Ask height
                $this->sendTextMessage($user->whatsapp_phone, "Please share your height (in cm or feet-inches, e.g., 175 or 5'9\"):");

                $session->current_question = 'height';
                break;

            case 'height':
                // Store height (will be converted later if needed)
                $responses['height'] = $response;

                // Ask weight
                $this->sendTextMessage($user->whatsapp_phone, "Please share your current weight (in kg or lbs):");

                $session->current_question = 'current_weight';
                break;

            case 'current_weight':
                // Store weight (will be converted later if needed)
                $responses['current_weight'] = $response;

                // Ask target weight
                $this->sendTextMessage($user->whatsapp_phone, "Please share your target weight (in kg or lbs), or type 'same' if you want to maintain your current weight:");

                $session->current_question = 'target_weight';
                break;

            case 'target_weight':
                if (strtolower($response) === 'same') {
                    $responses['target_weight'] = $responses['current_weight'];
                } else {
                    $responses['target_weight'] = $response;
                }

                // Ask activity level
                $this->sendTextMessage($user->whatsapp_phone, "Which best describes your activity level?", [
                    'type' => 'list',
                    'header' => ['type' => 'text', 'text' => 'Activity Level'],
                    'body' => ['text' => 'Select the option that best matches your typical week'],
                    'action' => ['button' => 'Select'],
                    'sections' => [
                        [
                            'title' => 'Activity Levels',
                            'rows' => [
                                ['id' => '1', 'title' => 'Sedentary', 'description' => 'Desk job, little exercise'],
                                ['id' => '2', 'title' => 'Lightly active', 'description' => 'Light exercise 1-3 days/week'],
                                ['id' => '3', 'title' => 'Moderately active', 'description' => 'Moderate exercise 3-5 days/week'],
                                ['id' => '4', 'title' => 'Very active', 'description' => 'Hard exercise 6-7 days/week'],
                                ['id' => '5', 'title' => 'Extremely active', 'description' => 'Physical job + intense exercise']
                            ]
                        ]
                    ]
                ]);

                $session->current_question = 'activity_level';
                break;

            // Continue with more questions following the assessment flow chart
            // This would include health conditions, dietary preferences, etc.

            // For brevity, I'm showing a simplified flow that jumps to diet type after activity level

            case 'activity_level':
                $responses['activity_level'] = $response;

                // Move to phase 3: Diet preferences
                $session->current_phase = 3;

                // Ask diet type
                $this->sendTextMessage($user->whatsapp_phone, "What type of diet do you follow?", [
                    'type' => 'list',
                    'header' => ['type' => 'text', 'text' => 'Diet Type'],
                    'body' => ['text' => 'Select your preferred eating style'],
                    'action' => ['button' => 'Select'],
                    'sections' => [
                        [
                            'title' => 'Diet Types',
                            'rows' => [
                                ['id' => '1', 'title' => 'Omnivore', 'description' => 'Eats everything'],
                                ['id' => '2', 'title' => 'Vegetarian', 'description' => 'No meat'],
                                ['id' => '3', 'title' => 'Vegan', 'description' => 'No animal products'],
                                ['id' => '4', 'title' => 'Pescatarian', 'description' => 'Vegetarian + seafood'],
                                ['id' => '5', 'title' => 'Flexitarian', 'description' => 'Mostly plant-based'],
                                ['id' => '6', 'title' => 'Keto', 'description' => 'Low carb, high fat'],
                                ['id' => '7', 'title' => 'Paleo', 'description' => 'Whole foods, no grains/dairy'],
                                ['id' => '8', 'title' => 'Other', 'description' => 'Custom diet']
                            ]
                        ]
                    ]
                ]);

                $session->current_question = 'diet_type';
                break;

            case 'diet_type':
                $responses['diet_type'] = $response;

                // For this example, let's complete the assessment here
                // In a full implementation, you would continue with more questions

                // Move to final phase: Plan creation
                $session->current_phase = 6;

                // Ask for plan type
                $this->sendTextMessage($user->whatsapp_phone, "Based on your inputs, I can now create your personalized plan. What would you like?", [
                    'type' => 'button',
                    'buttons' => [
                        ['type' => 'reply', 'reply' => ['id' => 'complete', 'title' => 'Complete Plan']],
                        ['type' => 'reply', 'reply' => ['id' => 'basic', 'title' => 'Basic Plan']],
                        ['type' => 'reply', 'reply' => ['id' => 'focus', 'title' => 'Focus on Diet']]
                    ]
                ]);

                $session->current_question = 'plan_type';
                break;

            case 'plan_type':
                $responses['plan_type'] = $response;

                // Complete assessment
                $this->sendTextMessage($user->whatsapp_phone, "Thank you for completing the assessment! I'm now generating your personalized diet plan. This may take a minute...");

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

                break;

            default:
                // Unknown question, restart assessment
                $this->sendTextMessage($user->whatsapp_phone, "Something went wrong with your assessment. Let's start over.");
                $session->status = 'abandoned';
                $session->save();

                return $this->startAssessment($user);
        }

        // Update session responses
        $session->responses = $responses;
        $session->save();

        return $session;
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
        $message .= "• Protein: *{$dietPlan->protein_grams}g* ({$dietPlan->macro_percentages['protein']}%)\n";
        $message .= "• Carbs: *{$dietPlan->carbs_grams}g* ({$dietPlan->macro_percentages['carbs']}%)\n";
        $message .= "• Fats: *{$dietPlan->fats_grams}g* ({$dietPlan->macro_percentages['fats']}%)\n\n";
        $message .= "I've created meal plans for every day of the week. Type 'plan' anytime to see your current plan or 'day' followed by the day (e.g., 'day monday') to see a specific day's meals.";

        $this->sendTextMessage($user->whatsapp_phone, $message);

        // Send first day's meal plan as an example
        $this->sendDayMealPlan($user, $dietPlan, 'monday');

        // Send follow-up schedule
        $this->sendTextMessage($user->whatsapp_phone, "I'll check in with you:\n• Daily for quick status updates\n• Weekly for detailed assessments\n• Monthly for plan adjustments\n\nType 'help' anytime to see available commands.");
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

        $message = "🍽️ *{$day}'s Meal Plan* 🍽️\n\n";

        $meals = $mealPlan->meals()->orderByRaw("FIELD(meal_type, 'breakfast', 'morning_snack', 'lunch', 'afternoon_snack', 'dinner', 'evening_snack')")->get();

        foreach ($meals as $meal) {
            $message .= "*{$meal->meal_type_display}* ({$meal->time_of_day})\n";
            $message .= "{$meal->title}\n";
            $message .= "{$meal->description}\n";
            $message .= "• Calories: {$meal->calories} kcal\n";
            $message .= "• Protein: {$meal->protein_grams}g | Carbs: {$meal->carbs_grams}g | Fats: {$meal->fats_grams}g\n\n";
        }

        $message .= "Total: {$mealPlan->total_calories} kcal | P: {$mealPlan->total_protein}g | C: {$mealPlan->total_carbs}g | F: {$mealPlan->total_fats}g\n\n";
        $message .= "To see recipe details, type 'recipe' followed by the meal type and day (e.g., 'recipe breakfast monday')";

        $this->sendTextMessage($user->whatsapp_phone, $message);
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
        $user->password = bcrypt(Str::random(16)); // Use Str::random() instead
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
}
