<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bid;
use App\Models\Order;
use App\Models\OrderFrelanser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BidController extends Controller
{
    // Получить все заявки для конкретного заказа
    public function index($orderId)
    {
        $order = Order::findOrFail($orderId);
        
        // Проверяем, что пользователь - владелец заказа
        if (Auth::id() !== $order->client_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $bids = Bid::with('freelancer')
            ->where('order_id', $orderId)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json(['bids' => $bids]);
    }

    // Создать новую заявку
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Проверяем, что пользователь - фрилансер
        if ($user->role !== 'freelancer') {
            return response()->json(['message' => 'Only freelancers can submit bids'], 403);
        }
        
        $validated = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'amount' => 'nullable|numeric|min:0',
            'comment' => 'required|string|min:10|max:1000',
            'deadline' => 'nullable|date|after:today'
        ]);
        
        // Проверяем, что заказ открыт
        $order = Order::find($validated['order_id']);
        if ($order->status !== 'open') {
            return response()->json(['message' => 'Order is not open for bids'], 400);
        }
        
        // Проверяем, что пользователь еще не подавал заявку
        $existingBid = Bid::where('order_id', $validated['order_id'])
            ->where('freelancer_id', $user->id)
            ->first();
            
        if ($existingBid) {
            return response()->json(['message' => 'You have already submitted a bid for this order'], 400);
        }
        
        $bid = Bid::create([
            'order_id' => $validated['order_id'],
            'freelancer_id' => $user->id,
            'amount' => $validated['amount'],
            'comment' => $validated['comment'],
            'deadline' => $validated['deadline'],
            'status' => 'pending'
        ]);
        
        return response()->json([
            'message' => 'Bid submitted successfully',
            'bid' => $bid->load('freelancer')
        ], 201);
    }

    // Принять заявку
public function accept($bidId)
{
    $bid = Bid::with('order')->findOrFail($bidId);
    
    // Проверяем, что пользователь - владелец заказа
    if (Auth::id() !== $bid->order->client_id) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }
    
    // Проверяем, что заявка еще не была принята
    if ($bid->status === 'accepted') {
        return response()->json(['message' => 'Bid already accepted'], 400);
    }
    
    // Начинаем транзакцию
    DB::beginTransaction();
    
    try {
        // Обновляем статус заявки
        $bid->update(['status' => 'accepted']);
        
        // Меняем статус заказа на "в процессе"
        $bid->order->update([
            'status' => 'in_progress',
            'freelancer_id' => $bid->freelancer_id
        ]);
        
        // Создаем или обновляем запись в таблице order_freelancer
        OrderFrelanser::updateOrCreate(
            ['order_id' => $bid->order_id],
            ['freelancer_id' => $bid->freelancer_id]
        );
        
        // Отклоняем все остальные заявки на этот заказ
        Bid::where('order_id', $bid->order_id)
            ->where('id', '!=', $bid->id)
            ->update(['status' => 'rejected']);
            
        DB::commit();
        
        return response()->json([
            'message' => 'Bid accepted successfully',
            'order_status' => 'in_progress'
        ]);
        
    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json(['message' => 'Error accepting bid: ' . $e->getMessage()], 500);
    }
}
    // Отклонить заявку
    public function reject($bidId)
    {
        $bid = Bid::with('order')->findOrFail($bidId);
        
        // Проверяем, что пользователь - владелец заказа
        if (Auth::id() !== $bid->order->client_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
        
        $bid->update(['status' => 'rejected']);
        
        return response()->json(['message' => 'Bid rejected successfully']);
    }
}