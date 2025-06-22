<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = ['user1_id', 'user2_id', 'order_id'];

    public function user1()
    {
        return $this->belongsTo(User::class, 'user1_id');
    }

    public function user2()
    {
        return $this->belongsTo(User::class, 'user2_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function lastMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
    protected $appends = ['unread_count'];

public function getUnreadCountAttribute()
{
    if (!auth()->check()) return 0;
    
    return $this->messages()
        ->where('user_id', '!=', auth()->id())
        ->whereNull('read_at')
        ->count();
}
}