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
}
