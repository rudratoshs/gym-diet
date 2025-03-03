<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('goal_trackings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('goal_type')->default('weight'); // weight, measurement, habit, etc.
            $table->decimal('start_value', 10, 2)->nullable();
            $table->decimal('target_value', 10, 2)->nullable();
            $table->decimal('current_value', 10, 2)->nullable();
            $table->date('start_date');
            $table->date('target_date')->nullable();
            $table->enum('status', ['in_progress', 'achieved', 'abandoned'])->default('in_progress');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_trackings');
    }
};