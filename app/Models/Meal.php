<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meal extends Model
{
    use HasFactory;

    protected $fillable = [
        'meal_plan_id',
        'meal_type',
        'title',
        'description',
        'calories',
        'protein_grams',
        'carbs_grams',
        'fats_grams',
        'time_of_day',
        'recipes',
    ];

    protected $casts = [
        'time_of_day' => 'datetime:H:i',
        'recipes' => 'array',
    ];

    public function mealPlan()
    {
        return $this->belongsTo(MealPlan::class);
    }

    // Get the meal type display name
    public function getMealTypeDisplayAttribute()
    {
        $displayNames = [
            'breakfast' => 'Breakfast',
            'morning_snack' => 'Morning Snack',
            'lunch' => 'Lunch',
            'afternoon_snack' => 'Afternoon Snack',
            'dinner' => 'Dinner',
            'evening_snack' => 'Evening Snack',
            'pre_workout' => 'Pre-Workout',
            'post_workout' => 'Post-Workout',
        ];

        return $displayNames[$this->meal_type] ?? $this->meal_type;
    }

    /**
     * Get the nutrition information for the meal.
     */
    public function nutritionInfo()
    {
        return $this->hasOne(NutritionInfo::class);
    }

    /**
     * Get the structured ingredients for the meal.
     */
    public function ingredients()
    {
        return $this->hasMany(RecipeIngredient::class);
    }

    /**
     * Get the meal compliance records for the meal.
     */
    public function complianceRecords()
    {
        return $this->hasMany(MealCompliance::class);
    }

}
