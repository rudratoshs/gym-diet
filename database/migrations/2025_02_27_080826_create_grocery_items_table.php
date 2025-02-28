<?php
// database/migrations/YYYY_MM_DD_create_grocery_items_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroceryItemsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('grocery_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('grocery_list_id');
            $table->string('name');
            $table->string('quantity')->nullable();
            $table->enum('category', ['produce', 'protein', 'dairy', 'grains', 'pantry', 'spices', 'other'])->default('other');
            $table->boolean('is_purchased')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('grocery_list_id')->references('id')->on('grocery_lists')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('grocery_items');
    }
}
