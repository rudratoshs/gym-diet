<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('meals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meal_plan_id')->constrained()->onDelete('cascade');
            $table->enum('meal_type', [
                'breakfast',
                'morning_snack',
                'lunch',
                'afternoon_snack',
                'dinner',
                'evening_snack',
                'pre_workout',
                'post_workout'
            ]);
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('calories')->nullable();
            $table->integer('protein_grams')->nullable();
            $table->integer('carbs_grams')->nullable();
            $table->integer('fats_grams')->nullable();
            $table->time('time_of_day')->nullable();
            $table->json('recipes')->nullable();
            $table->timestamps();

            // Each meal plan should have only one meal of each type
            $table->unique(['meal_plan_id', 'meal_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meals');
    }
};
