<?php
namespace App\Services;

use App\Models\DailyProgress;
use App\Models\DietPlan;
use App\Models\GoalTracking;
use App\Models\MealCompliance;
use App\Models\ProgressTracking;
use App\Models\CalendarEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppProgressTrackingService
{
    public function getWhatsAppService()
    {
        return app(WhatsAppService::class);
    }
    /**
     * Process a progress tracking command
     * 
     * @param User $user The user issuing the command
     * @param string $command The command text
     * @return string|null Response message or null if no specific response
     */
    public function processProgressCommand(User $user, string $command): ?string
    {
        $commandParts = explode(' ', strtolower(trim($command)), 3);
        $action = $commandParts[0] ?? '';
        $parameter1 = $commandParts[1] ?? '';
        $parameter2 = $commandParts[2] ?? '';

        switch ($action) {
            case 'progress':
                return $this->handleProgressReport($user, $parameter1);

            case 'checkin':
            case 'check-in':
                return $this->handleDailyCheckin($user);

            case 'water':
                return $this->trackWaterIntake($user, $parameter1);

            case 'weight':
                return $this->trackWeight($user, $parameter1);

            case 'meal':
                if ($parameter1 === 'done' || $parameter1 === 'completed') {
                    return $this->trackMealCompliance($user, $parameter2);
                }
                break;

            case 'exercise':
            case 'workout':
                if ($parameter1 === 'done' || $parameter1 === 'completed') {
                    return $this->trackExerciseCompletion($user, $parameter2);
                }
                break;

            case 'mood':
            case 'energy':
                return $this->trackWellbeing($user, $action, $parameter1);

            case 'goal':
                if ($parameter1 === 'update') {
                    return $this->updateGoal($user, $parameter2);
                } elseif ($parameter1 === 'new' || $parameter1 === 'add') {
                    return $this->createGoal($user, $parameter2);
                } else {
                    return $this->viewGoals($user);
                }
        }

        return null;
    }

    /**
     * Handle generating a progress report for the user
     */
    protected function handleProgressReport(User $user, string $period = 'week'): string
    {
        $now = Carbon::now();

        switch ($period) {
            case 'day':
            case 'today':
                $startDate = $now->startOfDay();
                $endDate = $now->copy()->endOfDay();
                $title = "Today's Progress";
                break;

            case 'week':
                $startDate = $now->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
                $title = "This Week's Progress";
                break;

            case 'month':
                $startDate = $now->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                $title = "This Month's Progress";
                break;

            case 'overall':
            case 'all':
                // Last 90 days as "overall"
                $startDate = $now->subDays(90);
                $endDate = Carbon::now();
                $title = "Overall Progress (Last 90 Days)";
                break;

            default:
                $startDate = $now->startOfWeek();
                $endDate = $now->copy()->endOfWeek();
                $title = "This Week's Progress";
        }

        // Get progress data
        $progressEntries = ProgressTracking::where('client_id', $user->id)
            ->whereBetween('tracking_date', [$startDate, $endDate])
            ->orderBy('tracking_date')
            ->get();

        $dailyEntries = DailyProgress::where('user_id', $user->id)
            ->whereBetween('tracking_date', [$startDate, $endDate])
            ->orderBy('tracking_date')
            ->get();

        // Get active goals
        $goals = GoalTracking::where('user_id', $user->id)
            ->whereIn('status', ['in_progress', 'achieved'])
            ->get();

        // Build the report
        $message = "📈 *{$title}* 📈\n\n";

        // Weight progress if available
        if ($progressEntries->count() > 0) {
            $firstEntry = $progressEntries->first();
            $lastEntry = $progressEntries->last();

            if (isset($firstEntry->weight) && isset($lastEntry->weight)) {
                $weightChange = $lastEntry->weight - $firstEntry->weight;
                $weightDirection = $weightChange <= 0 ? "lost" : "gained";
                $weightChange = abs($weightChange);

                $message .= "*Weight:* ";
                if ($weightChange > 0) {
                    $message .= "You've {$weightDirection} {$weightChange} kg\n";
                    $message .= "Starting: {$firstEntry->weight} kg → Current: {$lastEntry->weight} kg\n\n";
                } else {
                    $message .= "No change. Current: {$lastEntry->weight} kg\n\n";
                }
            }
        }

        // Compliance stats if available
        if ($dailyEntries->count() > 0) {
            $totalMealsCompleted = $dailyEntries->sum('meals_completed');
            $totalMealsPlanned = $dailyEntries->sum('total_meals');
            $totalWaterIntake = $dailyEntries->sum('water_intake');
            $exerciseDays = $dailyEntries->where('exercise_done', true)->count();

            $message .= "*Compliance Stats:*\n";

            if ($totalMealsPlanned > 0) {
                $mealCompliancePercent = round(($totalMealsCompleted / $totalMealsPlanned) * 100);
                $message .= "• Meal Plan: {$mealCompliancePercent}% adherence\n";
            }

            $message .= "• Water Intake: " . round($totalWaterIntake / $dailyEntries->count()) . " ml daily avg\n";
            $message .= "• Exercise: {$exerciseDays} days completed\n\n";
        }

        // Goals progress
        if ($goals->count() > 0) {
            $message .= "*Goals Progress:*\n";

            foreach ($goals as $goal) {
                $status = ($goal->status === 'achieved') ? "✅" : "🔄";
                $progress = round($goal->progress_percentage);
                $message .= "{$status} {$goal->goal_type}: {$progress}% complete\n";

                if ($goal->status === 'in_progress') {
                    $message .= "   {$goal->current_value}{$goal->unit} → Target: {$goal->target_value}{$goal->unit}\n";
                }
            }

            $message .= "\n";
        }

        // Recent activities
        if ($dailyEntries->count() > 0) {
            $recentEntries = $dailyEntries->sortByDesc('tracking_date')->take(3);

            $message .= "*Recent Activities:*\n";

            foreach ($recentEntries as $entry) {
                $date = Carbon::parse($entry->tracking_date)->format('D, M j');
                $message .= "• {$date}: ";

                if ($entry->meals_completed > 0) {
                    $message .= "{$entry->meals_completed}/{$entry->total_meals} meals, ";
                }

                if ($entry->water_intake > 0) {
                    $message .= "{$entry->water_intake} ml water, ";
                }

                if ($entry->exercise_done) {
                    $message .= "exercise completed";
                } else {
                    $message .= "no exercise";
                }

                $message .= "\n";
            }

            $message .= "\n";
        }

        // Coaching tips based on compliance
        $message .= "*Coaching Tips:*\n";

        if ($dailyEntries->count() > 0) {
            $averageMealCompliance = $totalMealsPlanned > 0 ?
                ($totalMealsCompleted / $totalMealsPlanned) : 0;
            $averageWaterIntake = $dailyEntries->count() > 0 ?
                ($totalWaterIntake / $dailyEntries->count()) : 0;
            $exerciseCompliance = $dailyEntries->count() > 0 ?
                ($exerciseDays / $dailyEntries->count()) : 0;

            // Add specific tips based on compliance
            if ($averageMealCompliance < 0.7) {
                $message .= "• Try meal prepping on weekends to improve your meal plan adherence.\n";
            }

            if ($averageWaterIntake < 2000) {
                $message .= "• Consider setting water reminders to reach your daily hydration goals.\n";
            }

            if ($exerciseCompliance < 0.5) {
                $message .= "• Schedule short 15-min workouts to build consistency in your exercise routine.\n";
            }
        } else {
            $message .= "• Start tracking daily to get personalized coaching tips.\n";
        }

        // Call to action
        $message .= "\nType 'checkin' to log today's progress, or 'help' to see all tracking commands.";

        return $message;
    }

    /**
     * Handle daily check-in command
     */
    protected function handleDailyCheckin(User $user): string
    {
        // First, check if a check-in was already done today
        $today = Carbon::today()->format('Y-m-d');
        $existingEntry = DailyProgress::where('user_id', $user->id)
            ->where('tracking_date', $today)
            ->first();

        if ($existingEntry) {
            // Update existing daily progress
            $this->getWhatsAppService()->sendTextMessage(
                $user->whatsapp_phone,
                "You've already started a check-in today. Let's continue tracking your progress."
            );
        } else {
            // Create new daily progress entry
            $dailyProgress = new DailyProgress();
            $dailyProgress->user_id = $user->id;
            $dailyProgress->tracking_date = $today;
            $dailyProgress->meals_completed = 0;

            // Calculate total meals from diet plan
            $dietPlan = DietPlan::where('client_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            if ($dietPlan) {
                // Get today's day of week
                $dayOfWeek = strtolower(Carbon::today()->format('l'));
                $mealPlan = $dietPlan->mealPlans()
                    ->where('day_of_week', $dayOfWeek)
                    ->first();

                if ($mealPlan) {
                    $totalMeals = $mealPlan->meals()->count();
                    $dailyProgress->total_meals = $totalMeals;
                } else {
                    $dailyProgress->total_meals = 3; // Default if no meal plan
                }
            } else {
                $dailyProgress->total_meals = 3; // Default if no diet plan
            }

            $dailyProgress->save();

            // Start interactive check-in process
            $this->getWhatsAppService()->sendTextMessage(
                $user->whatsapp_phone,
                "📝 *Daily Check-in: " . Carbon::today()->format('D, M j') . "* 📝\n\n" .
                "Let's track your progress for today! I'll ask a few quick questions."
            );
        }

        // Begin interactive water tracking
        $this->startWaterTracking($user);

        return ''; // No immediate response since we're starting an interactive flow
    }

    /**
     * Start water tracking interaction
     */
    protected function startWaterTracking(User $user): void
    {
        $this->getWhatsAppService()->sendTextMessage(
            $user->whatsapp_phone,
            "💧 *Water Intake*\n\n" .
            "How much water have you had today? (in glasses or ml)",
            [
                'type' => 'button',
                'buttons' => [
                    ['type' => 'reply', 'reply' => ['id' => 'water_1', 'title' => '1-2 glasses']],
                    ['type' => 'reply', 'reply' => ['id' => 'water_2', 'title' => '3-5 glasses']],
                    ['type' => 'reply', 'reply' => ['id' => 'water_3', 'title' => '6+ glasses']],
                ]
            ]
        );
    }

    /**
     * Track water intake
     */
    protected function trackWaterIntake(User $user, string $amount): string
    {
        $today = Carbon::today()->format('Y-m-d');
        $dailyProgress = DailyProgress::where('user_id', $user->id)
            ->where('tracking_date', $today)
            ->first();

        if (!$dailyProgress) {
            // Create new entry if none exists
            $dailyProgress = new DailyProgress();
            $dailyProgress->user_id = $user->id;
            $dailyProgress->tracking_date = $today;
            $dailyProgress->meals_completed = 0;
            $dailyProgress->total_meals = 3;
        }

        // Parse water intake amount
        $waterIntake = 0;

        // Handle button responses
        if ($amount === 'water_1') {
            $waterIntake = 500; // ~2 glasses
        } elseif ($amount === 'water_2') {
            $waterIntake = 1000; // ~4 glasses
        } elseif ($amount === 'water_3') {
            $waterIntake = 1500; // ~6 glasses
        } else {
            // Try to parse numeric amount
            if (is_numeric($amount)) {
                $waterIntake = (int) $amount;

                // Check if it's likely glasses or ml
                if ($waterIntake < 20) {
                    $waterIntake *= 250; // Convert glasses to ml
                }
            } elseif (preg_match('/(\d+)\s*glass/', $amount, $matches)) {
                $waterIntake = (int) $matches[1] * 250; // Convert glasses to ml
            }
        }

        if ($waterIntake > 0) {
            $dailyProgress->water_intake = $waterIntake;
            $dailyProgress->save();

            // If this is part of a check-in flow, continue to meal tracking
            if (in_array($amount, ['water_1', 'water_2', 'water_3'])) {
                $this->startMealTracking($user);
                return ""; // No response needed, continuing flow
            }

            return "💧 Great! I've recorded your water intake as " . ($waterIntake >= 1000 ? ($waterIntake / 1000) . " liters" : $waterIntake . " ml") . ".";
        }

        return "I couldn't understand that water amount. Please enter a number of glasses or milliliters (e.g., '8 glasses' or '2000 ml').";
    }

    /**
     * Start meal tracking interaction
     */
    protected function startMealTracking(User $user): void
    {
        $today = Carbon::today()->format('Y-m-d');
        $dailyProgress = DailyProgress::where('user_id', $user->id)
            ->where('tracking_date', $today)
            ->first();

        if (!$dailyProgress) {
            return; // Should not happen
        }

        $totalMeals = $dailyProgress->total_meals;

        $this->getWhatsAppService()->sendTextMessage(
            $user->whatsapp_phone,
            "🍽️ *Meal Plan Compliance*\n\n" .
            "How many of your planned meals have you completed today? (out of {$totalMeals})",
            [
                'type' => 'button',
                'buttons' => [
                    ['type' => 'reply', 'reply' => ['id' => 'meals_all', 'title' => 'All Meals']],
                    ['type' => 'reply', 'reply' => ['id' => 'meals_some', 'title' => 'Some Meals']],
                    ['type' => 'reply', 'reply' => ['id' => 'meals_none', 'title' => 'None Yet']],
                ]
            ]
        );
    }

    /**
     * Track meal compliance
     */
    protected function trackMealCompliance(User $user, string $mealInfo): string
    {
        $today = Carbon::today()->format('Y-m-d');
        $dailyProgress = DailyProgress::where('user_id', $user->id)
            ->where('tracking_date', $today)
            ->first();

        if (!$dailyProgress) {
            // Create new entry if none exists
            $dailyProgress = new DailyProgress();
            $dailyProgress->user_id = $user->id;
            $dailyProgress->tracking_date = $today;
            $dailyProgress->water_intake = 0;
            $dailyProgress->total_meals = 3;
        }

        $totalMeals = $dailyProgress->total_meals;
        $mealsCompleted = 0;

        // Handle button responses
        if ($mealInfo === 'meals_all') {
            $mealsCompleted = $totalMeals;
        } elseif ($mealInfo === 'meals_none') {
            $mealsCompleted = 0;
        } elseif ($mealInfo === 'meals_some') {
            // Will ask for specific number in next message
            $this->getWhatsAppService()->sendTextMessage(
                $user->whatsapp_phone,
                "How many meals have you completed so far? (1-{$totalMeals})"
            );
            return "";
        } elseif (is_numeric($mealInfo) && (int) $mealInfo >= 0 && (int) $mealInfo <= $totalMeals) {
            $mealsCompleted = (int) $mealInfo;
        } else {
            return "Please provide a valid number of meals between 0 and {$totalMeals}.";
        }

        $dailyProgress->meals_completed = $mealsCompleted;
        $dailyProgress->save();

        // If this is part of a check-in flow, continue to exercise tracking
        if (in_array($mealInfo, ['meals_all', 'meals_none']) || is_numeric($mealInfo)) {
            $this->startExerciseTracking($user);
            return ""; // No response needed, continuing flow
        }

        $compliance = round(($mealsCompleted / $totalMeals) * 100);
        return "🍽️ Great! I've recorded your meal compliance as {$mealsCompleted}/{$totalMeals} meals ({$compliance}%).";
    }

    /**
     * Start exercise tracking interaction
     */
    protected function startExerciseTracking(User $user): void
    {
        $this->getWhatsAppService()->sendTextMessage(
            $user->whatsapp_phone,
            "💪 *Exercise*\n\n" .
            "Have you completed your exercise today?",
            [
                'type' => 'button',
                'buttons' => [
                    ['type' => 'reply', 'reply' => ['id' => 'exercise_yes', 'title' => 'Yes']],
                    ['type' => 'reply', 'reply' => ['id' => 'exercise_no', 'title' => 'Not Yet']],
                    ['type' => 'reply', 'reply' => ['id' => 'exercise_rest', 'title' => 'Rest Day']],
                ]
            ]
        );
    }

    /**
     * Track exercise completion
     */
    protected function trackExerciseCompletion(User $user, string $status): string
    {
        $today = Carbon::today()->format('Y-m-d');
        $dailyProgress = DailyProgress::where('user_id', $user->id)
            ->where('tracking_date', $today)
            ->first();

        if (!$dailyProgress) {
            // Create new entry if none exists
            $dailyProgress = new DailyProgress();
            $dailyProgress->user_id = $user->id;
            $dailyProgress->tracking_date = $today;
            $dailyProgress->water_intake = 0;
            $dailyProgress->meals_completed = 0;
            $dailyProgress->total_meals = 3;
        }

        // Parse exercise status
        if ($status === 'yes' || $status === 'exercise_yes') {
            $dailyProgress->exercise_done = true;
            $dailyProgress->exercise_duration = 30; // Default assumption
        } elseif ($status === 'no' || $status === 'exercise_no') {
            $dailyProgress->exercise_done = false;
        } elseif ($status === 'rest' || $status === 'exercise_rest') {
            $dailyProgress->exercise_done = true;
            $dailyProgress->notes = isset($dailyProgress->notes) ?
                $dailyProgress->notes . " Rest day." : "Rest day.";
        } elseif (is_numeric($status)) {
            $dailyProgress->exercise_done = true;
            $dailyProgress->exercise_duration = (int) $status;
        }

        $dailyProgress->save();

        // If this is part of a check-in flow, continue to mood tracking
        if (in_array($status, ['exercise_yes', 'exercise_no', 'exercise_rest'])) {
            $this->startMoodTracking($user);
            return ""; // No response needed, continuing flow
        }

        return "💪 Your exercise status has been updated.";
    }

    /**
     * Start mood tracking interaction
     */
    protected function startMoodTracking(User $user): void
    {
        $this->getWhatsAppService()->sendTextMessage(
            $user->whatsapp_phone,
            "😊 *Energy & Mood*\n\n" .
            "How are you feeling today?",
            [
                'type' => 'button',
                'buttons' => [
                    ['type' => 'reply', 'reply' => ['id' => 'mood_great', 'title' => 'Great']],
                    ['type' => 'reply', 'reply' => ['id' => 'mood_good', 'title' => 'Good']],
                    ['type' => 'reply', 'reply' => ['id' => 'mood_low', 'title' => 'Low Energy']],
                ]
            ]
        );
    }

    /**
     * Track wellbeing (mood/energy)
     */
    protected function trackWellbeing(User $user, string $type, string $status): string
    {
        $today = Carbon::today()->format('Y-m-d');
        $dailyProgress = DailyProgress::where('user_id', $user->id)
            ->where('tracking_date', $today)
            ->first();

        if (!$dailyProgress) {
            // Create new entry if none exists
            $dailyProgress = new DailyProgress();
            $dailyProgress->user_id = $user->id;
            $dailyProgress->tracking_date = $today;
            $dailyProgress->water_intake = 0;
            $dailyProgress->meals_completed = 0;
            $dailyProgress->total_meals = 3;
            $dailyProgress->exercise_done = false;
        }

        // Map status values
        $energyValue = 'moderate';
        $moodValue = 'good';

        // Handle different input options
        if ($status === 'mood_great' || $status === 'high' || $status === 'excellent') {
            $energyValue = 'high';
            $moodValue = 'excellent';
        } elseif ($status === 'mood_good' || $status === 'moderate' || $status === 'good') {
            $energyValue = 'moderate';
            $moodValue = 'good';
        } elseif ($status === 'mood_low' || $status === 'low' || $status === 'poor') {
            $energyValue = 'low';
            $moodValue = 'fair';
        }

        // Set the appropriate field based on type
        if ($type === 'energy') {
            $dailyProgress->energy_level = $energyValue;
        } else { // mood
            $dailyProgress->mood = $moodValue;
        }

        $dailyProgress->save();

        // If this is part of a check-in flow, complete the check-in
        if (in_array($status, ['mood_great', 'mood_good', 'mood_low'])) {
            return $this->completeCheckin($user);
        }

        return "😊 Thanks for sharing how you're feeling today. Your wellness data has been updated.";
    }

    /**
     * Complete the check-in flow
     */
    protected function completeCheckin(User $user): string
    {
        $today = Carbon::today()->format('Y-m-d');
        $dailyProgress = DailyProgress::where('user_id', $user->id)
            ->where('tracking_date', $today)
            ->first();

        if (!$dailyProgress) {
            return "There was an issue with your check-in. Please try again by typing 'checkin'.";
        }

        // Calculate compliance percentage
        $mealCompliance = $dailyProgress->total_meals > 0 ?
            round(($dailyProgress->meals_completed / $dailyProgress->total_meals) * 100) : 0;

        // Format water intake for display
        $waterDisplay = $dailyProgress->water_intake >= 1000 ?
            ($dailyProgress->water_intake / 1000) . " liters" :
            $dailyProgress->water_intake . " ml";

        // Create summary message
        $message = "✅ *Check-in Complete!* ✅\n\n";
        $message .= "Thanks for completing your daily check-in. Here's a summary:\n\n";
        $message .= "💧 Water: {$waterDisplay}\n";
        $message .= "🍽️ Meals: {$dailyProgress->meals_completed}/{$dailyProgress->total_meals} ({$mealCompliance}%)\n";
        $message .= "💪 Exercise: " . ($dailyProgress->exercise_done ? "Completed" : "Not yet") . "\n";

        if (isset($dailyProgress->energy_level)) {
            $message .= "⚡ Energy: " . ucfirst($dailyProgress->energy_level) . "\n";
        }

        if (isset($dailyProgress->mood)) {
            $message .= "😊 Mood: " . ucfirst($dailyProgress->mood) . "\n";
        }

        // Weekly completion tracking
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');
        $weekEnd = Carbon::now()->endOfWeek()->format('Y-m-d');

        $weeklyCheckIns = DailyProgress::where('user_id', $user->id)
            ->whereBetween('tracking_date', [$weekStart, $weekEnd])
            ->count();

        $dayOfWeek = Carbon::now()->dayOfWeek;
        $daysSoFar = min($dayOfWeek + 1, 7); // +1 because dayOfWeek is 0-indexed

        $message .= "\n📊 *Weekly Completion*\n";
        $message .= "You've checked in {$weeklyCheckIns}/{$daysSoFar} days this week.\n";

        // Personalized tip based on current progress
        $message .= "\n💡 *Tip for Today*\n";

        if ($dailyProgress->water_intake < 2000) {
            $message .= "Try to increase your water intake to at least 2 liters daily for optimal hydration.\n";
        } elseif (!$dailyProgress->exercise_done) {
            $message .= "Even a short 10-minute workout can boost your energy and metabolism.\n";
        } elseif ($dailyProgress->meals_completed < $dailyProgress->total_meals) {
            $message .= "Staying on track with your meal plan will help you reach your nutrition goals faster.\n";
        } else {
            $message .= "Great job staying on track today! Consistency is key to long-term success.\n";
        }

        $message .= "\nType 'progress' anytime to see your overall stats.";

        // Update or create a weekly progress entry
        $this->updateWeeklyProgress($user);

        return $message;
    }

    /**
     * Update or create weekly progress tracking
     */
    protected function updateWeeklyProgress(User $user): void
    {
        $weekStart = Carbon::now()->startOfWeek()->format('Y-m-d');

        // Check if a weekly progress entry exists
        $weeklyProgress = ProgressTracking::where('client_id', $user->id)
            ->where('tracking_date', $weekStart)
            ->first();

        if (!$weeklyProgress) {
            // Create new weekly progress
            $weeklyProgress = new ProgressTracking();
            $weeklyProgress->client_id = $user->id;
            $weeklyProgress->tracking_date = $weekStart;
        }

        // Get daily entries for this week
        $dailyEntries = DailyProgress::where('user_id', $user->id)
            ->whereBetween('tracking_date', [
                $weekStart,
                Carbon::now()->endOfWeek()->format('Y-m-d')
            ])
            ->get();

        if ($dailyEntries->isEmpty()) {
            return;
        }

        // Calculate averages and aggregates
        $totalWaterIntake = $dailyEntries->sum('water_intake');
        $daysWithExercise = $dailyEntries->where('exercise_done', true)->count();
        $totalMealsCompleted = $dailyEntries->sum('meals_completed');
        $totalMealsPlanned = $dailyEntries->sum('total_meals');

        // Average calculations
        $avgWaterIntake = round($totalWaterIntake / $dailyEntries->count());
        $mealComplianceRate = $totalMealsPlanned > 0 ?
            round(($totalMealsCompleted / $totalMealsPlanned) * 100) : 0;
        $exerciseComplianceRate = round(($daysWithExercise / $dailyEntries->count()) * 100);

        // Update weekly progress with aggregated data
        $weeklyProgress->energy_level = $mealComplianceRate >= 80 && $exerciseComplianceRate >= 70 ? 10 :
            ($mealComplianceRate >= 60 && $exerciseComplianceRate >= 50 ? 8 : 6);

        $weeklyProgress->meal_compliance = $mealComplianceRate;
        $weeklyProgress->water_intake = $avgWaterIntake;
        $weeklyProgress->exercise_compliance = $exerciseComplianceRate;

        // Get latest weight if available
        $latestDailyWithWeight = ProgressTracking::where('client_id', $user->id)
            ->whereNotNull('weight')
            ->latest('tracking_date')
            ->first();

        if ($latestDailyWithWeight) {
            $weeklyProgress->weight = $latestDailyWithWeight->weight;
        }

        $weeklyProgress->save();
    }

    /**
     * Track user's weight
     */
    protected function trackWeight(User $user, string $weightInput): string
    {
        // Validate weight input
        if (!is_numeric($weightInput) || (float) $weightInput <= 0 || (float) $weightInput > 300) {
            return "Please provide a valid weight in kg (e.g., '65.5').";
        }

        $weight = (float) $weightInput;
        $today = Carbon::today()->format('Y-m-d');

        // Create progress tracking entry
        $progressEntry = ProgressTracking::firstOrNew([
            'client_id' => $user->id,
            'tracking_date' => $today
        ]);

        $progressEntry->weight = $weight;
        $progressEntry->save();

        // Also update goal tracking if weight goals exist
        $this->updateWeightGoal($user, $weight);

        // Get previous weight for comparison
        $previousWeight = ProgressTracking::where('client_id', $user->id)
            ->where('tracking_date', '<', $today)
            ->whereNotNull('weight')
            ->orderBy('tracking_date', 'desc')
            ->first();

        $message = "⚖️ Weight recorded: {$weight} kg";

        if ($previousWeight) {
            $difference = $weight - $previousWeight->weight;
            $trend = $difference < 0 ? "lost" : "gained";
            $difference = abs($difference);

            if ($difference >= 0.1) {
                $message .= "\nYou've {$trend} {$difference} kg since your last weigh-in.";
            } else {
                $message .= "\nYour weight is stable since your last weigh-in.";
            }
        }

        return $message;
    }

    /**
     * Update weight-related goals
     */
    protected function updateWeightGoal(User $user, float $currentWeight): void
    {
        // Find active weight goals
        $weightGoal = GoalTracking::where('user_id', $user->id)
            ->where('goal_type', 'weight')
            ->where('status', 'in_progress')
            ->first();

        if (!$weightGoal) {
            return;
        }

        // Update current value
        $weightGoal->current_value = $currentWeight;

        // Calculate progress percentage
        $totalChange = $weightGoal->target_value - $weightGoal->starting_value;
        $currentChange = $currentWeight - $weightGoal->starting_value;

        // Handle different goal directions (weight loss vs gain)
        if ($totalChange != 0) {
            $progressPercent = ($currentChange / $totalChange) * 100;

            // Cap at 100% for progress beyond goal
            if (
                ($totalChange < 0 && $progressPercent > 100) ||
                ($totalChange > 0 && $progressPercent > 100)
            ) {
                $progressPercent = 100;
            }

            $weightGoal->progress_percentage = $progressPercent;
        }

        // Check if goal is achieved
        if (
            ($totalChange < 0 && $currentWeight <= $weightGoal->target_value) ||
            ($totalChange > 0 && $currentWeight >= $weightGoal->target_value)
        ) {
            $weightGoal->status = 'achieved';
        }

        $weightGoal->save();
    }

    /**
     * View user's active goals
     */
    protected function viewGoals(User $user): string
    {
        $goals = GoalTracking::where('user_id', $user->id)
            ->whereIn('status', ['in_progress', 'achieved'])
            ->get();

        if ($goals->isEmpty()) {
            return "You don't have any active goals yet. Type 'goal new [description]' to create one.";
        }

        $message = "🎯 *Your Goals* 🎯\n\n";

        foreach ($goals as $goal) {
            $status = $goal->status === 'achieved' ? '✅' : '🔄';
            $progress = round($goal->progress_percentage);
            $targetDate = Carbon::parse($goal->target_date)->format('M j, Y');

            $message .= "{$status} *{$this->formatGoalType($goal->goal_type)}*: {$progress}% complete\n";
            $message .= "  {$goal->current_value}{$goal->unit} → Target: {$goal->target_value}{$goal->unit}\n";

            if ($goal->status === 'in_progress') {
                $message .= "  Target date: {$targetDate}\n";
            }

            $message .= "  \"{$goal->description}\"\n\n";
        }

        $message .= "To update a goal, type 'goal update [value]'.\n";
        $message .= "To add a new goal, type 'goal new [description]'.";

        return $message;
    }

    /**
     * Format goal type for display
     */
    protected function formatGoalType(string $type): string
    {
        $typeMap = [
            'weight' => 'Weight Goal',
            'measurement' => 'Body Measurement',
            'health_marker' => 'Health Marker',
            'habit' => 'Habit Building',
            'fitness' => 'Fitness Goal',
            'other' => 'Personal Goal'
        ];

        return $typeMap[$type] ?? ucfirst($type);
    }

    /**
     * Create a new goal
     */
    protected function createGoal(User $user, string $description): string
    {
        if (empty($description)) {
            return "Please provide a goal description, for example: 'goal new lose 5 kg by July 1st'.";
        }

        // TODO: Implement goal creation with NLP (Natural Language Processing)
        // For now, just create a simple weight goal

        // Check if user already has an active weight goal
        $existingGoal = GoalTracking::where('user_id', $user->id)
            ->where('goal_type', 'weight')
            ->where('status', 'in_progress')
            ->first();

        if ($existingGoal) {
            return "You already have an active weight goal. Please complete or update it before creating a new one.";
        }

        // Create new goal
        $goal = new GoalTracking();
        $goal->user_id = $user->id;
        $goal->goal_type = 'weight';
        $goal->description = $description;

        // Get current weight
        $latestWeight = ProgressTracking::where('client_id', $user->id)
            ->whereNotNull('weight')
            ->latest('tracking_date')
            ->first();

        if (!$latestWeight) {
            return "Please record your current weight first by typing 'weight [your weight in kg]'.";
        }

        $goal->starting_value = $latestWeight->weight;
        $goal->current_value = $latestWeight->weight;

        // Default target weight (5% loss)
        $goal->target_value = round($latestWeight->weight * 0.95, 1);
        $goal->unit = 'kg';

        // Default target date (8 weeks from now)
        $goal->target_date = Carbon::now()->addWeeks(8)->format('Y-m-d');
        $goal->status = 'in_progress';
        $goal->progress_percentage = 0;

        $goal->save();

        return "🎯 New weight goal created!\n\n" .
            "Starting: {$goal->starting_value}kg\n" .
            "Target: {$goal->target_value}kg\n" .
            "Target date: " . Carbon::parse($goal->target_date)->format('M j, Y') . "\n\n" .
            "I'll help you track your progress. You can update this goal anytime with 'goal update [value]'.";
    }

    /**
     * Update an existing goal
     */
    protected function updateGoal(User $user, string $value): string
    {
        if (!is_numeric($value)) {
            return "Please provide a numeric value to update your goal progress.";
        }

        // Find the most recent active goal
        $goal = GoalTracking::where('user_id', $user->id)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        if (!$goal) {
            return "You don't have any active goals to update. Create one with 'goal new [description]'.";
        }

        $oldValue = $goal->current_value;
        $goal->current_value = (float) $value;

        // Calculate progress percentage
        $totalChange = $goal->target_value - $goal->starting_value;
        $currentChange = $goal->current_value - $goal->starting_value;

        if ($totalChange != 0) {
            $progressPercent = ($currentChange / $totalChange) * 100;

            // Cap at 100% for progress beyond goal
            if (
                ($totalChange < 0 && $progressPercent > 100) ||
                ($totalChange > 0 && $progressPercent > 100)
            ) {
                $progressPercent = 100;
            }

            $goal->progress_percentage = $progressPercent;
        }

        // Check if goal is achieved
        if (
            ($totalChange < 0 && $goal->current_value <= $goal->target_value) ||
            ($totalChange > 0 && $goal->current_value >= $goal->target_value)
        ) {
            $goal->status = 'achieved';
        }

        $goal->save();

        // Format a response based on progress
        $progress = round($goal->progress_percentage);
        $change = abs($goal->current_value - $oldValue);
        $changeDirection = $goal->current_value < $oldValue ? "decreased" : "increased";

        $message = "🎯 Goal progress updated!\n\n";
        $message .= "Your {$this->formatGoalType($goal->goal_type)} has {$changeDirection} by {$change}{$goal->unit}.\n";
        $message .= "Current progress: {$progress}% toward your target.\n";

        if ($goal->status === 'achieved') {
            $message .= "\n🎉 Congratulations! You've achieved your goal!";
        } else {
            $daysLeft = Carbon::now()->diffInDays(Carbon::parse($goal->target_date));
            $message .= "\nYou have {$daysLeft} days left to reach your target.";
        }

        return $message;
    }

    /**
     * Create calendar entries for meal plans
     * 
     * @param User $user The user to create calendar entries for
     * @param string $startDay First day to create entries for (e.g., 'monday')
     * @param int $daysCount Number of days to create entries for
     * @return string Status message
     */
    public function createCalendarEntries(User $user, string $startDay = 'monday', int $daysCount = 7): string
    {
        // Get active diet plan
        $dietPlan = DietPlan::where('client_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$dietPlan) {
            return "You don't have an active diet plan to sync with your calendar.";
        }

        // Get all the days we need to process
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        // Find start index
        $startIndex = array_search(strtolower($startDay), $days);
        if ($startIndex === false) {
            $startIndex = 0; // Default to Monday if invalid day provided
        }

        // Calculate days to process
        $daysToProcess = [];
        for ($i = 0; $i < $daysCount; $i++) {
            $dayIndex = ($startIndex + $i) % 7;
            $daysToProcess[] = $days[$dayIndex];
        }

        // Get current date for the specified start day
        $currentDate = Carbon::now();
        $daysUntilStart = ($startIndex - $currentDate->dayOfWeekIso + 7) % 7;
        $currentDate->addDays($daysUntilStart);

        $createdCount = 0;
        $updatedCount = 0;

        // Process each day
        foreach ($daysToProcess as $day) {
            $mealPlan = $dietPlan->mealPlans()->where('day_of_week', $day)->first();

            if (!$mealPlan) {
                continue; // Skip days without meal plans
            }

            $meals = $mealPlan->meals;
            $dayDate = $currentDate->copy()->format('Y-m-d'); // Get date for current iteration

            foreach ($meals as $meal) {
                // Create or update calendar event
                $event = CalendarEvent::firstOrNew([
                    'user_id' => $user->id,
                    'event_type' => 'meal',
                    'start_time' => Carbon::parse("{$dayDate} {$meal->time_of_day}")
                ]);

                $isNew = !$event->exists;
                $event->title = "{$meal->title}";
                $event->description = $meal->description;

                // Calculate end time (default to 30 minutes after start)
                $event->end_time = Carbon::parse("{$dayDate} {$meal->time_of_day}")->addMinutes(30);

                // Set reminder to 30 minutes before
                $event->reminder_minutes = 30;
                $event->save();

                if ($isNew) {
                    $createdCount++;
                } else {
                    $updatedCount++;
                }
            }

            // Move to next day
            $currentDate->addDay();
        }

        if ($createdCount === 0 && $updatedCount === 0) {
            return "No meal plans were found for the specified days.";
        }

        return "📅 Calendar updated with your meal schedule!\n" .
            "Created {$createdCount} new entries and updated {$updatedCount} existing ones.\n\n" .
            "You'll receive reminders 30 minutes before each scheduled meal.";
    }

    /**
     * Send a WhatsApp meal reminder
     * 
     * @param User $user The user to send the reminder to
     * @param CalendarEvent $event The calendar event (meal) to remind about
     * @return bool Success status
     */
    public function sendMealReminder(User $user, CalendarEvent $event): bool
    {
        if (!$user->whatsapp_phone) {
            return false;
        }

        $message = "⏰ *Meal Reminder* ⏰\n\n";
        $message .= "It's almost time for your scheduled meal:\n\n";
        $message .= "*{$event->title}*\n";

        if (!empty($event->description)) {
            $message .= "{$event->description}\n\n";
        }

        $message .= "Scheduled for: " . Carbon::parse($event->start_time)->format('g:i A') . "\n\n";
        $message .= "Remember to log your meal completion with 'meal done' later!";

        return $this->getWhatsAppService()->sendTextMessage($user->whatsapp_phone, $message);
    }

    /**
     * Send a weekly summary to the user
     * 
     * @param User $user The user to send the summary to
     * @return bool Success status
     */
    public function sendWeeklySummary(User $user): bool
    {
        if (!$user->whatsapp_phone) {
            return false;
        }

        // Get the weekly progress data
        $weekStart = Carbon::now()->subWeek()->startOfWeek()->format('Y-m-d');
        $weekEnd = Carbon::now()->subWeek()->endOfWeek()->format('Y-m-d');

        $weeklyProgress = ProgressTracking::where('client_id', $user->id)
            ->where('tracking_date', $weekStart)
            ->first();

        // Get daily entries for the past week
        $dailyEntries = DailyProgress::where('user_id', $user->id)
            ->whereBetween('tracking_date', [$weekStart, $weekEnd])
            ->get();

        $message = "📊 *Weekly Progress Summary* 📊\n\n";
        $message .= "Here's how you did for the week of " .
            Carbon::parse($weekStart)->format('M j') . " - " .
            Carbon::parse($weekEnd)->format('M j') . ":\n\n";

        if ($dailyEntries->count() > 0) {
            // Calculate statistics
            $checkInRate = round(($dailyEntries->count() / 7) * 100);

            $totalMealsCompleted = $dailyEntries->sum('meals_completed');
            $totalMealsPlanned = $dailyEntries->sum('total_meals');
            $mealComplianceRate = $totalMealsPlanned > 0 ?
                round(($totalMealsCompleted / $totalMealsPlanned) * 100) : 0;

            $exerciseDays = $dailyEntries->where('exercise_done', true)->count();
            $exerciseRate = round(($exerciseDays / 7) * 100);

            $avgWaterIntake = round($dailyEntries->avg('water_intake'));

            // Format the summary
            $message .= "*Stats:*\n";
            $message .= "• Check-in rate: {$checkInRate}% ({$dailyEntries->count()}/7 days)\n";
            $message .= "• Meal compliance: {$mealComplianceRate}%\n";
            $message .= "• Exercise completion: {$exerciseRate}% ({$exerciseDays}/7 days)\n";
            $message .= "• Average water intake: " . ($avgWaterIntake >= 1000 ?
                round($avgWaterIntake / 1000, 1) . " liters" :
                $avgWaterIntake . " ml") . "\n\n";
        } else {
            $message .= "No check-ins recorded for last week.\n\n";
        }

        // Weight progress if available
        if ($weeklyProgress && $weeklyProgress->weight) {
            // Get previous week's progress for comparison
            $prevWeekProgress = ProgressTracking::where('client_id', $user->id)
                ->where('tracking_date', '<', $weekStart)
                ->whereNotNull('weight')
                ->orderBy('tracking_date', 'desc')
                ->first();

            if ($prevWeekProgress && $prevWeekProgress->weight) {
                $weightChange = $weeklyProgress->weight - $prevWeekProgress->weight;
                $changeDirection = $weightChange <= 0 ? "lost" : "gained";
                $weightChange = abs($weightChange);

                if ($weightChange >= 0.1) {
                    $message .= "*Weight Change:*\n";
                    $message .= "You've {$changeDirection} {$weightChange} kg this week.\n\n";
                } else {
                    $message .= "*Weight Status:*\n";
                    $message .= "Your weight remained stable this week at {$weeklyProgress->weight} kg.\n\n";
                }
            } else {
                $message .= "*Current Weight:*\n";
                $message .= "{$weeklyProgress->weight} kg\n\n";
            }
        }

        // Suggestions based on compliance
        $message .= "*For Next Week:*\n";

        if ($dailyEntries->count() < 5) {
            $message .= "• Try to check in more regularly for better tracking\n";
        }

        if (isset($mealComplianceRate) && $mealComplianceRate < 80) {
            $message .= "• Focus on following your meal plan more closely\n";
        }

        if (isset($exerciseRate) && $exerciseRate < 60) {
            $message .= "• Schedule your workouts to improve consistency\n";
        }

        if (isset($avgWaterIntake) && $avgWaterIntake < 2000) {
            $message .= "• Increase your daily water intake\n";
        }

        $message .= "\nType 'progress' anytime to see your current stats, or 'checkin' to log today's progress.";

        return $this->getWhatsAppService()->sendTextMessage($user->whatsapp_phone, $message);
    }
}