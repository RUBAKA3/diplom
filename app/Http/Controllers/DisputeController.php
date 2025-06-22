<?php

namespace App\Http\Controllers;

use App\Models\Dispute;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DisputeController extends Controller
{
    // Открытие нового спора
    public function openDispute(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);
        $user = Auth::user();


        // Проверка, что спор еще не открыт
        if ($order->dispute) {
            return response()->json([
                'success' => false,
                'message' => 'Спор по этому заказу уже открыт'
            ], 400);
        }

        // Валидация
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Создание спора
        $dispute = Dispute::create([
            'order_id' => $order->id,
            'initiator_id' => $user->id,
            'reason' => $request->reason,
            'status' => 'open',
        ]);

        // Обновляем статус заказа (если нужно)
        $order->status = 'dispute';
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Спор успешно открыт',
            'dispute' => $dispute
        ]);
    }

    // Список споров (для админа)
    public function index(Request $request)
    {
        $user = Auth::user();
        
        // Проверка прав - только админ
        if (!$user->isAdmin()) { // Предполагаем, что у вас есть метод isAdmin() в модели User
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        $disputes = Dispute::with(['order', 'initiator'])
            ->filter($request->all())
            ->paginate(15);

        return response()->json([
            'success' => true,
            'disputes' => $disputes
        ]);
    }

    // Просмотр спора
    public function show($disputeId)
    {
        $dispute = Dispute::with(['order', 'initiator', 'admin'])->findOrFail($disputeId);
        $user = Auth::user();

        // Проверка прав - участники спора или админ
        if (!$user->isAdmin() && 
            $dispute->initiator_id != $user->id && 
            $dispute->order->client_id != $user->id && 
            $dispute->order->freelancer_id != $user->id) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        return response()->json([
            'success' => true,
            'dispute' => $dispute
        ]);
    }

    // Обновление спора (для админа)
    public function update(Request $request, $disputeId)
    {
        $dispute = Dispute::findOrFail($disputeId);
        $user = Auth::user();

        // Проверка прав - только админ
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Доступ запрещен'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:in_progress,resolved,cancelled',
            'admin_comment' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $dispute->update([
            'status' => $request->status,
            'admin_comment' => $request->admin_comment,
            'admin_id' => $user->id,
        ]);

        // Если спор решен, можно обновить статус заказа
        if ($request->status === 'resolved') {
            $dispute->order->status = 'completed';
            $dispute->order->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Спор обновлен',
            'dispute' => $dispute->fresh()
        ]);
    }
}