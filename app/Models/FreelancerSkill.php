<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class FreelancerSkill extends Pivot
{
    use HasFactory;

    protected $table = 'freelancer_skills';

    public $incrementing = true;

    public function freelancer()
    {
        return $this->belongsTo(FreelancerProfile::class, 'freelancer_id');
    }

    public function skill()
    {
        return $this->belongsTo(Skill::class);
    }
}