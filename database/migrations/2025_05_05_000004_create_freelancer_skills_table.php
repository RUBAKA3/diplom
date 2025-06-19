<?php 
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFreelancerSkillsTable extends Migration
{
    public function up()
    {
        Schema::create('freelancer_skills', function (Blueprint $table) {
            $table->unsignedBigInteger('freelancer_id');
            $table->foreign('freelancer_id')->references('user_id')->on('freelancer_profiles');
            $table->foreignId('skill_id')->constrained('skills');
            $table->primary(['freelancer_id', 'skill_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('freelancer_skills');
    }
}