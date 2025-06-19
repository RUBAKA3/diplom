<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FreelancerProfile extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'hourly_rate',
        'category_id',
        'rating',
        'reviews_count',
        'is_online'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function skills()
    {
        return $this->belongsToMany(Skill::class, 'freelancer_skills', 'freelancer_id', 'skill_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'freelancer_id');
    }
}