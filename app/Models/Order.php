<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'title',
        'description',
        'budget',
        'deadline',
        'status',
        'category_id'
    
    ];

    public function skills()
    {
        return $this->belongsToMany(Skill::class);
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }
    public function freelancers()
{
    return $this->belongsToMany(User::class, 'order_freelancer', 'order_id', 'freelancer_id')
        ->where('is_freelancer', true);
}
// app/Models/Order.php
public function category()
{
    return $this->belongsTo(Category::class);
}
public function files()
{
    return $this->hasMany(File::class);
}
 public function order_freelancer()
{
    return $this->belongsTo(FreelancerProfile::class, 'freelancer_id');
}
// app/Models/Order.php

public function attachments()
{
    return $this->hasMany(OrderAttachment::class);
}
}