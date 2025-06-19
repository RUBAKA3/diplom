<?php


use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrdersTable extends Migration
{
    public function up()
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('users');
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->integer('budget')->nullable();
            $table->foreignId('category_id')->constrained();
            $table->date('deadline')->nullable();
            $table->string('status', 20)->default('open');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('orders');
    }
}