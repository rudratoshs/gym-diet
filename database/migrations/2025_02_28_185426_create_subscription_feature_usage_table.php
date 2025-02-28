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
        Schema::create('subscription_feature_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_feature_id')->constrained('subscription_features')->cascadeOnDelete();
            $table->integer('current_usage')->default(0);
            $table->integer('limit')->nullable(); // null means unlimited
            $table->timestamp('reset_at')->nullable(); // when usage resets
            $table->timestamps();

            // Unique constraint to prevent duplicate usage tracking
            $table->unique(['gym_id', 'subscription_feature_id'], 'unique_feature_usage_for_gym');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_feature_usage');
    }
};