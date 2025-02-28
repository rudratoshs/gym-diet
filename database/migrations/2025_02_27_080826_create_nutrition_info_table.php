<?php
// database/migrations/YYYY_MM_DD_create_nutrition_info_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNutritionInfoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('nutrition_info', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meal_id');
            $table->integer('calories')->nullable();
            $table->decimal('protein_grams', 5, 2)->nullable();
            $table->decimal('carbs_grams', 5, 2)->nullable();
            $table->decimal('fats_grams', 5, 2)->nullable();
            $table->decimal('fiber_grams', 5, 2)->nullable();
            $table->decimal('sugar_grams', 5, 2)->nullable();
            $table->decimal('sodium_mg', 8, 2)->nullable();
            $table->decimal('calcium_mg', 8, 2)->nullable();
            $table->decimal('iron_mg', 5, 2)->nullable();
            $table->decimal('vitamin_a_iu', 8, 2)->nullable();
            $table->decimal('vitamin_c_mg', 8, 2)->nullable();
            $table->decimal('vitamin_d_iu', 8, 2)->nullable();
            $table->decimal('vitamin_e_mg', 5, 2)->nullable();
            $table->timestamps();
            
            $table->foreign('meal_id')->references('id')->on('meals')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('nutrition_info');
    }
}
