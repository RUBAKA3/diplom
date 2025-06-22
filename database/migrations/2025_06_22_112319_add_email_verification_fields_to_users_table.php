<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email_verification_token', 60)->nullable()->after('jwt_token_expiry');
            $table->timestamp('email_verified_at')->nullable()->after('email_verification_token');
            $table->boolean('is_active')->default(false)->after('email_verified_at');
            
            
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_verification_token', 'email_verified_at', 'is_active']);
            // Не удаляем is_freelancer, если он уже существовал
        });
    }
};