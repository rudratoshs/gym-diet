<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AttributeSeeder extends Seeder
{
    public function run()
    {
        $attributes = [
            ['label' => 'Male', 'type' => 'gender', 'value' => 'male', 'description' => null],
            ['label' => 'Female', 'type' => 'gender', 'value' => 'female', 'description' => null],
            ['label' => 'Other', 'type' => 'gender', 'value' => 'other', 'description' => null],

            ['label' => 'Sedentary', 'type' => 'activity_level', 'value' => 'sedentary', 'description' => 'Little to no exercise'],
            ['label' => 'Lightly Active', 'type' => 'activity_level', 'value' => 'lightly_active', 'description' => 'Light exercise 1-3 days/week'],
            ['label' => 'Moderately Active', 'type' => 'activity_level', 'value' => 'moderately_active', 'description' => 'Moderate exercise 3-5 days/week'],
            ['label' => 'Very Active', 'type' => 'activity_level', 'value' => 'very_active', 'description' => 'Hard exercise 6-7 days/week'],
            ['label' => 'Extremely Active', 'type' => 'activity_level', 'value' => 'extremely_active', 'description' => 'Very hard exercise, physical job or training twice a day'],

            ['label' => 'Standard (No Restrictions)', 'type' => 'diet_type', 'value' => 'standard', 'description' => null],
            ['label' => 'Vegetarian', 'type' => 'diet_type', 'value' => 'vegetarian', 'description' => null],
            ['label' => 'Vegan', 'type' => 'diet_type', 'value' => 'vegan', 'description' => null],
            ['label' => 'Pescatarian', 'type' => 'diet_type', 'value' => 'pescatarian', 'description' => null],
            ['label' => 'Ketogenic', 'type' => 'diet_type', 'value' => 'keto', 'description' => null],
            ['label' => 'Paleo', 'type' => 'diet_type', 'value' => 'paleo', 'description' => null],
            ['label' => 'Mediterranean', 'type' => 'diet_type', 'value' => 'mediterranean', 'description' => null],

            ['label' => 'None (Health Condition)', 'type' => 'health_condition', 'value' => 'none_health', 'description' => null],
            ['label' => 'Diabetes', 'type' => 'health_condition', 'value' => 'diabetes', 'description' => null],
            ['label' => 'Hypertension', 'type' => 'health_condition', 'value' => 'hypertension', 'description' => null],
            ['label' => 'Heart Disease', 'type' => 'health_condition', 'value' => 'heart_disease', 'description' => null],
            ['label' => 'High Cholesterol', 'type' => 'health_condition', 'value' => 'high_cholesterol', 'description' => null],
            ['label' => 'Thyroid Issues', 'type' => 'health_condition', 'value' => 'thyroid', 'description' => null],
            ['label' => 'Arthritis', 'type' => 'health_condition', 'value' => 'arthritis', 'description' => null],
            ['label' => 'Digestive Disorder', 'type' => 'health_condition', 'value' => 'digestive_disorder', 'description' => null],

            ['label' => 'None (Allergy)', 'type' => 'allergy', 'value' => 'none_allergy', 'description' => null],
            ['label' => 'Gluten', 'type' => 'allergy', 'value' => 'gluten', 'description' => null],
            ['label' => 'Dairy', 'type' => 'allergy', 'value' => 'dairy', 'description' => null],
            ['label' => 'Nuts', 'type' => 'allergy', 'value' => 'nuts', 'description' => null],
            ['label' => 'Soy', 'type' => 'allergy', 'value' => 'soy', 'description' => null],
            ['label' => 'Shellfish', 'type' => 'allergy', 'value' => 'shellfish', 'description' => null],
            ['label' => 'Eggs', 'type' => 'allergy', 'value' => 'eggs', 'description' => null],
            ['label' => 'Fish', 'type' => 'allergy', 'value' => 'fish', 'description' => null],

            ['label' => 'Weight Loss', 'type' => 'recovery_need', 'value' => 'weight_loss', 'description' => null],
            ['label' => 'Muscle Gain', 'type' => 'recovery_need', 'value' => 'muscle_gain', 'description' => null],
            ['label' => 'Performance Improvement', 'type' => 'recovery_need', 'value' => 'performance', 'description' => null],
            ['label' => 'Injury Recovery', 'type' => 'recovery_need', 'value' => 'injury_recovery', 'description' => null],
            ['label' => 'Energy Enhancement', 'type' => 'recovery_need', 'value' => 'energy', 'description' => null],
            ['label' => 'Stress Reduction', 'type' => 'recovery_need', 'value' => 'stress_reduction', 'description' => null],
            ['label' => 'Sleep Improvement', 'type' => 'recovery_need', 'value' => 'sleep_improvement', 'description' => null],

            ['label' => 'High Protein', 'type' => 'meal_preference', 'value' => 'high_protein', 'description' => null],
            ['label' => 'Low Carb', 'type' => 'meal_preference', 'value' => 'low_carb', 'description' => null],
            ['label' => 'High Carb', 'type' => 'meal_preference', 'value' => 'high_carb', 'description' => null],
            ['label' => 'Low Fat', 'type' => 'meal_preference', 'value' => 'low_fat', 'description' => null],
            ['label' => 'Low Calorie', 'type' => 'meal_preference', 'value' => 'low_calorie', 'description' => null],
            ['label' => 'Intermittent Fasting', 'type' => 'meal_preference', 'value' => 'fasting', 'description' => null],
            ['label' => 'Small Frequent Meals', 'type' => 'meal_preference', 'value' => 'small_frequent', 'description' => null],
        ];

        DB::table('attributes')->insert($attributes);
    }
}