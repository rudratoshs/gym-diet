<?php
// database/migrations/YYYY_MM_DD_create_client_feedback_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateClientFeedbackTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('client_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('diet_plan_id')->nullable();
            $table->unsignedBigInteger('meal_id')->nullable();
            $table->enum('feedback_type', ['general', 'meal', 'plan', 'system', 'dietitian'])->default('general');
            $table->tinyInteger('rating')->nullable()->comment('Scale 1-5');
            $table->text('comments')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('diet_plan_id')->references('id')->on('diet_plans')->onDelete('set null');
            $table->foreign('meal_id')->references('id')->on('meals')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('client_feedback');
    }
}
