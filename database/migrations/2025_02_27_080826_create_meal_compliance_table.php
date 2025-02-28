<?php
// database/migrations/YYYY_MM_DD_create_meal_compliance_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMealComplianceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('meal_compliance', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('meal_id');
            $table->date('tracking_date');
            $table->boolean('consumed')->default(false);
            $table->time('consumed_at')->nullable();
            $table->text('substitutions')->nullable();
            $table->tinyInteger('rating')->nullable()->comment('Scale 1-5');
            $table->string('photo_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('meal_id')->references('id')->on('meals');
            $table->unique(['user_id', 'meal_id', 'tracking_date']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('meal_compliance');
    }
}
