<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('freelancer_id')->constrained('users')->onDelete('cascade');
            $table->decimal('amount', 10, 2)->nullable();
            $table->text('comment');
            $table->date('deadline')->nullable();
            $table->string('status')->default('pending'); // pending, accepted, rejected
            $table->timestamps();
            
            $table->index(['order_id', 'freelancer_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('bids');
    }
};