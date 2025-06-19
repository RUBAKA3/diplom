<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderFrelanser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FrelancersController extends Controller
{
    public function index()
    {
        // Получаем только пользователей с is_freelancer = true
        $freelancers = User::with(['freelancerProfile.skills', 'freelancerProfile.category'])
            ->where('is_freelancer', true)
            ->get();

        // Форматируем вывод согласно требованиям
        $formattedFreelancers = $freelancers->map(function ($user) {
            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar_url,
                    'is_freelancer' => $user->is_freelancer,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'freelancerProfile' => $user->freelancerProfile ? [
                        'id' => $user->freelancerProfile->id,
                        'description' => $user->freelancerProfile->description,
                        'experience' => $user->freelancerProfile->experience,
                        'hourly_rate' => $user->freelancerProfile->hourly_rate,
                        'skills' => $user->freelancerProfile->skills->map(function ($skill) {
                            return [
                                'id' => $skill->id,
                                'name' => $skill->name,
                                'category_id' => $skill->category_id,
                                'created_at' => $skill->created_at,
                                'updated_at' => $skill->updated_at,
                                'pivot' => $skill->pivot
                            ];
                        }),
                        'category' => $user->freelancerProfile->category
                    ] : null
                ]
            ];
        });

        return response()->json($formattedFreelancers);
    }
     public function assignFreelancer(Request $request)
{
    $request->validate([
        'order_id' => 'required|exists:orders,id',
    ]);

    // Получаем ID аутентифицированного пользователя
    $freelancerId = Auth::id();

    // Проверяем, существует ли уже такая связь
    $existingAssignment = OrderFrelanser::where('order_id', $request->order_id)
        ->where('freelancer_id', $freelancerId)
        ->first();

    if ($existingAssignment) {
        return response()->json([
            'message' => 'Вы уже назначены на этот заказ',
        ], 400);
    }

    try {
        // Создаем связь между заказом и фрилансером
        OrderFrelanser::create([
            'order_id' => $request->order_id,
            'freelancer_id' => $freelancerId,
        ]);

        // Обновляем статус заказа на "в работе"
        $order = Order::findOrFail($request->order_id);
        $order->status = 'in_progress'; // или другой ваш статус для "в работе"
        $order->save();

        return response()->json([
            'message' => 'Вы успешно назначены на заказ',
            'order' => $order,
        ], 201);

    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Ошибка при назначении на заказ: ' . $e->getMessage(),
        ], 500);
    }
}
public function getFreelancerOrders($freelancerId)
    {
        $orders = OrderFrelanser::with('order')
            ->where('freelancer_id', $freelancerId)
            ->get()
            ->pluck('order');

        return response()->json([
            'orders' => $orders,
        ]);
    }
}