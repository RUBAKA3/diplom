<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class NewMessage implements ShouldBroadcast  // <-- Важно!
{
    public $message;

    public function __construct($message)
    {
        $this->message = $message;
    }

    public function broadcastOn()
    {
        return new Channel('chat.');  // Или PrivateChannel
    }

    // Опционально: кастомное имя события
    public function broadcastAs()
    {
        return 'NewMessage';
    }
}