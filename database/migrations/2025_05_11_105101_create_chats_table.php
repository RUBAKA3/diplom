<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chats', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user1_id')->constrained('users')->cascadeOnDelete(); // Клиент
        $table->foreignId('user2_id')->constrained('users')->cascadeOnDelete(); // Исполнитель
        $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete(); // Связь с заказом (опционально)
        $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
