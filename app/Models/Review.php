<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'user_id',
        'target_user_id',
        'rating',
        'comment',
    ];

    /**
     * Получить пользователя, оставившего отзыв
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Получить пользователя, на которого оставлен отзыв
     */
    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    /**
     * Получить связанный заказ
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}