<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreelancerApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'description',
        'skills',
        'portfolio_url',
        'status'
    ];

    protected $casts = [
        'skills' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'freelancer_application_skills', 'application_id', 'skill_id');
    }
    
}
