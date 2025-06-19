<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderSkillTable extends Migration
{
    public function up()
    {
        Schema::create('order_skill', function (Blueprint $table) {
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('skill_id')->constrained()->onDelete('cascade');
            $table->primary(['order_id', 'skill_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_skill');
    }
}