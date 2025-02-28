<?php
// app/Services/GroceryListService.php
namespace App\Services;

use App\Models\DietPlan;
use App\Models\GroceryList;
use App\Models\GroceryItem;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class GroceryListService
{
    /**
     * Generate a grocery list for a user's active diet plan
     * 
     * @param User $user The user to generate the grocery list for
     * @param string|null $period 'day', 'week', or 'custom'
     * @param array|null $days Array of specific days (e.g., ['monday', 'tuesday'])
     * @return GroceryList|null
     */
    public function generateGroceryList(User $user, string $period = 'week', ?array $days = null): ?GroceryList
    {
        try {
            // Get active diet plan
            $dietPlan = DietPlan::where('client_id', $user->id)
                ->where('status', 'active')
                ->latest()
                ->first();

            if (!$dietPlan) {
                Log::warning("No active diet plan found for user", ['user_id' => $user->id]);
                return null;
            }

            // Determine which days to include
            $daysToInclude = $this->determineDaysToInclude($period, $days);

            // Create grocery list record
            $groceryList = new GroceryList();
            $groceryList->user_id = $user->id;
            $groceryList->diet_plan_id = $dietPlan->id;
            $groceryList->title = $this->generateTitle($period, $daysToInclude);
            $groceryList->description = "Grocery list for your " . ($period === 'day' ? 'daily' : 'weekly') . " meal plan";
            $groceryList->week_starting = Carbon::now()->startOfWeek();
            $groceryList->status = 'active';
            $groceryList->save();

            // Extract ingredients from meals
            $this->extractAndStoreIngredients($groceryList, $dietPlan, $daysToInclude);

            return $groceryList;
        } catch (\Exception $e) {
            Log::error("Error generating grocery list", [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Determine which days to include in the grocery list
     */
    protected function determineDaysToInclude(string $period, ?array $days): array
    {
        $allDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        if ($period === 'day' && !empty($days)) {
            // Only include specified days
            return array_intersect($allDays, $days);
        } elseif ($period === 'custom' && !empty($days)) {
            // Custom selection of days
            return array_intersect($allDays, $days);
        } else {
            // Default to whole week
            return $allDays;
        }
    }

    /**
     * Generate a title for the grocery list
     */
    protected function generateTitle(string $period, array $daysToInclude): string
    {
        if ($period === 'day' && count($daysToInclude) === 1) {
            return ucfirst($daysToInclude[0]) . "'s Grocery List";
        } elseif ($period === 'custom') {
            $dayCount = count($daysToInclude);

            if ($dayCount <= 3) {
                // List the days if there are only a few
                $days = array_map('ucfirst', $daysToInclude);
                return implode(', ', $days) . " Grocery List";
            } else {
                return "Custom " . $dayCount . "-Day Grocery List";
            }
        } else {
            return "Weekly Grocery List";
        }
    }

    /**
     * Extract ingredients from meals and store as grocery items
     */
    protected function extractAndStoreIngredients(GroceryList $groceryList, DietPlan $dietPlan, array $daysToInclude): void
    {
        $allIngredients = [];
        $mealPlans = MealPlan::where('diet_plan_id', $dietPlan->id)
            ->whereIn('day_of_week', $daysToInclude)
            ->get();

        foreach ($mealPlans as $mealPlan) {
            $meals = $mealPlan->meals;

            foreach ($meals as $meal) {
                $ingredients = $this->extractIngredientsFromMeal($meal);

                foreach ($ingredients as $ingredient) {
                    // Format: [name, quantity, unit]
                    $key = strtolower($ingredient[0]);

                    if (isset($allIngredients[$key])) {
                        // If ingredient exists, potentially update quantity
                        $this->consolidateIngredient($allIngredients[$key], $ingredient);
                    } else {
                        $allIngredients[$key] = $ingredient;
                    }
                }
            }
        }

        // Store consolidated ingredients
        foreach ($allIngredients as $ingredient) {
            $this->createGroceryItem($groceryList->id, $ingredient);
        }
    }

    /**
     * Extract ingredients array from meal recipe
     * 
     * @param Meal $meal
     * @return array Array of ingredient arrays [name, quantity, unit]
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

        foreach ($recipes['ingredients'] as $ingredient) {
            $parsed = $this->parseIngredient($ingredient);
            if ($parsed) {
                $ingredients[] = $parsed;
            }
        }

        return $ingredients;
    }

    /**
     * Parse an ingredient string into structured data
     * 
     * @param string $ingredient The ingredient string (e.g., "2 cups flour")
     * @return array|null [name, quantity, unit]
     */
    protected function parseIngredient(string $ingredient): ?array
    {
        // Remove any extra spaces
        $ingredient = trim($ingredient);

        // Skip empty ingredients
        if (empty($ingredient)) {
            return null;
        }

        // Check for common ingredient patterns
        // Pattern: quantity unit name (e.g., "2 cups flour")
        if (preg_match('/^(\d+\/?\d*|\d*\.\d+)\s+([a-zA-Z]+)\s+(.+)$/', $ingredient, $matches)) {
            return [
                $matches[3], // name
                $matches[1], // quantity
                $matches[2]  // unit
            ];
        }

        // Pattern: quantity name (e.g., "2 apples")
        if (preg_match('/^(\d+\/?\d*|\d*\.\d+)\s+(.+)$/', $ingredient, $matches)) {
            return [
                $matches[2], // name
                $matches[1], // quantity
                ''           // no unit
            ];
        }

        // Pattern: fractions like "1/2 cup flour"
        if (preg_match('/^(\d+\/\d+)\s+([a-zA-Z]+)\s+(.+)$/', $ingredient, $matches)) {
            return [
                $matches[3], // name
                $matches[1], // quantity (fraction)
                $matches[2]  // unit
            ];
        }

        // If no pattern matches, just use the whole string as the name
        return [
            $ingredient, // name
            '',          // no quantity
            ''           // no unit
        ];
    }

    /**
     * Consolidate identical ingredients with different quantities
     */
    protected function consolidateIngredient(array &$existing, array $new): void
    {
        // Only consolidate if units match
        if ($existing[2] === $new[2] && !empty($existing[1]) && !empty($new[1])) {
            // Try to add quantities if they're numeric or fractions
            if (is_numeric($existing[1]) && is_numeric($new[1])) {
                $existing[1] = (float) $existing[1] + (float) $new[1];
            } elseif (strpos($existing[1], '/') !== false || strpos($new[1], '/') !== false) {
                // Handle fractions - convert to decimal, add, then back to fraction if needed
                $existing[1] = $this->addFractions($existing[1], $new[1]);
            } else {
                // If we can't add them properly, just note both quantities
                $existing[1] = $existing[1] . ' + ' . $new[1];
            }
        } elseif (empty($existing[1]) && !empty($new[1])) {
            // If existing has no quantity but new does, use the new quantity
            $existing[1] = $new[1];
            $existing[2] = $new[2]; // Also update unit
        }
    }

    /**
     * Add two fractions or numbers represented as strings
     */
    protected function addFractions(string $a, string $b): string
    {
        // Convert fractions to decimal
        $valueA = $this->fractionToDecimal($a);
        $valueB = $this->fractionToDecimal($b);

        // Add decimals
        $sum = $valueA + $valueB;

        // Format the result to avoid long decimals
        return (string) round($sum, 2);
    }

    /**
     * Convert a fraction string to decimal
     */
    protected function fractionToDecimal(string $fraction): float
    {
        // If it's already a number, return it
        if (is_numeric($fraction)) {
            return (float) $fraction;
        }

        // Handle mixed numbers like "1 1/2"
        if (strpos($fraction, ' ') !== false) {
            list($whole, $fraction) = explode(' ', $fraction, 2);
            return (float) $whole + $this->fractionToDecimal($fraction);
        }

        // Handle simple fractions like "1/2"
        if (strpos($fraction, '/') !== false) {
            list($numerator, $denominator) = explode('/', $fraction, 2);
            return $denominator != 0 ? (float) $numerator / (float) $denominator : 0;
        }

        // Default case
        return 0;
    }

    /**
     * Create a grocery item from parsed ingredient data
     */
    protected function createGroceryItem(int $groceryListId, array $ingredient): GroceryItem
    {
        // Determine category based on ingredient name
        $category = $this->categorizeIngredient($ingredient[0]);

        // Format quantity
        $quantity = $ingredient[1];
        if (!empty($ingredient[2])) {
            $quantity .= ' ' . $ingredient[2];
        }

        $item = new GroceryItem();
        $item->grocery_list_id = $groceryListId;
        $item->name = $ingredient[0];
        $item->quantity = $quantity;
        $item->category = $category;
        $item->is_purchased = false;
        $item->save();

        return $item;
    }

    /**
     * Categorize an ingredient based on its name
     */
    protected function categorizeIngredient(string $name): string
    {
        $lowercaseName = strtolower($name);

        // Define category keywords
        $categoryMap = [
            'produce' => [
                'apple',
                'banana',
                'orange',
                'pear',
                'grape',
                'berry',
                'strawberry',
                'blueberry',
                'lettuce',
                'spinach',
                'kale',
                'tomato',
                'cucumber',
                'carrot',
                'onion',
                'garlic',
                'potato',
                'sweet potato',
                'broccoli',
                'cauliflower',
                'pepper',
                'eggplant',
                'zucchini',
                'fruit',
                'vegetable',
                'lemon',
                'lime',
                'avocado'
            ],
            'protein' => [
                'beef',
                'chicken',
                'pork',
                'lamb',
                'meat',
                'steak',
                'fish',
                'salmon',
                'tuna',
                'shrimp',
                'tofu',
                'tempeh',
                'seitan',
                'bean',
                'lentil',
                'pulse',
                'protein',
                'egg'
            ],
            'dairy' => [
                'milk',
                'cheese',
                'yogurt',
                'butter',
                'cream',
                'sour cream',
                'curd',
                'paneer',
                'dairy',
                'ghee',
                'buttermilk',
                'kefir'
            ],
            'grains' => [
                'rice',
                'wheat',
                'oat',
                'barley',
                'bread',
                'pasta',
                'noodle',
                'cereal',
                'grain',
                'quinoa',
                'couscous',
                'flour',
                'cornmeal'
            ],
            'pantry' => [
                'oil',
                'vinegar',
                'sauce',
                'paste',
                'can',
                'jar',
                'condiment',
                'ketchup',
                'mustard',
                'mayo',
                'mayonnaise',
                'nut',
                'seed',
                'dried',
                'canned',
                'sugar',
                'syrup',
                'honey',
                'jam',
                'jelly',
                'preserve'
            ],
            'spices' => [
                'salt',
                'pepper',
                'spice',
                'herb',
                'seasoning',
                'cinnamon',
                'cumin',
                'curry',
                'paprika',
                'oregano',
                'basil',
                'thyme',
                'rosemary',
                'bay leaf',
                'ginger',
                'turmeric',
                'clove',
                'cardamom'
            ]
        ];

        // Check for matches in each category
        foreach ($categoryMap as $category => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($lowercaseName, $keyword) !== false) {
                    return $category;
                }
            }
        }

        // Default category
        return 'other';
    }

    /**
     * Mark a grocery item as purchased or unpurchased
     */
    public function toggleItemPurchased(int $itemId, bool $isPurchased): bool
    {
        try {
            $item = GroceryItem::findOrFail($itemId);
            $item->is_purchased = $isPurchased;
            $item->save();

            return true;
        } catch (\Exception $e) {
            Log::error("Error toggling grocery item purchased status", [
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Convert grocery list to WhatsApp-friendly format
     */
    public function formatGroceryListForWhatsApp(GroceryList $groceryList): string
    {
        $items = $groceryList->items()->orderBy('category')->get();

        if ($items->isEmpty()) {
            return "Your grocery list is empty.";
        }

        $message = "🛒 *{$groceryList->title}* 🛒\n\n";

        // Group items by category
        $categorizedItems = $items->groupBy('category');

        foreach ($categorizedItems as $category => $categoryItems) {
            $message .= "*" . ucfirst($category) . "*\n";

            foreach ($categoryItems as $item) {
                $checkbox = $item->is_purchased ? "✅" : "⬜";
                $quantity = !empty($item->quantity) ? " - {$item->quantity}" : "";
                $message .= "{$checkbox} {$item->name}{$quantity}\n";
            }

            $message .= "\n";
        }

        // Add usage instructions
        $message .= "To mark an item as purchased, reply with:\n";
        $message .= "'bought [item number]' or 'bought [item name]'\n\n";
        $message .= "To get this list again, type 'grocery' anytime.";

        return $message;
    }

    /**
     * Process a grocery list-related command
     */
    public function processGroceryCommand(User $user, string $command): ?string
    {
        $commandParts = explode(' ', strtolower(trim($command)), 2);
        $action = $commandParts[0] ?? '';
        $parameter = $commandParts[1] ?? '';

        switch ($action) {
            case 'grocery':
                return $this->handleGroceryListRequest($user, $parameter);

            case 'bought':
            case 'purchased':
                return $this->handleMarkAsPurchased($user, $parameter);

            case 'reset':
                if ($parameter === 'grocery') {
                    return $this->handleResetGroceryList($user);
                }
                break;
        }

        return null;
    }

    /**
     * Handle request to show grocery list
     */
    protected function handleGroceryListRequest(User $user, string $parameter): string
    {
        // Check if user wants a specific grocery list
        $specificDay = null;
        if (!empty($parameter)) {
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

            if (in_array($parameter, $days)) {
                $specificDay = $parameter;
            }
        }

        // Get or generate grocery list
        $groceryList = GroceryList::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$groceryList || ($specificDay !== null)) {
            // Generate new list if none exists or a specific day is requested
            $period = ($specificDay !== null) ? 'day' : 'week';
            $days = ($specificDay !== null) ? [$specificDay] : null;

            $groceryList = $this->generateGroceryList($user, $period, $days);

            if (!$groceryList) {
                return "I couldn't generate a grocery list. Please make sure you have an active diet plan.";
            }
        }

        return $this->formatGroceryListForWhatsApp($groceryList);
    }

    /**
     * Handle marking an item as purchased
     */
    protected function handleMarkAsPurchased(User $user, string $parameter): string
    {
        if (empty($parameter)) {
            return "Please specify which item you purchased, e.g., 'bought apples' or 'bought 1' for the first item.";
        }

        $activeList = GroceryList::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$activeList) {
            return "You don't have an active grocery list. Type 'grocery' to create one.";
        }

        // Check if parameter is a number
        if (is_numeric($parameter)) {
            // Get item by position in list
            $allItems = $activeList->items()->orderBy('category')->orderBy('id')->get();
            $index = (int) $parameter - 1;

            if (isset($allItems[$index])) {
                $item = $allItems[$index];
                $this->toggleItemPurchased($item->id, true);

                return "✅ Marked '{$item->name}' as purchased!";
            } else {
                return "Item #{$parameter} not found in your grocery list.";
            }
        } else {
            // Try to find by name
            $items = $activeList->items()
                ->where('name', 'like', "%{$parameter}%")
                ->get();

            if ($items->isEmpty()) {
                return "Couldn't find an item matching '{$parameter}' in your grocery list.";
            } elseif ($items->count() === 1) {
                $item = $items->first();
                $this->toggleItemPurchased($item->id, true);

                return "✅ Marked '{$item->name}' as purchased!";
            } else {
                $this->toggleItemPurchased($items->first()->id, true);

                return "✅ Marked '{$items->first()->name}' as purchased! (found multiple matches)";
            }
        }
    }

    /**
     * Handle resetting a grocery list (marking all unpurchased)
     */
    protected function handleResetGroceryList(User $user): string
    {
        $activeList = GroceryList::where('user_id', $user->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$activeList) {
            return "You don't have an active grocery list to reset.";
        }

        // Reset all items to unpurchased
        $activeList->items()->update(['is_purchased' => false]);

        return "🔄 Your grocery list has been reset. All items are now unmarked.";
    }
}