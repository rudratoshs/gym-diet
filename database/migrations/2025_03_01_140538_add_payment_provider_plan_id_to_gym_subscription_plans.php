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
        Schema::table('gym_subscription_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('gym_subscription_plans', 'payment_provider_plan_id')) {
                $table->string('payment_provider_plan_id')->nullable();
            }

            if (!Schema::hasColumn('gym_subscription_plans', 'billing_cycle')) {
                $table->enum('billing_cycle', ['monthly', 'quarterly', 'annual']);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('gym_subscription_plans', function (Blueprint $table) {
            if (Schema::hasColumn('gym_subscription_plans', 'payment_provider_plan_id')) {
                $table->dropColumn('payment_provider_plan_id');
            }

            if (Schema::hasColumn('gym_subscription_plans', 'billing_cycle')) {
                $table->dropColumn('billing_cycle');
            }
        });
    }
};
