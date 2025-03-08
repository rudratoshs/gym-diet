<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('assessment_sessions', function (Blueprint $table) {
            $table->string('assessment_type')->default('quick')->after('user_id'); // Default to quick
        });
    }

    public function down()
    {
        Schema::table('assessment_sessions', function (Blueprint $table) {
            $table->dropColumn('assessment_type');
        });
    }
};