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
        Schema::create('ai_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gym_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('provider')->default('gemini'); // 'gemini', 'openai', 'claude', etc.
            $table->text('api_key')->nullable(); // Will be encrypted
            $table->text('api_url')->nullable();
            $table->string('model')->nullable(); // e.g., 'gpt-4o', 'gemini-pro', etc.
            $table->json('additional_settings')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_configurations');
    }
};
