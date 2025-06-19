<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skill extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'category_id'];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function freelancers()
    {
        return $this->belongsToMany(FreelancerProfile::class, 'freelancer_skills', 'skill_id', 'freelancer_id');
    }
}