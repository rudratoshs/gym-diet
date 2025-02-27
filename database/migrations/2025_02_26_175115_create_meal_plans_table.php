<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('meal_plans', function (Blueprint $table) {
        $table->id();
        $table->foreignId('diet_plan_id')->constrained()->onDelete('cascade');
        $table->enum('day_of_week', [
            'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'
        ]);
        $table->timestamps();
        
        // Each diet plan should have only one meal plan per day
        $table->unique(['diet_plan_id', 'day_of_week']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meal_plans');
    }
};
