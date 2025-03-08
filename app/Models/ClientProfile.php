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
        'health_details',
        'allergies',
        'recovery_needs',
        'organ_recovery_details',
        'cuisine_preferences',
        'meal_timing',
        'food_restrictions',
        'daily_schedule',
        'cooking_capability',
        'exercise_routine',
        'stress_sleep',
        'primary_goal',
        'goal_timeline',
        'measurement_preference',
        'plan_type',
        'meal_preferences',
        'country',
        'state',
        'city',
        'medications',
        'medication_details',
        'commitment_level',
        'additional_requests',
        'meal_variety',
        // 🔹 New Fields from Migration
        'body_type',
        'water_intake',
        'meal_portion_size',
        'favorite_foods',
        'disliked_foods',
        'past_medical_history',
        'cooking_style',
        'grocery_access',
        'meal_budget',
        'exercise_timing',
        'sleep_hours',
        'motivation',
        'past_attempts',
        'detail_level',
        'recipe_complexity',
        'organ_recovery_focus',
        'religion_diet',
        'fasting_details',
        'work_type',
        'cooking_time',
        'timeline',
    ];

    protected $casts = [
        'health_conditions' => 'array',
        'allergies' => 'array',
        'recovery_needs' => 'array',
        'cuisine_preferences' => 'array',
        'food_restrictions' => 'array',
        'meal_preferences' => 'array',
        'medications' => 'array',
        'favorite_foods' => 'array',
        'disliked_foods' => 'array',
        'past_medical_history' => 'array',
        'organ_recovery_focus' => 'array',
        'religion_diet' => 'array',
    ];
    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Calculate BMI
     */
    public function getBmiAttribute()
    {
        if (!$this->height || !$this->current_weight) {
            return null;
        }

        $heightInMeters = $this->height / 100;
        return round($this->current_weight / ($heightInMeters * $heightInMeters), 1);
    }

    /**
     * Get BMI category
     */
    public function getBmiCategoryAttribute()
    {
        $bmi = $this->bmi;

        if (!$bmi) {
            return null;
        }

        return match (true) {
            $bmi < 18.5 => 'underweight',
            $bmi < 25 => 'normal',
            $bmi < 30 => 'overweight',
            default => 'obese',
        };
    }

    /**
     * Calculate BMR (Basal Metabolic Rate) using Mifflin-St Jeor Equation
     */
    public function getBmrAttribute()
    {
        if (!$this->height || !$this->current_weight || !$this->age || !$this->gender) {
            return null;
        }

        return round(match ($this->gender) {
            'male' => (10 * $this->current_weight) + (6.25 * $this->height) - (5 * $this->age) + 5,
            'female' => (10 * $this->current_weight) + (6.25 * $this->height) - (5 * $this->age) - 161,
            default => null
        });
    }

    /**
     * Calculate daily calorie needs based on activity level
     */
    public function getDailyCaloriesAttribute()
    {
        if (!$this->bmr) {
            return null;
        }

        $activityMultipliers = [
            'sedentary' => 1.2,
            'lightly_active' => 1.375,
            'moderately_active' => 1.55,
            'very_active' => 1.725,
            'extremely_active' => 1.9,
        ];

        return round($this->bmr * ($activityMultipliers[$this->activity_level] ?? 1.2));
    }

    /**
     * Get weight difference (target - current)
     */
    public function getWeightDifferenceAttribute()
    {
        if (!$this->current_weight || !$this->target_weight) {
            return 0;
        }

        return $this->target_weight - $this->current_weight;
    }

    /**
     * Get weight goal type
     */
    public function getWeightGoalTypeAttribute()
    {
        $diff = $this->weight_difference;

        return match (true) {
            $diff < -1 => 'loss',
            $diff > 1 => 'gain',
            default => 'maintenance',
        };
    }

    /**
     * Get displayable meal timing
     */
    public function getMealTimingDisplayAttribute()
    {
        return match ($this->meal_timing) {
            'traditional' => 'Traditional (3 meals)',
            'frequent' => 'Small frequent meals (5-6)',
            'intermittent' => 'Intermittent fasting',
            'omad' => 'One meal a day (OMAD)',
            'flexible' => 'Flexible pattern',
            default => 'Traditional (3 meals)',
        };
    }

    /**
     * Get displayable diet type
     */
    public function getDietTypeDisplayAttribute()
    {
        return match ($this->diet_type) {
            'omnivore' => 'Omnivore',
            'vegetarian' => 'Vegetarian',
            'vegan' => 'Vegan',
            'pescatarian' => 'Pescatarian',
            'flexitarian' => 'Flexitarian',
            'keto' => 'Keto',
            'paleo' => 'Paleo',
            default => 'Omnivore',
        };
    }

    /**
     * Get displayable activity level
     */
    public function getActivityLevelDisplayAttribute()
    {
        return match ($this->activity_level) {
            'sedentary' => 'Sedentary',
            'lightly_active' => 'Lightly active',
            'moderately_active' => 'Moderately active',
            'very_active' => 'Very active',
            'extremely_active' => 'Extremely active',
            default => 'Moderately active',
        };
    }

    /**
     * Get displayable stress and sleep habits
     */
    public function getStressSleepDisplayAttribute()
    {
        return match ($this->stress_sleep) {
            'low' => 'Low stress, good sleep',
            'moderate' => 'Moderate stress, average sleep',
            'high' => 'High stress, poor sleep',
            default => 'Not specified',
        };
    }

    /**
     * Get displayable primary goal
     */
    public function getPrimaryGoalDisplayAttribute()
    {
        return match ($this->primary_goal) {
            'weight_loss' => 'Weight Loss',
            'muscle_gain' => 'Muscle Gain',
            'maintenance' => 'Weight Maintenance',
            'recovery' => 'Health Recovery',
            'custom' => 'Custom Goal',
            default => 'Not specified',
        };
    }

    /**
     * Get displayable plan type
     */
    public function getPlanTypeDisplayAttribute()
    {
        return match ($this->plan_type) {
            'standard' => 'Standard',
            'premium' => 'Premium',
            'custom' => 'Customized Plan',
            default => 'Standard',
        };
    }

    public function getWaterIntakeDisplayAttribute()
    {
        return "{$this->water_intake} liters/day";
    }
}