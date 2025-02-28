<?php
// database/migrations/YYYY_MM_DD_add_nutritional_fields_to_meals_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddNutritionalFieldsToMealsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Only run if meal_plans table exists and the columns don't already exist
        if (Schema::hasTable('meals') && !Schema::hasColumn('meals', 'nutritional_score')) {
            Schema::table('meals', function (Blueprint $table) {
                $table->integer('nutritional_score')->nullable()->after('fats_grams')->comment('0-100 scale');
                $table->boolean('is_healthy')->nullable()->after('nutritional_score');
                $table->boolean('is_enhanced')->default(false)->after('is_healthy')->comment('Whether nutrition data is enhanced');
                $table->json('food_groups')->nullable()->after('is_enhanced');
                $table->json('allergens')->nullable()->after('food_groups');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('meals')) {
            Schema::table('meals', function (Blueprint $table) {
                $table->dropColumn([
                    'nutritional_score',
                    'is_healthy',
                    'is_enhanced',
                    'food_groups',
                    'allergens'
                ]);
            });
        }
    }
}
