<?php
// database/migrations/YYYY_MM_DD_add_generation_status_to_meal_plans_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddGenerationStatusToMealPlansTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Only run if meal_plans table exists and the column doesn't already exist
        if (Schema::hasTable('meal_plans') && !Schema::hasColumn('meal_plans', 'generation_status')) {
            Schema::table('meal_plans', function (Blueprint $table) {
                $table->enum('generation_status', ['pending', 'in_progress', 'completed', 'failed'])
                    ->default('completed')
                    ->after('day_of_week');
                $table->integer('total_calories')->nullable()->after('generation_status');
                $table->integer('total_protein')->nullable()->after('total_calories');
                $table->integer('total_carbs')->nullable()->after('total_protein');
                $table->integer('total_fats')->nullable()->after('total_carbs');
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
        if (Schema::hasTable('meal_plans')) {
            Schema::table('meal_plans', function (Blueprint $table) {
                $table->dropColumn([
                    'generation_status',
                    'total_calories',
                    'total_protein',
                    'total_carbs',
                    'total_fats'
                ]);
            });
        }
    }
}
