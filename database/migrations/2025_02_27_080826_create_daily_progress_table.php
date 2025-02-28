<?php
// database/migrations/YYYY_MM_DD_create_daily_progress_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDailyProgressTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('daily_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->date('tracking_date');
            $table->integer('water_intake')->nullable()->comment('ml of water consumed');
            $table->integer('meals_completed')->default(0);
            $table->integer('total_meals')->default(3);
            $table->integer('calories_consumed')->nullable();
            $table->boolean('exercise_done')->default(false);
            $table->integer('exercise_duration')->nullable()->comment('minutes');
            $table->enum('energy_level', ['low', 'moderate', 'high'])->nullable()->default('moderate');
            $table->enum('mood', ['poor', 'fair', 'good', 'excellent'])->nullable()->default('good');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->unique(['user_id', 'tracking_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('daily_progress');
    }
}
