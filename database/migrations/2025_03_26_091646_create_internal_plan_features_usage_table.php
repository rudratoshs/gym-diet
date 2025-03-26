<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('internal_feature_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_subscription_id')
                  ->constrained('client_subscriptions')
                  ->onDelete('cascade');

            $table->foreignId('subscription_feature_id')
                  ->constrained('subscription_features')
                  ->onDelete('cascade');

            $table->integer('used')->default(0);
            $table->integer('limit')->nullable();
            $table->timestamp('reset_at')->nullable();

            $table->timestamps();

            $table->unique(['client_subscription_id', 'subscription_feature_id'], 'client_feature_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_feature_usage');
    }

};