<?php

// database/seeders/DietPlanSeeder.php
namespace Database\Seeders;

use App\Models\DietPlan;
use App\Models\Meal;
use App\Models\MealPlan;
use App\Models\User;
use Illuminate\Database\Seeder;

class DietPlanSeeder extends Seeder
{
    public function run()
    {
        // Find a client
        $client = User::role('client')->first();

        if (!$client) {
            $this->command->info('No client found. Skipping diet plan seeding.');
            return;
        }

        // Find a dietitian
        $dietitian = User::role('dietitian')->first();

        if (!$dietitian) {
            $dietitian = User::role('admin')->first(); // Use admin as fallback
        }

        // Create a diet plan
        $dietPlan = DietPlan::create([
            'client_id' => $client->id,
            'created_by' => $dietitian->id,
            'title' => 'Weight Loss Plan',
            'description' => 'A balanced diet plan focused on sustainable weight loss.',
            'daily_calories' => 1800,
            'protein_grams' => 135,
            'carbs_grams' => 180,
            'fats_grams' => 60,
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addMonths(3),
        ]);

        // Create meal plans for each day of the week
        $daysOfWeek = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

        foreach ($daysOfWeek as $day) {
            $mealPlan = MealPlan::create([
                'diet_plan_id' => $dietPlan->id,
                'day_of_week' => $day,
            ]);

            // Create meals for each meal plan
            $this->createMeals($mealPlan->id, $day);
        }
    }

    /**
     * Create meals for a meal plan.
     */
    private function createMeals($mealPlanId, $day)
    {
        $meals = [
            [
                'meal_type' => 'breakfast',
                'title' => 'Oatmeal with Berries',
                'description' => 'Rolled oats cooked with almond milk, topped with mixed berries and a tablespoon of honey.',
                'calories' => 350,
                'protein_grams' => 15,
                'carbs_grams' => 65,
                'fats_grams' => 5,
                'time_of_day' => '08:00',
                'recipes' => [
                    [
                        'name' => 'Oatmeal Base',
                        'ingredients' => [
                            '1/2 cup rolled oats',
                            '1 cup almond milk',
                            '1 tablespoon honey',
                            '1/2 cup mixed berries'
                        ],
                        'instructions' => 'Cook oats with almond milk for 5 minutes. Top with berries and honey.'
                    ]
                ]
            ],
            [
                'meal_type' => 'morning_snack',
                'title' => 'Greek Yogurt with Almonds',
                'description' => 'Plain Greek yogurt with a handful of almonds.',
                'calories' => 200,
                'protein_grams' => 20,
                'carbs_grams' => 10,
                'fats_grams' => 10,
                'time_of_day' => '11:00',
                'recipes' => [
                    [
                        'name' => 'Yogurt Snack',
                        'ingredients' => [
                            '1 cup plain Greek yogurt',
                            '15 almonds'
                        ],
                        'instructions' => 'Mix yogurt and almonds.'
                    ]
                ]
            ],
            [
                'meal_type' => 'lunch',
                'title' => 'Grilled Chicken Salad',
                'description' => 'Grilled chicken breast on a bed of mixed greens with olive oil and lemon dressing.',
                'calories' => 450,
                'protein_grams' => 40,
                'carbs_grams' => 25,
                'fats_grams' => 20,
                'time_of_day' => '13:30',
                'recipes' => [
                    [
                        'name' => 'Grilled Chicken',
                        'ingredients' => [
                            '150g chicken breast',
                            '1 tsp olive oil',
                            'Salt and pepper to taste'
                        ],
                        'instructions' => 'Season chicken and grill for 6-8 minutes per side.'
                    ],
                    [
                        'name' => 'Salad Base',
                        'ingredients' => [
                            '2 cups mixed greens',
                            '1 tablespoon olive oil',
                            '1 tablespoon lemon juice',
                            'Salt and pepper to taste'
                        ],
                        'instructions' => 'Toss greens with olive oil and lemon juice. Top with grilled chicken.'
                    ]
                ]
            ],
            [
                'meal_type' => 'afternoon_snack',
                'title' => 'Apple and String Cheese',
                'description' => 'Medium apple with a piece of string cheese.',
                'calories' => 200,
                'protein_grams' => 7,
                'carbs_grams' => 25,
                'fats_grams' => 8,
                'time_of_day' => '16:00',
                'recipes' => null
            ],
            [
                'meal_type' => 'dinner',
                'title' => 'Baked Salmon with Roasted Vegetables',
                'description' => 'Baked salmon fillet with roasted Brussels sprouts and sweet potatoes.',
                'calories' => 500,
                'protein_grams' => 35,
                'carbs_grams' => 45,
                'fats_grams' => 20,
                'time_of_day' => '19:00',
                'recipes' => [
                    [
                        'name' => 'Baked Salmon',
                        'ingredients' => [
                            '150g salmon fillet',
                            '1 tablespoon olive oil',
                            'Lemon juice',
                            'Salt and pepper to taste'
                        ],
                        'instructions' => 'Season salmon and bake at 200°C for 12-15 minutes.'
                    ],
                    [
                        'name' => 'Roasted Vegetables',
                        'ingredients' => [
                            '1 cup Brussels sprouts, halved',
                            '1 small sweet potato, cubed',
                            '1 tablespoon olive oil',
                            'Salt and pepper to taste'
                        ],
                        'instructions' => 'Toss vegetables with oil, salt, and pepper. Roast at 200°C for 25 minutes.'
                    ]
                ]
            ],
            [
                'meal_type' => 'evening_snack',
                'title' => 'Protein Shake',
                'description' => 'Protein shake with almond milk.',
                'calories' => 150,
                'protein_grams' => 25,
                'carbs_grams' => 5,
                'fats_grams' => 3,
                'time_of_day' => '21:00',
                'recipes' => [
                    [
                        'name' => 'Protein Shake',
                        'ingredients' => [
                            '1 scoop protein powder',
                            '1 cup almond milk'
                        ],
                        'instructions' => 'Mix protein powder with almond milk.'
                    ]
                ]
            ]
        ];

        // Create different variations for different days
        if ($day === 'tuesday' || $day === 'thursday' || $day === 'saturday') {
            $meals[0]['title'] = 'Protein Pancakes';
            $meals[0]['description'] = 'Protein pancakes topped with berries and a drizzle of honey.';

            $meals[2]['title'] = 'Turkey Wrap';
            $meals[2]['description'] = 'Turkey slices with avocado and vegetables in a whole wheat wrap.';

            $meals[4]['title'] = 'Vegetable Stir Fry with Tofu';
            $meals[4]['description'] = 'Tofu and mixed vegetables stir-fried with low-sodium soy sauce.';
        }

        // Create meals
        foreach ($meals as $mealData) {
            Meal::create(array_merge($mealData, ['meal_plan_id' => $mealPlanId]));
        }
    }
}