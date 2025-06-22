<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'chat_id',
        'user_id',
        'message',
        'read_at' // Добавлено для отслеживания прочитанных сообщений
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'read_at',
        'created_at',
        'updated_at'
    ];

    /**
     * Relationship to the Chat model
     */
    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    /**
     * Relationship to the User model
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withDefault([
            'name' => 'Удаленный пользователь',
            'avatar_url' => 'default-avatar.png'
        ]);
    }

    /**
     * Scope for unread messages
     */
    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /**
     * Mark message as read
     */
    public function markAsRead()
    {
        if (is_null($this->read_at)) {
            $this->update(['read_at' => now()]);
        }
        return $this;
    }

    /**
     * Check if message is read
     */
    public function isRead()
    {
        return !is_null($this->read_at);
    }

    /**
     * Check if message is unread
     */
    public function isUnread()
    {
        return is_null($this->read_at);
    }

    /**
     * Get the message time with read status
     */
    public function getTimeWithStatusAttribute()
    {
        $time = $this->created_at->format('H:i');
        
        if ($this->isMine()) {
            return $time . ($this->isRead() ? ' ✓✓' : ' ✓');
        }
        
        return $time;
    }

    /**
     * Check if message belongs to current user
     */
    public function isMine()
    {
        return $this->user_id === auth()->id();
    }
}