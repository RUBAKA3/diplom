<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFreelancerProfilesTable extends Migration
{
    public function up()
    {
        Schema::create('freelancer_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary(); // Делаем user_id первичным ключом
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            
            $table->string('title', 100);
            $table->text('description')->nullable();
            $table->integer('hourly_rate')->nullable();
            $table->foreignId('category_id')->constrained('categories');
            $table->decimal('rating', 3, 2)->default(0.0);
            $table->integer('reviews_count')->default(0);
            $table->boolean('is_online')->default(false);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('freelancer_profiles');
    }
}
