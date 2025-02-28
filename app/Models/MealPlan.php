<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MealPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'diet_plan_id',
        'day_of_week',
        'total_calories',
        'total_protein',
        'total_carbs',
        'total_fats',
        'generation_status'

    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'total_calories' => 'integer',
        'total_protein' => 'integer',
        'total_carbs' => 'integer',
        'total_fats' => 'integer',
    ];

    public function dietPlan()
    {
        return $this->belongsTo(DietPlan::class);
    }

    public function meals()
    {
        return $this->hasMany(Meal::class);
    }

    // Get total calories for this meal plan
    public function getTotalCaloriesAttribute()
    {
        return $this->meals->sum('calories');
    }

    // Get total macros for this meal plan
    public function getTotalProteinAttribute()
    {
        return $this->meals->sum('protein_grams');
    }

    public function getTotalCarbsAttribute()
    {
        return $this->meals->sum('carbs_grams');
    }

    public function getTotalFatsAttribute()
    {
        return $this->meals->sum('fats_grams');
    }

    /**
     * Get formatted day of week
     */
    public function getDayOfWeekFormattedAttribute()
    {
        return ucfirst($this->day_of_week);
    }

    /**
     * Calculate nutritional totals from meals
     */
    public function calculateTotals()
    {
        $totals = [
            'calories' => 0,
            'protein' => 0,
            'carbs' => 0,
            'fats' => 0
        ];

        foreach ($this->meals as $meal) {
            $totals['calories'] += $meal->calories;
            $totals['protein'] += $meal->protein_grams;
            $totals['carbs'] += $meal->carbs_grams;
            $totals['fats'] += $meal->fats_grams;
        }

        $this->total_calories = $totals['calories'];
        $this->total_protein = $totals['protein'];
        $this->total_carbs = $totals['carbs'];
        $this->total_fats = $totals['fats'];
        $this->generation_status = 'completed';
        $this->save();

        return $totals;
    }
}
