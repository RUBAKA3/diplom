<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Order;
use App\Models\Skill;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class OrderController extends Controller
{
   public function ordercr(Request $request)
    {
        // Валидация данных
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'budget' => 'required|numeric|min:1',
            'deadline_days' => 'nullable|integer|min:1',
            'category_id' => 'required|exists:categories,id',
            'skills' => 'nullable|array',
            'skills.*' => 'string|max:255',
            'files' => 'nullable',
            'files.*' => 'file|mimes:jpeg,png,pdf,docx,zip|max:10240',
            'status' => 'required|in:draft,open'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Для черновиков не проверяем баланс
        if ($request->status === 'draft') {
            $order = $this->createOrder($request);
            return response()->json([
                'message' => 'Черновик сохранен',
                'order' => $order,
                'balance' => Auth::user()->balance
            ], 201);
        }

        // Для активных заказов проверяем баланс
        $user = Auth::user();
        if ($user->balance < $request->budget) {
            return response()->json([
                'message' => 'Недостаточно средств',
                'current_balance' => $user->balance,
                'required_amount' => $request->budget
            ], 422);
        }

        // Создание заказа и списание средств в транзакции
        try {
            DB::beginTransaction();

            // Списываем средства
            $user->balance -= $request->budget;
            $user->save();

            // Создаем заказ
            $order = $this->createOrder($request);

            DB::commit();

            return response()->json([
                'message' => 'Заказ создан и средства зарезервированы',
                'order' => $order,
                'new_balance' => $user->balance
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при создании заказа',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function createOrder(Request $request)
    {
        $orderData = [
            'client_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'budget' => $request->budget,
            'category_id' => $request->category_id,
            'status' => $request->status,
        ];

        // Добавляем deadline если указан
        if ($request->has('deadline_days')) {
            $orderData['deadline'] = Carbon::today()->addDays($request->deadline_days);
        }

        $order = Order::create($orderData);

        // Прикрепляем навыки
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

        return $order->load('skills', 'category', 'files');
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
                'category_id'=>$order->category_id,
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
    protected function canApply(Order $order)
{
    // Если пользователь не авторизован - не может подать заявку
    if (!auth()->check()) {
        return false;
    }

    // Если пользователь - владелец заказа - не может подать заявку
    if (auth()->id() === $order->client_id) {
        return false;
    }

    // Если пользователь не фрилансер - не может подать заявку
    if (auth()->user()->role !== 'freelancer') {
        return false;
    }

    // Если заказ не в статусе 'open' - нельзя подать заявку
    if ($order->status !== 'open') {
        return false;
    }

    // Проверяем, есть ли уже заявка от этого пользователя
    $hasExistingBid = $order->bids()
        ->where('freelancer_id', auth()->id())
        ->exists();

    return !$hasExistingBid;
}
 public function show($id)
    {
        $order = Order::with([
                'client', 
                'skills', 
                'freelancers',
                'category',
                'bids' => function($query) {
                    $query->where('freelancer_id', auth()->id());
                },
                'attachments',
                'files'
            ])
            ->withCount('bids')
            ->findOrFail($id);

        // Преобразуем даты в объекты Carbon
        $createdAt = Carbon::parse($order->created_at);
        $deadline = $order->deadline ? Carbon::parse($order->deadline) : null;

        $assignedFreelancer = $order->freelancers->first();
        $isOwner = auth()->check() && auth()->id() === $order->client_id;

        $response = [
            'isOwner' => $isOwner,
            'can_apply' => $this->canApply($order),
            'order' => [
                'id' => $order->id,
                'title' => $order->title,
                'category' => $order->category->name,
                'created_at' => $createdAt->format('d.m.Y H:i'),
                'status' => $order->status,
                'bids_count' => $order->bids_count,
                'price' => $order->budget ? number_format($order->budget, 0, '', ',') . ' ₽' : 'Договорная',
                'deadline' => $deadline ? $deadline->format('d.m.Y') : null,
                'description' => $order->description,
                'client' => [
                    'id' => $order->client->id,
                    'name' => $order->client->name,
                    'avatar' => $order->client->avatar_url ?? null,
                    'rating' => $order->client->rating ?? 0,
                    'reviews_count' => $order->client->reviews_count ?? 0,
                    'city' => $order->client->city ?? 'Не указан'
                ],
                'freelancer' => $assignedFreelancer ? [
                    'id' => $assignedFreelancer->id,
                    'name' => $assignedFreelancer->name,
                    'avatar' => $assignedFreelancer->avatar_url ?? null,
                    'rating' => $assignedFreelancer->rating ?? 0,
                    'reviews_count' => $assignedFreelancer->reviews_count ?? 0
                ] : null,
                'skills' => $order->skills->pluck('name')->toArray(),
                'files' => $this->getFiles($order),
                'bids' => $order->bids
            ]
        ];

        return response()->json($response);
    }
    protected function getAttachments(Order $order): array
{
    return $order->attachments->map(function ($attachment) {
        return [
            'id' => $attachment->id,
            'name' => $attachment->original_name ?? $attachment->name,
            'url' => asset('storage/' . $attachment->path), // или Storage::url($attachment->path)
            'size' => $this->formatFileSize($attachment->size) // форматирует байты в KB/MB
        ];
    })->toArray();
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