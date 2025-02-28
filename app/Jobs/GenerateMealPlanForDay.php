<?php
// app/Jobs/GenerateMealPlanForDay.php
namespace App\Jobs;

use App\Models\DietPlan;
use App\Models\MealPlan;
use App\Models\ClientProfile;
use App\Models\Meal;
use App\Services\AI\GeminiService;
use App\Services\AIServiceFactory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMealPlanForDay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $mealPlanId;     // Changed to public
    public $dietPlanId;     // Changed to public
    public $profileId;      // Changed to public
    public $responses;      // Changed to public
    public $preferences;    // Changed to public
    public $day;            // Changed to public

    /**
     * Retry configuration
     */
    public $tries = 3;
    public $backoff = [60, 180, 600]; // Retry after 1m, 3m, 10m
    public $timeout = 90; // 90 seconds timeout

    /**
     * Create a new job instance.
     */
    public function __construct($mealPlanId, $dietPlanId, $profileId, $responses, $preferences, $day)
    {
        $this->mealPlanId = $mealPlanId;
        $this->dietPlanId = $dietPlanId;
        $this->profileId = $profileId;
        $this->responses = $responses;
        $this->preferences = $preferences;
        $this->day = $day;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            Log::info('Processing meal plan generation job', [
                'meal_plan_id' => $this->mealPlanId,
                'day' => $this->day
            ]);

            // Get required models
            $mealPlan = MealPlan::findOrFail($this->mealPlanId);
            $dietPlan = DietPlan::findOrFail($this->dietPlanId);
            $profile = ClientProfile::findOrFail($this->profileId);

            // Get appropriate AI service
            $user = $profile->user;
            $gym = $user ? $user->gyms()->first() : null;
            $aiService = AIServiceFactory::create($gym);

            // Update meal plan status
            $mealPlan->generation_status = 'in_progress';
            $mealPlan->save();

            // Generate meals for this day
            $aiService->generateMealPlans($dietPlan, $this->responses, [
                'profile' => $profile,
                'health_conditions' => $profile->health_conditions ?? ['none'],
                'allergies' => $profile->allergies ?? ['none'],
                'recovery_needs' => $profile->recovery_needs ?? ['none'],
                'meal_preferences' => $profile->meal_preferences ?? ['balanced'],
                'cuisine_preferences' => $profile->cuisine_preferences ?? ['no_preference'],
                'day' => $this->day,
                'meal_plan_id' => $this->mealPlanId,
            ]);

            // Update status
            $mealPlan->generation_status = 'completed';
            $mealPlan->calculateTotals();
            $mealPlan->save();

            Log::info('Meal plan generation job completed successfully', [
                'meal_plan_id' => $this->mealPlanId,
                'day' => $this->day
            ]);
        } catch (\Exception $e) {
            Log::error('Meal plan generation job failed', [
                'meal_plan_id' => $this->mealPlanId,
                'day' => $this->day,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw to trigger job retry if tries are available
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception)
    {
        // Log the failure
        Log::error('Meal plan generation job failed permanently', [
            'meal_plan_id' => $this->mealPlanId,
            'day' => $this->day,
            'error' => $exception->getMessage()
        ]);

        // Try to load the meal plan
        try {
            $mealPlan = MealPlan::find($this->mealPlanId);
            $dietPlan = DietPlan::find($this->dietPlanId);

            // If meal plan exists but has no meals, add default meals
            if ($mealPlan && $dietPlan && $mealPlan->meals()->count() === 0) {
                // Add default meals as fallback
                $this->createDefaultMeals($mealPlan->id, $dietPlan);

                // Update status
                $mealPlan->generation_status = 'failed';
                $mealPlan->save();

                Log::info('Default meals created after job failure', [
                    'meal_plan_id' => $this->mealPlanId,
                    'day' => $this->day
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to create default meals after job failure', [
                'meal_plan_id' => $this->mealPlanId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create default meals for a meal plan
     * (Copied from BaseAIService to avoid dependency issues)
     */
    private function createDefaultMeals($mealPlanId, $dietPlan)
    {
        $defaultMeals = [
            [
                'meal_type' => 'breakfast',
                'title' => 'Oatmeal with Fruits',
                'description' => 'Rolled oats cooked with almond milk, topped with fruits and honey.',
                'calories' => round($dietPlan->daily_calories * 0.25),
                'protein_grams' => round($dietPlan->protein_grams * 0.2),
                'carbs_grams' => round($dietPlan->carbs_grams * 0.3),
                'fats_grams' => round($dietPlan->fats_grams * 0.15),
                'time_of_day' => '08:00',
                'recipes' => json_encode([
                    'ingredients' => [
                        '1/2 cup rolled oats',
                        '1 cup milk',
                        '1 tbsp honey',
                        '1/2 cup mixed fruits'
                    ],
                    'instructions' => [
                        'Cook oats with milk. Top with fruits and honey.'
                    ]
                ])
            ],
            [
                'meal_type' => 'lunch',
                'title' => 'Quinoa Salad with Vegetables',
                'description' => 'Quinoa mixed with fresh vegetables and olive oil dressing.',
                'calories' => round($dietPlan->daily_calories * 0.35),
                'protein_grams' => round($dietPlan->protein_grams * 0.4),
                'carbs_grams' => round($dietPlan->carbs_grams * 0.35),
                'fats_grams' => round($dietPlan->fats_grams * 0.35),
                'time_of_day' => '13:00',
                'recipes' => json_encode([
                    'ingredients' => [
                        '1 cup cooked quinoa',
                        '1 cup mixed vegetables',
                        '2 tbsp olive oil',
                        'Salt and pepper to taste'
                    ],
                    'instructions' => [
                        'Mix quinoa with chopped vegetables and olive oil. Season to taste.'
                    ]
                ])
            ],
            [
                'meal_type' => 'dinner',
                'title' => 'Baked Fish with Steamed Vegetables',
                'description' => 'Baked white fish fillet with a side of steamed seasonal vegetables.',
                'calories' => round($dietPlan->daily_calories * 0.4),
                'protein_grams' => round($dietPlan->protein_grams * 0.4),
                'carbs_grams' => round($dietPlan->carbs_grams * 0.35),
                'fats_grams' => round($dietPlan->fats_grams * 0.5),
                'time_of_day' => '19:00',
                'recipes' => json_encode([
                    'ingredients' => [
                        '150g white fish fillet',
                        '2 cups mixed vegetables',
                        '1 tbsp olive oil',
                        'Lemon, herbs and spices'
                    ],
                    'instructions' => [
                        'Season fish and bake at 180°C for 15-20 minutes. Steam vegetables until tender.'
                    ]
                ])
            ]
        ];

        foreach ($defaultMeals as $mealData) {
            Meal::create([
                'meal_plan_id' => $mealPlanId,
                'meal_type' => $mealData['meal_type'],
                'title' => $mealData['title'],
                'description' => $mealData['description'],
                'calories' => $mealData['calories'],
                'protein_grams' => $mealData['protein_grams'],
                'carbs_grams' => $mealData['carbs_grams'],
                'fats_grams' => $mealData['fats_grams'],
                'time_of_day' => $mealData['time_of_day'],
                'recipes' => $mealData['recipes']
            ]);
        }

        // Update the meal plan with calculated totals
        $mealPlan = MealPlan::findOrFail($mealPlanId);
        $mealPlan->calculateTotals();
    }
}