<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug'];

    public function skills()
    {
        return $this->hasMany(Skill::class);
    }

    public function freelancerProfiles()
    {
        return $this->hasMany(FreelancerProfile::class);
    }
    // app/Models/Category.php
public function orders()
{
    return $this->hasMany(Order::class);
}
}   