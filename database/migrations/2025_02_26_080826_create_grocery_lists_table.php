<?php
// database/migrations/2025_02_27_080826_create_grocery_lists_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroceryListsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('grocery_lists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('diet_plan_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('week_starting');
            $table->enum('status', ['active', 'completed', 'archived'])->default('active');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('diet_plan_id')->references('id')->on('diet_plans')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grocery_lists');
    }
}