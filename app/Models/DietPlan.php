<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DietPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'created_by',
        'title',
        'description',
        'daily_calories',
        'protein_grams',
        'carbs_grams',
        'fats_grams',
        'status',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mealPlans()
    {
        return $this->hasMany(MealPlan::class);
    }

    // Calculate macro percentages
    public function getMacroPercentagesAttribute()
    {
        $total = 0;
        $percentages = [];

        if ($this->protein_grams) {
            $proteinCalories = $this->protein_grams * 4; // 4 calories per gram
            $total += $proteinCalories;
            $percentages['protein'] = $proteinCalories;
        }

        if ($this->carbs_grams) {
            $carbsCalories = $this->carbs_grams * 4; // 4 calories per gram
            $total += $carbsCalories;
            $percentages['carbs'] = $carbsCalories;
        }

        if ($this->fats_grams) {
            $fatsCalories = $this->fats_grams * 9; // 9 calories per gram
            $total += $fatsCalories;
            $percentages['fats'] = $fatsCalories;
        }

        if ($total > 0) {
            $percentages['protein'] = round(($percentages['protein'] ?? 0) / $total * 100);
            $percentages['carbs'] = round(($percentages['carbs'] ?? 0) / $total * 100);
            $percentages['fats'] = round(($percentages['fats'] ?? 0) / $total * 100);
        }

        return $percentages;
    }
}

