<?php
// app/Services/NutritionInfoService.php
namespace App\Services;

use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\NutritionInfo;
use App\Models\User;
use App\Models\DietPlan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NutritionInfoService
{
    protected $apiKey;
    protected $apiUrl;
    protected $cacheEnabled;
    protected $cacheDuration;

    public function __construct()
    {
        $this->apiKey = config('services.nutrition_api.key');
        $this->apiUrl = config('services.nutrition_api.url');
        $this->cacheEnabled = config('services.nutrition_api.cache_enabled', true);
        $this->cacheDuration = config('services.nutrition_api.cache_duration', 60 * 24 * 7); // Default 1 week
    }

    /**
     * Enhance a meal with detailed nutrition information
     * 
     * @param Meal $meal The meal to enhance with nutrition information
     * @return bool Success or failure
     */
    public function enhanceMealNutrition(Meal $meal): bool
    {
        try {
            // Check if we already have nutritional data for this meal
            $existingInfo = NutritionInfo::where('meal_id', $meal->id)->first();
            if ($existingInfo) {
                return true; // Already enhanced
            }

            // Extract ingredients from meal
            $ingredients = $this->extractIngredientsFromMeal($meal);
            if (empty($ingredients)) {
                Log::warning("No ingredients found for meal", ['meal_id' => $meal->id]);
                return false;
            }

            // Get nutrition data (either from API or simulation)
            $nutritionData = $this->getNutritionData($ingredients, $meal->title);

            if (!$nutritionData) {
                // If we couldn't get real data, use the meal's existing macros to create basic info
                $nutritionData = $this->generateEstimatedNutritionData($meal);
            }

            // Store nutrition information
            $nutritionInfo = new NutritionInfo();
            $nutritionInfo->meal_id = $meal->id;
            $nutritionInfo->calories = $nutritionData['calories'] ?? $meal->calories;
            $nutritionInfo->protein_grams = $nutritionData['protein_grams'] ?? $meal->protein_grams;
            $nutritionInfo->carbs_grams = $nutritionData['carbs_grams'] ?? $meal->carbs_grams;
            $nutritionInfo->fats_grams = $nutritionData['fats_grams'] ?? $meal->fats_grams;
            
            // Additional nutrition information
            $nutritionInfo->fiber_grams = $nutritionData['fiber_grams'] ?? 0;
            $nutritionInfo->sugar_grams = $nutritionData['sugar_grams'] ?? 0;
            $nutritionInfo->sodium_mg = $nutritionData['sodium_mg'] ?? 0;
            $nutritionInfo->calcium_mg = $nutritionData['calcium_mg'] ?? 0;
            $nutritionInfo->iron_mg = $nutritionData['iron_mg'] ?? 0;
            $nutritionInfo->vitamin_a_iu = $nutritionData['vitamin_a_iu'] ?? 0;
            $nutritionInfo->vitamin_c_mg = $nutritionData['vitamin_c_mg'] ?? 0;
            $nutritionInfo->vitamin_d_iu = $nutritionData['vitamin_d_iu'] ?? 0;
            $nutritionInfo->vitamin_e_mg = $nutritionData['vitamin_e_mg'] ?? 0;
            
            $nutritionInfo->save();

            // Update the meal's main macros if they differ significantly
            $this->updateMealMacrosIfNeeded($meal, $nutritionData);

            return true;
        } catch (\Exception $e) {
            Log::error("Error enhancing meal nutrition", [
                'meal_id' => $meal->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Extract ingredients from a meal
     */
    protected function extractIngredientsFromMeal(Meal $meal): array
    {
        $ingredients = [];
        $recipes = $meal->recipes;
        
        // Handle both string (JSON) and array representations
        if (is_string($recipes)) {
            $recipes = json_decode($recipes, true);
        }
        
        if (!$recipes || !isset($recipes['ingredients']) || !is_array($recipes['ingredients'])) {
            return $ingredients;
        }
        
        return $recipes['ingredients'];
    }

    /**
     * Get nutrition data from API or cache
     */
    protected function getNutritionData(array $ingredients, string $mealName): ?array
    {
        // Create a cache key based on ingredients and meal name
        $cacheKey = 'nutrition_data_' . md5($mealName . json_encode($ingredients));
        
        // Check cache if enabled
        if ($this->cacheEnabled && Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }
        
        // If no API key or using simulation mode, generate simulated data
        if (empty($this->apiKey) || config('services.nutrition_api.use_simulation', true)) {
            $data = $this->simulateNutritionData($ingredients, $mealName);
            
            // Cache the result
            if ($this->cacheEnabled) {
                Cache::put($cacheKey, $data, $this->cacheDuration);
            }
            
            return $data;
        }
        
        // Call external nutrition API (implementation depends on which API you use)
        try {
            // Example using a generic REST API
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl, [
                'title' => $mealName,
                'ingredients' => $ingredients
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                
                // Cache the result
                if ($this->cacheEnabled) {
                    Cache::put($cacheKey, $data, $this->cacheDuration);
                }
                
                return $data;
            } else {
                Log::error("Nutrition API error", [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'meal' => $mealName
                ]);
                
                return null;
            }
        } catch (\Exception $e) {
            Log::error("Nutrition API exception", [
                'error' => $e->getMessage(),
                'meal' => $mealName
            ]);
            
            return null;
        }
    }

    /**
     * Simulate nutrition data for testing without an API
     */
    protected function simulateNutritionData(array $ingredients, string $mealName): array
    {
        // Base calorie approximation based on ingredient count
        $baseCalories = count($ingredients) * 120;
        
        // Adjust based on meal name keywords
        $lowCalorieTerms = ['salad', 'vegetable', 'light', 'lean', 'diet', 'steamed'];
        $highCalorieTerms = ['fried', 'creamy', 'cheese', 'butter', 'rich', 'sweet', 'dessert'];
        
        $calorieMultiplier = 1.0;
        foreach ($lowCalorieTerms as $term) {
            if (stripos($mealName, $term) !== false) {
                $calorieMultiplier *= 0.85;
            }
        }
        
        foreach ($highCalorieTerms as $term) {
            if (stripos($mealName, $term) !== false) {
                $calorieMultiplier *= 1.25;
            }
        }
        
        $calories = round($baseCalories * $calorieMultiplier);
        
        // Determine macro distribution based on meal type
        $proteinPercent = 0.25; // Default protein percentage
        $carbPercent = 0.5;     // Default carb percentage
        $fatPercent = 0.25;     // Default fat percentage
        
        // Adjust based on meal type
        if (stripos($mealName, 'protein') !== false || stripos($mealName, 'meat') !== false || 
            stripos($mealName, 'chicken') !== false || stripos($mealName, 'fish') !== false) {
            $proteinPercent = 0.4;
            $carbPercent = 0.3;
            $fatPercent = 0.3;
        } elseif (stripos($mealName, 'pasta') !== false || stripos($mealName, 'rice') !== false || 
                  stripos($mealName, 'bread') !== false || stripos($mealName, 'cereal') !== false) {
            $proteinPercent = 0.15;
            $carbPercent = 0.65;
            $fatPercent = 0.2;
        } elseif (stripos($mealName, 'salad') !== false || stripos($mealName, 'vegetable') !== false) {
            $proteinPercent = 0.2;
            $carbPercent = 0.5;
            $fatPercent = 0.3;
        }
        
        // Calculate macros based on calories and percentages
        $proteinGrams = round(($calories * $proteinPercent) / 4); // 4 calories per gram
        $carbGrams = round(($calories * $carbPercent) / 4);       // 4 calories per gram
        $fatGrams = round(($calories * $fatPercent) / 9);         // 9 calories per gram
        
        // Generate other nutritional values
        $fiberGrams = round($carbGrams * (rand(10, 25) / 100)); // 10-25% of carbs
        $sugarGrams = round($carbGrams * (rand(5, 40) / 100));  // 5-40% of carbs
        
        // Generate micronutrients
        $sodiumMg = round(rand(50, 500) * count($ingredients)); // Rough estimate
        $calciumMg = round(rand(20, 200) * count($ingredients));
        $ironMg = rand(1, 8);
        $vitaminAIu = rand(100, 1000);
        $vitaminCMg = rand(5, 60);
        $vitaminDIu = rand(10, 100);
        $vitaminEMg = rand(1, 15);
        
        return [
            'calories' => $calories,
            'protein_grams' => $proteinGrams,
            'carbs_grams' => $carbGrams,
            'fats_grams' => $fatGrams,
            'fiber_grams' => $fiberGrams,
            'sugar_grams' => $sugarGrams,
            'sodium_mg' => $sodiumMg,
            'calcium_mg' => $calciumMg,
            'iron_mg' => $ironMg,
            'vitamin_a_iu' => $vitaminAIu,
            'vitamin_c_mg' => $vitaminCMg,
            'vitamin_d_iu' => $vitaminDIu,
            'vitamin_e_mg' => $vitaminEMg
        ];
    }

    /**
     * Generate basic nutrition data based on meal's existing macros
     */
    protected function generateEstimatedNutritionData(Meal $meal): array
    {
        // Start with the existing macros
        $calories = $meal->calories;
        $proteinGrams = $meal->protein_grams;
        $carbGrams = $meal->carbs_grams;
        $fatGrams = $meal->fats_grams;
        
        // Estimate other values based on macros
        $fiberGrams = round($carbGrams * (rand(10, 25) / 100)); // 10-25% of carbs
        $sugarGrams = round($carbGrams * (rand(5, 40) / 100));  // 5-40% of carbs
        
        // Estimate micronutrients
        $sodiumMg = round($calories * 0.5); // Rough estimate
        $calciumMg = round($calories * 0.15);
        $ironMg = round($proteinGrams * 0.1);
        $vitaminAIu = round($calories * 0.8);
        $vitaminCMg = round($carbGrams * 0.5);
        $vitaminDIu = round($fatGrams * 1.5);
        $vitaminEMg = round($fatGrams * 0.2);
        
        return [
            'calories' => $calories,
            'protein_grams' => $proteinGrams,
            'carbs_grams' => $carbGrams,
            'fats_grams' => $fatGrams,
            'fiber_grams' => $fiberGrams,
            'sugar_grams' => $sugarGrams,
            'sodium_mg' => $sodiumMg,
            'calcium_mg' => $calciumMg,
            'iron_mg' => $ironMg,
            'vitamin_a_iu' => $vitaminAIu,
            'vitamin_c_mg' => $vitaminCMg,
            'vitamin_d_iu' => $vitaminDIu,
            'vitamin_e_mg' => $vitaminEMg
        ];
    }

    /**
     * Update meal macros if the calculated values differ significantly
     */
    protected function updateMealMacrosIfNeeded(Meal $meal, array $nutritionData): void
    {
        $thresholdPercent = 15; // Only update if difference is greater than 15%
        $updateNeeded = false;
        
        // Check if any macro differs significantly
        if (isset($nutritionData['calories']) && 
            abs($meal->calories - $nutritionData['calories']) > ($meal->calories * $thresholdPercent / 100)) {
            $meal->calories = $nutritionData['calories'];
            $updateNeeded = true;
        }
        
        if (isset($nutritionData['protein_grams']) && 
            abs($meal->protein_grams - $nutritionData['protein_grams']) > ($meal->protein_grams * $thresholdPercent / 100)) {
            $meal->protein_grams = $nutritionData['protein_grams'];
            $updateNeeded = true;
        }
        
        if (isset($nutritionData['carbs_grams']) && 
            abs($meal->carbs_grams - $nutritionData['carbs_grams']) > ($meal->carbs_grams * $thresholdPercent / 100)) {
            $meal->carbs_grams = $nutritionData['carbs_grams'];
            $updateNeeded = true;
        }
        
        if (isset($nutritionData['fats_grams']) && 
            abs($meal->fats_grams - $nutritionData['fats_grams']) > ($meal->fats_grams * $thresholdPercent / 100)) {
            $meal->fats_grams = $nutritionData['fats_grams'];
            $updateNeeded = true;
        }
        
        if ($updateNeeded) {
            $meal->save();
            
            // Also update meal plan totals
            $mealPlan = MealPlan::find($meal->meal_plan_id);
            if ($mealPlan) {
                $mealPlan->calculateTotals();
                $mealPlan->save();
            }
        }
    }

    /**
     * Format nutrition information for WhatsApp display
     */
    public function formatNutritionInfoForWhatsApp(Meal $meal): string
    {
        // Get the nutrition info
        $nutritionInfo = NutritionInfo::where('meal_id', $meal->id)->first();
        
        // If no enhanced nutrition info exists, create it
        if (!$nutritionInfo) {
            $success = $this->enhanceMealNutrition($meal);
            if ($success) {
                $nutritionInfo = NutritionInfo::where('meal_id', $meal->id)->first();
            }
        }
        
        // Format the basic info (always available)
        $message = "📊 *Nutrition Information: {$meal->title}* 📊\n\n";
        $message .= "*Macronutrients:*\n";
        $message .= "• Calories: {$meal->calories} kcal\n";
        $message .= "• Protein: {$meal->protein_grams}g\n";
        $message .= "• Carbs: {$meal->carbs_grams}g\n";
        $message .= "• Fats: {$meal->fats_grams}g\n";
        
        // Add enhanced nutrition info if available
        if ($nutritionInfo) {
            $message .= "\n*Detailed Nutrition:*\n";
            
            if ($nutritionInfo->fiber_grams > 0) {
                $message .= "• Fiber: {$nutritionInfo->fiber_grams}g\n";
            }
            
            if ($nutritionInfo->sugar_grams > 0) {
                $message .= "• Sugar: {$nutritionInfo->sugar_grams}g\n";
            }
            
            // Add a section for vitamins and minerals if available
            $hasMicronutrients = false;
            $micronutrients = "";
            
            if ($nutritionInfo->sodium_mg > 0) {
                $micronutrients .= "• Sodium: {$nutritionInfo->sodium_mg}mg\n";
                $hasMicronutrients = true;
            }
            
            if ($nutritionInfo->calcium_mg > 0) {
                $micronutrients .= "• Calcium: {$nutritionInfo->calcium_mg}mg\n";
                $hasMicronutrients = true;
            }
            
            if ($nutritionInfo->iron_mg > 0) {
                $micronutrients .= "• Iron: {$nutritionInfo->iron_mg}mg\n";
                $hasMicronutrients = true;
            }
            
            if ($nutritionInfo->vitamin_a_iu > 0) {
                $micronutrients .= "• Vitamin A: {$nutritionInfo->vitamin_a_iu}IU\n";
                $hasMicronutrients = true;
            }
            
            if ($nutritionInfo->vitamin_c_mg > 0) {
                $micronutrients .= "• Vitamin C: {$nutritionInfo->vitamin_c_mg}mg\n";
                $hasMicronutrients = true;
            }
            
            if ($hasMicronutrients) {
                $message .= "\n*Vitamins & Minerals:*\n" . $micronutrients;
            }
        }
        
        // Add health tips based on the nutritional content
        $message .= "\n*Health Benefits:*\n";
        
        if ($meal->protein_grams > 20) {
            $message .= "• High in protein, great for muscle recovery 💪\n";
        }
        
        if ($nutritionInfo && $nutritionInfo->fiber_grams > 5) {
            $message .= "• Good source of fiber for digestive health 🍃\n";
        }
        
        if ($nutritionInfo && $nutritionInfo->vitamin_c_mg > 30) {
            $message .= "• Rich in vitamin C for immune support 🍊\n";
        }
        
        if ($nutritionInfo && $nutritionInfo->iron_mg > 3) {
            $message .= "• Good source of iron for healthy blood 💯\n";
        }
        
        return $message;
    }

    /**
     * Process a nutrition-related command
     */
    public function processNutritionCommand(User $user, string $command): ?string
    {
        $commandParts = explode(' ', strtolower(trim($command)), 3);
        $action = $commandParts[0] ?? '';
        $parameter1 = $commandParts[1] ?? '';
        $parameter2 = $commandParts[2] ?? '';
        
        switch ($action) {
            case 'nutrition':
            case 'nutrients':
                return $this->handleNutritionInfoRequest($user, $parameter1, $parameter2);
                
            case 'calories':
            case 'macros':
                return $this->handleMacrosSummary($user, $parameter1);
        }
        
        return null;
    }

    /**
     * Handle request for nutrition information
     */
    protected function handleNutritionInfoRequest(User $user, string $mealType, string $day): string
    {
        if (empty($mealType) || empty($day)) {
            return "Please specify a meal type and day, for example: 'nutrition breakfast monday'";
        }
        
        // Get active diet plan
        $dietPlan = DietPlan::where('client_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();
            
        if (!$dietPlan) {
            return "You don't have an active diet plan. Type 'start' to begin the assessment process.";
        }
        
        // Find the meal
        $mealPlan = $dietPlan->mealPlans()->where('day_of_week', $day)->first();
        
        if (!$mealPlan) {
            return "I couldn't find a meal plan for {$day}.";
        }
        
        $meal = $mealPlan->meals()->where('meal_type', $mealType)->first();
        
        if (!$meal) {
            return "I couldn't find a {$mealType} meal for {$day}.";
        }
        
        // Format and return nutrition information
        return $this->formatNutritionInfoForWhatsApp($meal);
    }

    /**
     * Handle request for macros summary
     */
    protected function handleMacrosSummary(User $user, string $day = ''): string
    {
        // Get active diet plan
        $dietPlan = DietPlan::where('client_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();
            
        if (!$dietPlan) {
            return "You don't have an active diet plan. Type 'start' to begin the assessment process.";
        }
        
        if (empty($day)) {
            // Show overall diet plan macros
            $message = "📊 *Your Daily Macro Targets* 📊\n\n";
            $message .= "• Calories: {$dietPlan->daily_calories} kcal\n";
            $message .= "• Protein: {$dietPlan->protein_grams}g (" . $this->calculateMacroPercentage($dietPlan, 'protein') . "%)\n";
            $message .= "• Carbs: {$dietPlan->carbs_grams}g (" . $this->calculateMacroPercentage($dietPlan, 'carbs') . "%)\n";
            $message .= "• Fats: {$dietPlan->fats_grams}g (" . $this->calculateMacroPercentage($dietPlan, 'fats') . "%)\n\n";
            
            $message .= "To see macros for a specific day, type 'macros [day]'";
            
            return $message;
        } else {
            // Show macros for specific day
            $mealPlan = $dietPlan->mealPlans()->where('day_of_week', $day)->first();
            
            if (!$mealPlan) {
                return "I couldn't find a meal plan for {$day}.";
            }
            
            $meals = $mealPlan->meals;
            
            $message = "📊 *{$day}'s Nutrition Summary* 📊\n\n";
            
            $totalCalories = 0;
            $totalProtein = 0;
            $totalCarbs = 0;
            $totalFats = 0;
            
            foreach ($meals as $meal) {
                $totalCalories += $meal->calories;
                $totalProtein += $meal->protein_grams;
                $totalCarbs += $meal->carbs_grams;
                $totalFats += $meal->fats_grams;
            }
            
            $message .= "*Daily Totals:*\n";
            $message .= "• Calories: {$totalCalories} kcal\n";
            $message .= "• Protein: {$totalProtein}g (" . round(($totalProtein * 4 / $totalCalories) * 100) . "%)\n";
            $message .= "• Carbs: {$totalCarbs}g (" . round(($totalCarbs * 4 / $totalCalories) * 100) . "%)\n";
            $message .= "• Fats: {$totalFats}g (" . round(($totalFats * 9 / $totalCalories) * 100) . "%)\n\n";
            
            $message .= "*Target Comparison:*\n";
            $message .= "• Calories: " . $this->formatComparisonPercent($totalCalories, $dietPlan->daily_calories) . "\n";
            $message .= "• Protein: " . $this->formatComparisonPercent($totalProtein, $dietPlan->protein_grams) . "\n";
            $message .= "• Carbs: " . $this->formatComparisonPercent($totalCarbs, $dietPlan->carbs_grams) . "\n";
            $message .= "• Fats: " . $this->formatComparisonPercent($totalFats, $dietPlan->fats_grams) . "\n\n";
            
            $message .= "For detailed meal nutrition, type 'nutrition [meal] {$day}'";
            
            return $message;
        }
    }

    /**
     * Calculate macro percentage for display
     */
    protected function calculateMacroPercentage(DietPlan $dietPlan, string $macro): int
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
     * Format comparison percentage with indicator
     */
    protected function formatComparisonPercent(float $actual, float $target): string
    {
        if ($target == 0) {
            return "N/A";
        }
        
        $percent = ($actual / $target) * 100;
        $formattedPercent = round($percent);
        
        if ($formattedPercent < 90) {
            return "{$actual}/{$target} ({$formattedPercent}%) ⬇️";
        } elseif ($formattedPercent > 110) {
            return "{$actual}/{$target} ({$formattedPercent}%) ⬆️";
        } else {
            return "{$actual}/{$target} ({$formattedPercent}%) ✓";
        }
    }
}