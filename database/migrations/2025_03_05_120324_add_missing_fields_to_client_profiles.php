<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->string('body_type')->nullable()->after('target_weight'); // Ectomorph, Mesomorph, Endomorph
            $table->decimal('water_intake', 5, 2)->nullable()->after('body_type'); // Liters per day
            $table->string('meal_portion_size')->nullable()->after('water_intake'); // Small, Medium, Large
            $table->json('favorite_foods')->nullable()->after('meal_portion_size'); 
            $table->json('disliked_foods')->nullable()->after('favorite_foods'); 
            $table->json('past_medical_history')->nullable()->after('health_conditions'); 
            $table->string('cooking_style')->nullable()->after('cooking_capability'); 
            $table->string('grocery_access')->nullable()->after('cooking_style'); // Easy, Moderate, Difficult
            $table->string('meal_budget')->nullable()->after('grocery_access'); // Strict, Flexible, etc.
            $table->string('exercise_timing')->nullable()->after('exercise_routine'); // Morning, Evening, etc.
            $table->string('sleep_hours')->nullable()->after('stress_sleep'); // Example: "6-7 hours"
            $table->string('motivation')->nullable()->after('primary_goal'); // Health, Fitness, etc.
            $table->string('past_attempts')->nullable()->after('motivation'); // Success, Failure, First Attempt
            $table->string('detail_level')->nullable()->after('plan_type'); // General, Specific
            $table->string('recipe_complexity')->nullable()->after('detail_level'); // Simple, Moderate, Complex
            $table->json('organ_recovery_focus')->nullable()->after('recovery_needs'); // JSON for organ-specific recovery needs
            $table->json('religion_diet')->nullable()->after('food_restrictions'); // Kosher, Halal, Jain, etc.
            $table->text('fasting_details')->nullable()->after('meal_timing'); 
            $table->string('work_type')->nullable()->after('daily_schedule'); // Desk job, physically demanding, etc.
            $table->string('cooking_time')->nullable()->after('cooking_capability'); // 15 min, 30 min, etc.
            $table->string('timeline')->nullable()->after('goal_timeline'); // Short-term, Long-term, Ongoing
        });
    }

    public function down()
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->dropColumn([
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
            ]);
        });
    }
};