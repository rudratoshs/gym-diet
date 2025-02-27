<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            // Phase 2: Health Assessment fields
            $table->text('health_details')->nullable()->after('health_conditions');
            $table->text('organ_recovery_details')->nullable()->after('recovery_needs');

            // Phase 3: Diet Preferences fields
            $table->json('cuisine_preferences')->nullable()->after('diet_type');
            $table->string('meal_timing', 20)->nullable()->after('cuisine_preferences');
            $table->json('food_restrictions')->nullable()->after('meal_timing');

            // Phase 4: Lifestyle fields
            $table->string('daily_schedule', 20)->nullable()->after('food_restrictions');
            $table->string('cooking_capability', 20)->nullable()->after('daily_schedule');
            $table->string('exercise_routine', 20)->nullable()->after('cooking_capability');
            $table->string('stress_sleep', 30)->nullable()->after('exercise_routine');

            // Phase 5: Goal fields
            $table->string('primary_goal', 20)->nullable()->after('stress_sleep');
            $table->string('goal_timeline', 20)->nullable()->after('primary_goal');
            $table->string('measurement_preference', 20)->nullable()->after('goal_timeline');

            // Phase 6: Plan Customization
            $table->string('plan_type', 20)->nullable()->after('measurement_preference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            // Drop the added columns
            $table->dropColumn([
                'health_details',
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
                'plan_type'
            ]);
        });
    }
};