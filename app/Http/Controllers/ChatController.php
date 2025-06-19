<?php

namespace App\Http\Controllers;

use App\Events\ChatMessagesViewed;
use App\Events\NewMessage;
use App\Models\Chat;
use App\Models\Message;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class ChatController extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function getOrCreateChat($userId1, $userId2, $orderId = null)
{
    $chat = Chat::where(function($q) use ($userId1, $userId2) {
        $q->where('user1_id', $userId1)->where('user2_id', $userId2);
    })->orWhere(function($q) use ($userId1, $userId2) {
        $q->where('user1_id', $userId2)->where('user2_id', $userId1);
    })->first();

    if (!$chat) {
        $chat = Chat::create([
            'user1_id' => $userId1,
            'user2_id' => $userId2,
            'order_id' => $orderId,
        ]);
    }

    return $chat;
}

public function sendMessage(Request $request, $chatId)
{
    $request->validate(['message' => 'required|string']);

    $message = Message::create([
        'chat_id' => $chatId,
        'user_id' => auth()->id(),
        'message' => $request->message,
    ]);
    \Log::info('Sending NewMessage event');
    event(new NewMessage($message)); // Для WebSockets

    return response()->json($message, 201);
}

public function Messages($chat_id)
{
    $userId = auth()->id();
    $chat = Chat::with(['user1', 'user2'])->findOrFail($chat_id);
    
    // Проверка доступа
    if (!in_array($userId, [$chat->user1_id, $chat->user2_id])) {
        abort(403);
    }

    $messages = Message::where('chat_id', $chat_id)
        ->with(['user' => function($q) {
            $q->select('id', 'name');
        }])
        ->orderBy('created_at', 'asc')
        ->get()
        ->map(function($message) use ($userId) {
            $message->is_own = $message->user_id === $userId;
            return $message;
        });

    return response()->json([
        'chat' => $chat,
        'messages' => $messages,
        'current_user_id' => $userId // Добавляем ID текущего пользователя
    ]);
}
 public function getUserChats()
{
    $user = Auth::user();

    $chats = Chat::where(function($query) use ($user) {
            $query->where('user1_id', $user->id)
                  ->orWhere('user2_id', $user->id);
        })
        ->with([
            'user1', 
            'user2', 
            'order',
            'messages' => function($query) {
                $query->latest()->limit(1);
            }
        ])
        ->get()
        ->map(function($chat) {
            $chat->last_message = $chat->messages->first();
            return $chat;
        });

    return response()->json($chats);
}

}