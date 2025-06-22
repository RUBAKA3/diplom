<?php

namespace App\Http\Controllers;

use App\Events\ChatMessagesViewed;

use App\Events\ChatUpdated;
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

    public function getOrCreateChat(Request $request)
{
    try {
        $validated = $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            'order_id' => 'nullable|integer|exists:orders,id'
        ]);

        $userId1 = auth()->id();
        $userId2 = $validated['receiver_id'];
        $orderId = $validated['order_id'] ?? null;

        if ($userId1 == $userId2) {
            return response()->json([
                'message' => 'Нельзя создать чат с самим собой'
            ], 422);
        }

        $chat = Chat::firstOrCreate(
            [
                'user1_id' => min($userId1, $userId2),
                'user2_id' => max($userId1, $userId2),
            ],
            [
                'order_id' => $orderId
            ]
        );

        return response()->json([
            'success' => true,
            'chat' => $chat
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'message' => $e->getMessage(),
            
        ], 422);
    }
}

public function sendMessage(Request $request, $chatId)
{
    $request->validate(['message' => 'required|string']);

    $message = Message::create([
        'chat_id' => $chatId,
        'user_id' => auth()->id(),
        'message' => $request->message,
    ]);
    
    $message->load('user');
    
    $chat = Chat::with(['user1', 'user2', 'order'])->find($chatId);
    $chat->update(['updated_at' => now()]);
    $chat->refresh();
    
    // Отправляем событие новое сообщение в конкретный чат
    broadcast(new NewMessage($message))->toOthers();
    
    // Отправляем обновление для всех чатов пользователей
    broadcast(new ChatUpdated($chat->load('lastMessage')))->toOthers();

    return response()->json([
        'message' => $message,
        'chat' => $chat
    ], 201);
}
    public function Messages($chat_id)
    {
        $userId = auth()->id();
        $chat = Chat::with(['user1', 'user2', 'order'])->findOrFail($chat_id);
        
        if (!in_array($userId, [$chat->user1_id, $chat->user2_id])) {
            abort(403);
        }

        // Помечаем сообщения как прочитанные
        Message::where('chat_id', $chat_id)
            ->where('user_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = Message::where('chat_id', $chat_id)
            ->with(['user' => function($q) {
                $q->select('id', 'name', 'avatar_url');
            }])
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($message) use ($userId) {
                $message->is_own = $message->user_id === $userId;
                return $message;
            });

        broadcast(new ChatMessagesViewed($chat_id, $userId))->toOthers();

        return response()->json([
            'chat' => $chat,
            'messages' => $messages,
            'current_user_id' => $userId
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
                'user1:id,name,avatar_url', 
                'user2:id,name,avatar_url', 
                'order:id,title',
                'lastMessage.user:id,name'
            ])
            ->withCount(['messages as unread_count' => function($query) use ($user) {
                $query->where('user_id', '!=', $user->id)
                      ->whereNull('read_at');
            }])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function($chat) use ($user) {
                $chat->last_message = $chat->lastMessage;
                return $chat;
            });

        return response()->json($chats);
    }

   // Laravel пример
public function markAsRead($chatId)
{
    $chat = Chat::findOrFail($chatId);
    
    // Помечаем все сообщения как прочитанные
    Message::where('chat_id', $chatId)
        ->where('user_id', '!=', auth()->id())
        ->whereNull('read_at')
        ->update(['read_at' => now()]);
    
    // Отправляем событие об обновлении
    broadcast(new ChatUpdated($chat))->toOthers();
    
    return response()->json(['status' => 'success']);
}
}