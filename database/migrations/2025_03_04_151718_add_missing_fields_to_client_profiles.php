<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            // Add missing fields from comprehensive assessment
            if (!Schema::hasColumn('client_profiles', 'medications')) {
                $table->json('medications')->nullable();
            }
            if (!Schema::hasColumn('client_profiles', 'medication_details')) {
                $table->text('medication_details')->nullable();
            }
            if (!Schema::hasColumn('client_profiles', 'commitment_level')) {
                $table->string('commitment_level')->nullable();
            }
            if (!Schema::hasColumn('client_profiles', 'additional_requests')) {
                $table->text('additional_requests')->nullable();
            }
            if (!Schema::hasColumn('client_profiles', 'meal_variety')) {
                $table->string('meal_variety')->nullable();
            }
        });
    }

    public function down()
    {
        Schema::table('client_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'medications',
                'medication_details',
                'commitment_level',
                'additional_requests',
                'meal_variety'
            ]);
        });
    }
};