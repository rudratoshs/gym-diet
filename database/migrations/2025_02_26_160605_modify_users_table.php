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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->unique();
            $table->string('whatsapp_phone')->nullable()->unique();
            $table->string('whatsapp_id')->nullable()->unique();
            $table->enum('status', ['active', 'inactive', 'pending'])->default('pending');
            $table->string('stripe_customer_id')->nullable();
            $table->string('razorpay_customer_id')->nullable();
        });
    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone',
                'whatsapp_phone',
                'whatsapp_id',
                'status',
                'stripe_customer_id',
                'razorpay_customer_id'
            ]);
        });
    }
};
