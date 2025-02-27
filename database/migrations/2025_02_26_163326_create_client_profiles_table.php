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
        Schema::create('client_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('age')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->decimal('height', 5, 2)->nullable(); // in cm
            $table->decimal('current_weight', 5, 2)->nullable(); // in kg
            $table->decimal('target_weight', 5, 2)->nullable(); // in kg
            $table->enum('activity_level', [
                'sedentary',
                'lightly_active',
                'moderately_active',
                'very_active',
                'extremely_active'
            ])->nullable();
            $table->enum('diet_type', [
                'omnivore',
                'vegetarian',
                'vegan',
                'pescatarian',
                'flexitarian',
                'keto',
                'paleo',
                'other'
            ])->nullable();
            $table->json('health_conditions')->nullable();
            $table->json('allergies')->nullable();
            $table->json('recovery_needs')->nullable();
            $table->json('meal_preferences')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_profiles');
    }
};
