<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Dispute extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'initiator_id',
        'reason',
        'status',
        'admin_comment',
        'admin_id'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiator_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
    // В модель Dispute
public function scopeFilter($query, array $filters)
{
    $query->when($filters['status'] ?? null, function ($query, $status) {
        $query->where('status', $status);
    })->when($filters['search'] ?? null, function ($query, $search) {
        $query->where(function ($query) use ($search) {
            $query->where('reason', 'like', '%'.$search.'%')
                ->orWhereHas('order', function ($query) use ($search) {
                    $query->where('title', 'like', '%'.$search.'%');
                });
        });
    });
}
}