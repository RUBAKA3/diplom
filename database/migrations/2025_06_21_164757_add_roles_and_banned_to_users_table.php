<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRolesAndBannedToUsersTable extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['user', 'freelancer', 'admin'])->default('user');
            $table->boolean('is_banned')->default(false);
            $table->dropColumn('is_freelancer'); // Удаляем старое поле, если оно есть
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_banned']);
            $table->boolean('is_freelancer')->default(false);
        });
    }
}