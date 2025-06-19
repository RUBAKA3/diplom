<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class OrderController extends Controller
{
   public function ordercr(Request $request)
{
    $validator = Validator::make($request->all(), [
        'title' => 'required|string|max:255',
        'description' => 'required|string',
        'budget' => 'nullable|numeric|min:0',
        'deadline_days' => 'nullable|integer|min:1',
        'category_id' => 'required|exists:categories,id',
        'skills' => 'nullable|array',
        'skills.*' => 'string|max:255',
        'files' => 'nullable',
        'files.*' => 'file|mimes:jpeg,png,pdf,docx,zip|max:10240',
        'status' => 'required|in:draft,open' // Добавляем валидацию статуса
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Validation error',
            'errors' => $validator->errors()
        ], 422);
    }

    // Рассчитываем deadline
    $deadline = $request->has('deadline_days') 
        ? Carbon::today()->addDays($request->deadline_days)
        : null;

    // Создаем заказ
    $order = Order::create([
        'client_id' => Auth::id(),
        'title' => $request->title,
        'description' => $request->description,
        'budget' => $request->budget,
        'deadline' => $deadline,
        'category_id' => $request->category_id,
        'status' => $request->status, // Используем переданный статус
    ]);

    // Обрабатываем навыки
    if ($request->has('skills')) {
        $skillIds = [];
        foreach ($request->skills as $skillName) {
            $skill = Skill::firstOrCreate(['name' => trim($skillName)]);
            $skillIds[] = $skill->id;
        }
        $order->skills()->attach($skillIds);
    }

    // Загружаем файлы
    if ($request->hasFile('files')) {
        foreach ($request->file('files') as $file) {
            $path = $file->store('orders/files', 'public');
            
            $order->files()->create([
                'name' => $file->getClientOriginalName(),
                'url' => $path,
                
            ]);
        }
    }

    return response()->json([
        'message' => $request->status === 'draft' ? 'Draft saved successfully' : 'Order created successfully',
        'order' => $order->load('skills', 'category', 'files')
    ], 201);
}
public function userOrders(Request $request)
{
    $perPage = $request->per_page ?? 10;
    
    $orders = Order::where('client_id', auth()->id())
        ->with(['skills', 'category', 'files'])
        ->latest()
        ->paginate($perPage);
    
    return response()->json([
        'data' => $orders->items(),
        'total' => $orders->total(),
        'current_page' => $orders->currentPage(),
        'per_page' => $orders->perPage(),
        'last_page' => $orders->lastPage(),
    ]);
}

public function cancelOrder(Order $order)
{
    if ($order->client_id !== auth()->id()) {
        return response()->json(['message' => 'Unauthorized'], 403);
    }
    
    if (!in_array($order->status, ['open', 'in_progress'])) {
        return response()->json(['message' => 'Order cannot be canceled'], 400);
    }
    
    $order->update(['status' => 'canceled']);
    
    return response()->json(['message' => 'Order canceled successfully']);
}
    public function index(Request $request)
    {
        // Основной запрос с фильтрацией по статусу 'open'
        $query = Order::with(['client', 'skills', 'freelancers'])
            ->where('status', 'open')
            ->orderBy('created_at', 'desc');

        // Фильтрация по другим статусам, если переданы в запросе
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $orders = $query->get()->map(function ($order) {
            return [
                'id' => $order->id,
                'title' => $order->title,
                'description' => $order->description,
                'price' => $order->budget ? number_format($order->budget, 0, '', ',') . ' ₽' : 'Договорная',
                'deadline' => $order->deadline ? Carbon::parse($order->deadline)->format('d.m.Y') : null,
                'status' => $order->status,
                'client' => [
                    'id' => $order->client->id,
                    'name' => $order->client->name,
                    'avatar' => $order->client->avatar_url ?? null
                ],
                'skills' => $order->skills->map(function ($skill) {
                    return $skill;
               
                }),
                'freelancers_count' => $order->freelancers->count(), // Количество фрилансеров
                'created_at' => $order->created_at->format('d.m.Y ')
            ];
        });

        return response()->json($orders);
    }
    public function byCategory($category_id)
    {
        // Получаем категорию или возвращаем 404
        $category = Category::findOrFail($category_id);

        // Получаем заказы для категории с необходимыми отношениями
        $orders = Order::with([
                'client', 
                'skills', 
                'freelancers',
                'category'
            ])
            ->where('category_id', $category_id)
            ->where('status', 'open')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($order) {
                return $this->transformOrder($order);
            });

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description ?? null // если есть описание
            ],
            'orders' => $orders,
            'meta' => [
                'total_orders' => $orders->count(),
                'last_updated' => now()->format('d.m.Y H:i')
            ]
        ]);
    }

    /**
     * Преобразование заказа в нужный формат
     *
     * @param Order $order
     * @return array
     */
    protected function transformOrder(Order $order)
    {
        return [
            'id' => $order->id,
            'title' => $order->title,
            'description' => $order->description,
            'price' => $this->formatPrice($order->budget),
            'deadline' => $order->deadline 
                ? Carbon::parse($order->deadline)->format('d.m.Y') 
                : null,
            'status' => $order->status,
            'client' => [
                'id' => $order->client->id,
                'name' => $order->client->name,
                'avatar' => $order->client->avatar_url ?? null,
                'rating' => $order->client->rating ?? null
            ],
            'skills' => $order->skills->map(function($skill) {
                return [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'category_id' => $skill->category_id
                ];
            }),
            'freelancers' => $order->freelancers->map(function($freelancer) {
                return [
                    'id' => $freelancer->id,
                    'name' => $freelancer->name,
                    'avatar' => $freelancer->avatar_url ?? null,
                    'rating' => $freelancer->freelancerProfile->rating ?? null
                ];
            }),
            'freelancers_count' => $order->freelancers->count(),
            'created_at' => $order->created_at->format('d.m.Y H:i'),
            'updated_at' => $order->updated_at->format('d.m.Y H:i')
        ];
    }

    /**
     * Форматирование цены
     *
     * @param int|null $budget
     * @return string
     */
    protected function formatPrice($budget)
    {
        if (!$budget) {
            return 'Договорная';
        }

        return number_format($budget, 0, '', ' ') . ' ₽';
    }
    public function show($id)
    {
        // Получаем заказ по ID
        $order = Order::with([
                'client', 
                'skills', 
                'freelancers',
                'category'
            ])->findOrFail($id);

        // Форматируем данные для ответа
        $response = [
            'isOwner' => false, // Установите это значение в зависимости от логики авторизации
            'bidAmount' => null,
            'bidComment' => '',
            'order' => [
                'id' => $order->id,
                'title' => $order->title,
                'category' => $order->category->name, // Предполагается, что у категории есть поле name
                'date' => $this->timeAgo($order->created_at), // Метод для форматирования даты
                'status' => $order->status,
                'freelancers_count' => $order->freelancers->count(), // Количество фрилансеров
                'price' => $order->budget ? number_format($order->budget, 0, '', ',') . ' ₽' : 'Договорная',
                'budgetType' => 'fixed', // Можно изменить в зависимости от ваших требований
                'deadline' => $this->timeUntilDeadline($order->deadline),
                'description' => $order->description,
                'client' => [
                    'id' => $order->client->id,
                    'name' => $order->client->name,
                    'avatar' => $order->client->avatar_url ?? null
                ],
                'skills' => $order->skills->map(function($skill) {
                return $skill->name;
            }),
                'files' => $this->getFiles($order) // Метод для получения файлов
            ]
        ];

        return response()->json($response);
    }
    private function getFiles($order)
    {
        return $order->files->map(function ($file) {
            return [
                'name' => $file->name,
                'url' => $file->url
            ];
        })->toArray();
    }
    private function timeAgo($datetime)
{
    // Преобразуем строку или объект в формат Carbon
    $date = Carbon::parse($datetime);

    // Возвращаем разницу в виде строки, например "2 дня назад"
    return $date->diffForHumans();
}
private function timeUntilDeadline($deadline)
{
    // Преобразуем строку даты в объект Carbon
    $date = Carbon::parse($deadline);

    // Получаем разницу во времени
    $diff = Carbon::now()->diff($date);

    // Формируем строку в зависимости от разницы
    if ($diff->invert) {
        return 'Время вышло';
    }

    // Пример вывода: "через 3 дня", "через 2 недели"
    return $date->diffForHumans();
}

}