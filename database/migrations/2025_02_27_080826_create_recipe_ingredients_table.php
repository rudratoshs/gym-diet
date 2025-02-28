<?php
// database/migrations/YYYY_MM_DD_create_recipe_ingredients_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRecipeIngredientsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meal_id');
            $table->string('ingredient_name');
            $table->string('quantity', 50)->nullable();
            $table->string('unit', 20)->nullable();
            $table->string('preparation_notes')->nullable();
            $table->boolean('is_optional')->default(false);
            $table->boolean('is_substitutable')->default(false);
            $table->text('substitution_options')->nullable();
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
        Schema::dropIfExists('recipe_ingredients');
    }
}
