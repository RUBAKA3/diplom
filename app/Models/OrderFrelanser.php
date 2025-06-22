<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderFrelanser extends Model
{
    use HasFactory;

    /**
     * Название таблицы, связанной с моделью.
     *
     * @var string
     */
    protected $table = 'order_freelancer';

    /**
     * Отключаем автоинкремент для первичного ключа,
     * так как у нас составной первичный ключ.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * Указывает, что временные метки (created_at, updated_at) должны поддерживаться.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Атрибуты, которые можно массово назначать.
     *
     * @var array
     */
    protected $fillable = [
        'order_id',
        'freelancer_id',
    ];

    /**
     * Получить связанный заказ.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Получить связанного фрилансера.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function freelancer()
    {
        return $this->belongsTo(User::class, 'freelancer_id');
    }
    
}