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
        Schema::table('subscription_plans', function (Blueprint $table) {
            // Drop existing price columns if they exist
            if (Schema::hasColumn('subscription_plans', 'monthly_price')) {
                $table->dropColumn('monthly_price');
            }
            if (Schema::hasColumn('subscription_plans', 'quarterly_price')) {
                $table->dropColumn('quarterly_price');
            }
            if (Schema::hasColumn('subscription_plans', 'annual_price')) {
                $table->dropColumn('annual_price');
            }
            
            // Add new columns
            $table->enum('plan_type', ['one_time', 'recurring'])->default('recurring')->after('description');
            $table->string('payment_provider')->nullable()->after('is_active');
            $table->json('payment_provider_plans')->nullable()->after('payment_provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['plan_type', 'payment_provider', 'payment_provider_plans']);
            
            // Add back the original columns
            $table->decimal('monthly_price', 10, 2)->nullable();
            $table->decimal('quarterly_price', 10, 2)->nullable();
            $table->decimal('annual_price', 10, 2)->nullable();
        });
    }
};