<?php
// database/migrations/2024_02_26_000002_add_totals_to_meal_plans_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTotalsToMealPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->integer('total_calories')->nullable()->after('day_of_week');
            $table->integer('total_protein')->nullable()->after('total_calories');
            $table->integer('total_carbs')->nullable()->after('total_protein');
            $table->integer('total_fats')->nullable()->after('total_carbs');
            $table->string('generation_status')->default('pending')->after('total_fats');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('meal_plans', function (Blueprint $table) {
            $table->dropColumn([
                'total_calories',
                'total_protein',
                'total_carbs',
                'total_fats',
                'generation_status'
            ]);
        });
    }
}