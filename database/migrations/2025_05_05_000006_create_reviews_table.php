<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateReviewsTable extends Migration
{
    public function up()
{
    Schema::create('reviews', function (Blueprint $table) {
        $table->id();
        $table->foreignId('order_id')->constrained()->onDelete('cascade');
        $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
        $table->foreignId('target_user_id')->constrained('users')->onDelete('cascade');
        $table->integer('rating');
        $table->text('comment')->nullable();
        $table->timestamps();
    });
}

    public function down()
    {
        Schema::dropIfExists('reviews');
    }
}