<?php
// database/migrations/YYYY_MM_DD_create_goal_tracking_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGoalTrackingTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('goal_tracking', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->enum('goal_type', ['weight', 'measurement', 'health_marker', 'habit', 'fitness', 'other'])->default('weight');
            $table->decimal('target_value', 8, 2);
            $table->decimal('starting_value', 8, 2);
            $table->decimal('current_value', 8, 2);
            $table->string('unit', 20)->nullable();
            $table->date('target_date');
            $table->enum('status', ['in_progress', 'achieved', 'abandoned', 'recalibrated'])->default('in_progress');
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('goal_tracking');
    }
}

