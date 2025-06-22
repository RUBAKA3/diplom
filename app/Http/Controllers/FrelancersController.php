<?php

namespace App\Http\Controllers;

use App\Models\FreelancerApplication;
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
            ->where('role', "freelancer")
            ->get();

        // Форматируем вывод согласно требованиям
        $formattedFreelancers = $freelancers->map(function ($user) {
            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar_url,
                    'role' => $user->role,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at,
                    'freelancerProfile' => $user->freelancerProfile ? [
                        'id' => $user->freelancerProfile->id,
                        'description' => $user->freelancerProfile->description,
                        'experience' => $user->freelancerProfile->experience,
                        'hourly_rate' => $user->freelancerProfile->hourly_rate,
                        'rating' => $user->freelancerProfile->rating,
                        'reviews_count' => $user->freelancerProfile->reviews_count,
                        
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
     public function store(Request $request)
    {
        $validated = $request->validate([
            'description' => 'required|string|min:50',
            'skills' => 'required|array',
            'portfolio_url' => 'nullable|url',
        ]);

        $application = FreelancerApplication::create([
            'user_id' => Auth::id(),
            ...$validated,
            'status' => 'pending',
        ]);

        return response()->json($application, 201);
    }
     public function inde()
    {
        $applications = FreelancerApplication::with('user')->get();
        
        return response()->json($applications);
    }

    public function check()
    {
        $application = FreelancerApplication::where('user_id', Auth::id())->first();
        return response()->json(['exists' => !!$application, 'status' => $application->status ?? null]);
    }   
    public function approve($id)
    {
        $application = FreelancerApplication::with('user')->findOrFail($id);

        // Проверяем, что заявка еще не обработана
        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application has already been processed'
            ], 400);
        }

        // Обновляем статус заявки
        $application->update(['status' => 'approved']);

        // Обновляем роль пользователя
        $user = $application->user;
        $user->role = 'freelancer';
        $user->save();

        return response()->json([
            'message' => 'Application approved successfully',
            'application' => $application,
            'user' => $user
        ]);
    }

    /**
     * Reject a freelancer application
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject($id)
    {
        $application = FreelancerApplication::findOrFail($id);

        // Проверяем, что заявка еще не обработана
        if ($application->status !== 'pending') {
            return response()->json([
                'message' => 'Application has already been processed'
            ], 400);
        }

        // Обновляем статус заявки
        $application->update(['status' => 'rejected']);

        return response()->json([
            'message' => 'Application rejected successfully',
            'application' => $application
        ]);
    }

    /**
     * Get pending applications (for admin)
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function pending()
    {
        $applications = FreelancerApplication::with('user')
            ->where('status', 'pending')
            ->get();

        return response()->json($applications);
    }
}