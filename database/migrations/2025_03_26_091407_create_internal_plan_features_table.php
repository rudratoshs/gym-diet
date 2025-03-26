<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('internal_plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_subscription_plan_id')
                  ->constrained('gym_subscription_plans')
                  ->onDelete('cascade');

            $table->foreignId('subscription_feature_id')
                  ->constrained('subscription_features')
                  ->onDelete('cascade');

            $table->string('value')->nullable(); // Optional: stringified boolean or free-form value
            $table->integer('limit')->nullable(); // Optional: numeric limits

            $table->timestamps();

            $table->unique(['gym_subscription_plan_id', 'subscription_feature_id'], 'unique_internal_feature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_plan_features');
    }
};