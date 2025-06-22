<?php

namespace App\Http\Controllers;

use App\Models\OrderAttachment;
use App\Models\Review;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Создание нового отзыва
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        // Получаем заказ
        $order = Order::findOrFail($request->order_id);
        $order->status = 'completed';
        // Проверяем, завершён ли заказ
        if ($order->status !== 'completed') {
            return response()->json([
                'message' => 'Отзыв можно оставить только для завершенного заказа',
            ], 403);
        }

        // Проверяем, связан ли текущий пользователь с заказом (как клиент или фрилансер)
        $user = Auth::user();
        if ($order->client_id !== $user->id && !$order->freelancers->contains($user->id)) {
            return response()->json([
                'message' => 'Вы не можете оставить отзыв на этот заказ',
            ], 403);
        }

        // Проверяем, не оставлял ли уже пользователь отзыв на этот заказ
        $existingReview = Review::where('order_id', $request->order_id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingReview) {
            return response()->json([
                'message' => 'Вы уже оставляли отзыв на этот заказ',
            ], 400);
        }

        try {
            // Создаем отзыв
            $review = Review::create([
                'order_id' => $request->order_id,
                'user_id' => $user->id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'target_user_id' => $user->id === $order->client_id 
                    ? $order->freelancers->first()->id 
                    : $order->client_id,
            ]);

            return response()->json([
                'message' => 'Отзыв успешно создан',
                'review' => $review,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при создании отзыва: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Получение отзывов пользователя
     *
     * @param int $userId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUserReviews($userId)
    {
        $reviews = Review::where('target_user_id', $userId)
            ->with(['user', 'order'])
            ->get();

        return response()->json([   
            'reviews' => $reviews,
        ]);
    }
   public function closeOrder(Request $request, $orderId)
{
    // 1. Находим заказ
    $order = Order::findOrFail($orderId);
    $user = Auth::user();
    
    // 2. Проверка прав
    if ($order->client_id != $user->id) {
        return response()->json(['message' => 'Доступ запрещен'], 403);
    }
    
    // 3. Проверка статуса
    if ($order->status !== 'awaiting_review') {
        return response()->json([
            'success' => false,
            'message' => 'Заказ можно закрыть только из статуса "Ожидает подтверждения"'
        ], 400);
    }
    
    // 4. Валидация
    $validator = Validator::make($request->all(), [
        'rating' => 'required|integer|between:1,5',
        'comment' => 'nullable|string|max:1000',
        'additional_amount' => 'nullable|numeric|min:0' // Добавляем валидацию для дополнительной суммы
    ]);
    
    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors()
        ], 422);
    }
    
    // 5. Добавляем сумму к цене заказа, если она указана
    if ($request->has('additional_amount') && $request->additional_amount > 0) {
        $order->price += $request->additional_amount;
        // Можно также добавить запись в историю изменений, если нужно
        // $order->price_history = [...$order->price_history, ['amount' => $request->additional_amount, 'date' => now()]];
    }
    
    // 6. Обновляем статус заказа
    $order->status = 'completed';
    $order->updated_at = now();
    $order->save();
    
    // 7. Создаем отзыв
    Review::create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'target_user_id' => $user->id,
        'rating' => $request->rating,
        'comment' => $request->comment,
    ]);
    
    // 8. Обновляем рейтинг фрилансера
    $this->updateFreelancerRating($order->freelancer_id);
    
    return response()->json([
        'success' => true,
        'message' => 'Заказ успешно закрыт',
        'order' => $order->fresh()
    ]);
}
    
    /**
     * Подтверждение выполнения заказа (для фрилансера)
     *
     * @param int $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function completeOrder(Request $request, $orderId)
{
    $request->validate([
        'files' => 'sometimes|array',
        'files.*' => 'file|max:10240', // Максимум 10MB на файл
    ]);

    $order = Order::findOrFail($orderId);
    $user = Auth::user();

    // Проверяем, что заказ в работе и текущий пользователь - исполнитель
    if ($order->status !== 'in_progress' ) {
        return response()->json([
            'success' => false,
            'message' => 'Невозможно подтвердить выполнение заказа'
        ], 400);
    }

    // Сохраняем файлы, если они есть
    if ($request->hasFile('files')) {
        foreach ($request->file('files') as $file) {
            $path = $file->store('orders/files', 'public');
            
            $order->files()->create([
                'name' => $file->getClientOriginalName(),
                'url' => $path,
            ]);
        }
    }

    // Меняем статус на "ожидает подтверждения клиента"
    $order->status = 'awaiting_review';
    $order->save();

    // Можно добавить уведомление клиенту

    return response()->json([
        'success' => true,
        'message' => 'Заказ отправлен на подтверждение клиенту',
        'order' => $order->load('attachments')
        
    ]);
}
    
    /**
     * Обновление рейтинга фрилансера
     *
     * @param int $freelancerId
     */
    protected function updateFreelancerRating($freelancerId)
    {
        $freelancer = User::find($freelancerId);
        
        if ($freelancer) {
            $averageRating = Review::where('freelancer_id', $freelancerId)
                ->avg('rating');
            
            $freelancer->rating = round($averageRating, 1);
            $freelancer->save();
        }
    }
    
}