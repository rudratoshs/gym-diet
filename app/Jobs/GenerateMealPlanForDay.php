<?php
// app/Jobs/GenerateMealPlanForDay.php
namespace App\Jobs;

use App\Models\DietPlan;
use App\Models\MealPlan;
use App\Models\ClientProfile;
use App\Services\AI\GeminiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateMealPlanForDay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $mealPlanId;
    protected $dietPlanId;
    protected $profileId;
    protected $responses;
    protected $preferences;
    protected $day;

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

            // Create GeminiService instance
            $geminiService = app(GeminiService::class);

            // Generate meals for this day
            $geminiService->generateMealsForDay(
                $mealPlan,
                $dietPlan,
                $profile,
                $this->responses,
                $this->preferences,
                $this->day
            );

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
}