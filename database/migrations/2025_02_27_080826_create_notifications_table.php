<?php
// database/migrations/YYYY_MM_DD_create_notifications_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('message');
            $table->enum('type', ['meal', 'exercise', 'shopping', 'progress', 'system', 'other'])->default('system');
            $table->boolean('is_read')->default(false);
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->enum('delivery_channel', ['whatsapp', 'app', 'email', 'sms'])->default('whatsapp');
            $table->enum('status', ['pending', 'sent', 'delivered', 'failed'])->default('pending');
            $table->string('reference_id')->nullable()->comment('Reference ID in external system');
            $table->string('reference_type', 50)->nullable()->comment('Type of reference (meal, progress, etc.)');
            $table->timestamps();
            
            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notifications');
    }
}
