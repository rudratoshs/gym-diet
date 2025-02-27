<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'age',
        'gender',
        'height',
        'current_weight',
        'target_weight',
        'activity_level',
        'diet_type',
        'health_conditions',
        'allergies',
        'recovery_needs',
        'meal_preferences',
    ];

    protected $casts = [
        'health_conditions' => 'array',
        'allergies' => 'array',
        'recovery_needs' => 'array',
        'meal_preferences' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Calculate BMI
    public function getBmiAttribute()
    {
        if ($this->height && $this->current_weight) {
            // Height in meters (convert from cm)
            $heightInMeters = $this->height / 100;

            // BMI formula: weight (kg) / (height (m))^2
            return round($this->current_weight / ($heightInMeters * $heightInMeters), 1);
        }

        return null;
    }

    // Calculate BMR (Basal Metabolic Rate) using Mifflin-St Jeor Equation
    public function getBmrAttribute()
    {
        if ($this->height && $this->current_weight && $this->age && $this->gender) {
            if ($this->gender === 'male') {
                return round((10 * $this->current_weight) + (6.25 * $this->height) - (5 * $this->age) + 5);
            } else {
                return round((10 * $this->current_weight) + (6.25 * $this->height) - (5 * $this->age) - 161);
            }
        }

        return null;
    }

    // Calculate daily calorie needs based on activity level
    public function getDailyCaloriesAttribute()
    {
        if ($this->bmr) {
            $activityMultipliers = [
                'sedentary' => 1.2,
                'lightly_active' => 1.375,
                'moderately_active' => 1.55,
                'very_active' => 1.725,
                'extremely_active' => 1.9,
            ];

            $multiplier = $activityMultipliers[$this->activity_level] ?? 1.2;

            return round($this->bmr * $multiplier);
        }

        return null;
    }
}