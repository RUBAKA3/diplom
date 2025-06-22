<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\EmailVerification;
use App\Mail\UserVerification;
use App\Models\User;
use App\Models\FreelancerProfile;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{

    public function register(Request $request)
    {
        // Валидация входных данных
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:4|max:100',
            'email' => 'required|email:rfc,dns|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.regex' => 'Пароль должен содержать минимум 1 заглавную букву, 1 строчную и 1 цифру'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors()
            ], 422);
        }

        // Создание пользователя
        $user = User::create([
            'name' => $request->name,
            'email' => strtolower(trim($request->email)),
            'password_hash' => Hash::make($request->password),
            'email_verification_token' => Str::random(60),
            'is_active' => false
        ]);

        // Отправка письма с подтверждением
        try {
            Mail::to($user->email)->send(new UserVerification($user));
            
            return response()->json([
                'message' => 'Регистрация успешна. Пожалуйста, проверьте вашу почту для подтверждения email.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email
                ]
            ], 201);
            
        } catch (\Exception $e) {
            // Удаляем пользователя при ошибке отправки
            $user->delete();
            
            \Log::error('Ошибка отправки письма подтверждения', [
                'error' => $e->getMessage(),
                'email' => $request->email
            ]);
            
            return response()->json([
                'message' => 'Не удалось отправить письмо подтверждения',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

     public function verifyEmail($token)
{
    $user = User::where('email_verification_token', $token)->first();

    if (!$user) {
        return response()->json(['message' => 'Неверный токен подтверждения'], 404);
    }

    if ($user->hasVerifiedEmail()) {
        return response()->json(['message' => 'Email уже подтвержден'], 400);
    }

    // Вот это критически важный вызов:
    $user->markEmailAsVerified();

    return response()->json([
        'message' => 'Email успешно подтвержден',
        'user' => $user->only(['id', 'name', 'email', 'email_verified_at'])
    ]);
}

    public function resendVerification(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)
                   ->where('is_active', false)
                   ->first();

        if (!$user) {
            return response()->json([
                'message' => 'Пользователь не найден или email уже подтвержден'
            ], 404);
        }

        try {
            $user->update(['email_verification_token' => Str::random(60)]);
            Mail::to($user->email)->send(new UserVerification($user));
            
            return response()->json([
                'message' => 'Письмо с подтверждением отправлено повторно'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Ошибка повторной отправки письма', [
                'error' => $e->getMessage(),
                'email' => $request->email
            ]);
            
            return response()->json([
                'message' => 'Не удалось отправить письмо подтверждения',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Сохраняем токен в базе
        $user = User::find(Auth::id());
        $user->saveToken($token, Carbon::now()->addMinutes(config('jwt.ttl')));

        return $this->respondWithToken($token);
    }
    public function profile($id)
{
    // Находим пользователя по ID с присоединенными данными профиля фрилансера
    $user = User::with(['freelancerProfile', 'freelancerProfile.skills', 'freelancerProfile.category'])
                ->find($id);

    // Если пользователь не найден, возвращаем 404
    if (!$user) {
        return response()->json([
            'message' => 'Пользователь не найден'
        ], 404);
    }

    // Если пользователь не фрилансер, возвращаем только базовые данные
    if (!$user->is_freelancer || !$user->freelancerProfile) {
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'avatar' => $user->avatar_url,
                'isOnline' => false,
                'message' => 'Данные пользователя успешно получены'
            ]
        ]);
    }

    // Форматируем данные для фрилансера
    $freelancerProfile = $user->freelancerProfile;
    $skills = $freelancerProfile->skills->pluck('name')->toArray();

    $responseData = [
        'id' => $user->id,
        'name' => $user->name,
        'title' => $freelancerProfile->title,
        'avatar' => $user->avatar_url,
        'isOnline' => $freelancerProfile->is_online,
        'hourly_rate' => $freelancerProfile->hourly_rate,
        'rating' => (float) $freelancerProfile->rating,
        'reviews' => $freelancerProfile->reviews_count,
        'rate' => number_format($freelancerProfile->hourly_rate, 0, '', ' ') . ' ₽/час',
        'description' => $freelancerProfile->description,
        'skills' => $skills,
        
    ];

    // Возвращаем данные пользователя в формате JSON
    return response()->json([
        'user' => $responseData,
        'message' => 'Данные пользователя успешно получены'
    ]);
}

    public function me()
{
    // Получаем аутентифицированного пользователя с отношениями
    $user = auth()->user()->load([
        'freelancerProfile', 
        'freelancerProfile.skills', 
        'freelancerProfile.category'
    ]);

    // Базовые данные пользователя
    $userData = [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'avatar' => $user->avatar_url,
        
        'role' => $user->role,
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at,
        // добавь другие базовые поля, которые нужны
    ];

    // Если пользователь фрилансер и есть профиль
    if ($user->role == "freelancer" && $user->freelancerProfile) {
        $userData['freelancerProfile'] = [
            'id' => $user->freelancerProfile->id,
            'description' => $user->freelancerProfile->description,
            'experience' => $user->freelancerProfile->experience,
            'hourly_rate' => $user->freelancerProfile->hourly_rate,
            'skills' => $user->freelancerProfile->skills,
            'category' => $user->freelancerProfile->category,
            // добавь другие поля профиля фрилансера
        ];
    }

    return response()->json([
        'user' => $userData,
        'message' => 'Данные текущего пользователя успешно получены'
    ]);
}

    public function logout()
    {
        $user = User::find(Auth::id());
        $user->clearToken();
        
        auth()->logout();
        
        return response()->json(['message' => 'Successfully logged out']);
    }
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60
        ]);
    }
    public function store(Request $request)
    {
        // Получаем аутентифицированного пользователя
        $user = Auth::user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Неавторизованный доступ'
            ], 401);
        }

        // Валидация входных данных (без user_id)
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:100',
            'description' => 'nullable|string',
            'hourly_rate' => 'required|integer|min:0',
            'category_id' => 'required|integer|exists:categories,id',
            'skills' => 'nullable|array',
            'skills.*' => 'integer|exists:skills,id',
            'is_online' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Ошибка валидации',
                'errors' => $validator->errors()
            ], 422);
        }

        // Проверяем, что профиль фрилансера ещё не создан
        if ($user->freelancerProfile) {
            return response()->json([
                'message' => 'Профиль фрилансера уже существует'
            ], 409);
        }

        try {
            DB::beginTransaction();

            // Создаем профиль фрилансера
            $profile = FreelancerProfile::create([
                'user_id' => $user->id, // Берём ID из аутентифицированного пользователя
                'title' => $request->title,
                'description' => $request->description,
                'hourly_rate' => $request->hourly_rate,
                'category_id' => $request->category_id,
                'is_online' => $request->is_online ?? false,
                'rating' => 0.0,
                'reviews_count' => 0
            ]);

            // Обновляем статус пользователя
            $user->update(['is_freelancer' => true]);

            // Добавляем навыки
            if ($request->has('skills')) {
                $profile->skills()->attach($request->skills);
            }

            DB::commit();

            return response()->json([
                'message' => 'Профиль фрилансера успешно создан',
                'profile' => $profile
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Ошибка при создании профиля',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function frelancerprof(Request $request)
{
    // Получаем аутентифицированного пользователя
     // Получаем аутентифицированного пользователя по токену
     $user = Auth::user();
    
     if (!$user) {
         return response()->json([
             'message' => 'Неавторизованный доступ'
         ], 401);
     }
 
     // Проверяем, есть ли у пользователя профиль фрилансера
     if (!$user->freelancerProfile) {
         return response()->json([
             'message' => 'Профиль фрилансера не найден'
         ], 404);
     }
 
     // Загружаем связанные данные профиля
     $profile = $user->freelancerProfile->load(['skills', 'category']);
 
     return response()->json([
         'profile' => [
             'id' => $user->id,
             'title' => $profile->title,
             'description' => $profile->description,
             'hourly_rate' => $profile->hourly_rate,
             'is_online' => $profile->is_online,
             'rating' => $profile->rating,
             'reviews_count' => $profile->reviews_count,
             'category' => [
                 'name' => $profile->category->name
             ],
             'skills' => $profile->skills->pluck('name')->toArray(),
             'created_at' => $profile->created_at,
             'updated_at' => $profile->updated_at
         ]
     ], 200);
}
   public function updateProfile(Request $request)
{
    $user = Auth::user();
    
    // Валидация базовых данных
    $validator = Validator::make($request->all(), [
        // Базовые поля пользователя
        'name' => 'sometimes|string|max:255',
        'email' => 'sometimes|email|unique:users,email,'.$user->id,
        'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        'role' => 'sometimes|in:client,freelancer', // предполагаем два возможных значения
        
        // Поля фрилансера (только если role = freelancer)
        'description' => 'sometimes|string|max:200',
        'experience' => 'sometimes|integer|min:0|max:50',
        'hourly_rate' => 'sometimes|numeric|min:0',
        'skills' => 'sometimes|array',
        'skills.*' => 'exists:skills,id',
        'category_id' => 'sometimes|exists:categories,id',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'Ошибка валидации',
            'errors' => $validator->errors()
        ], 422);
    }

    // Обновление базовых данных
    if ($request->has('name')) {
        $user->name = $request->name;
    }
    if ($request->has('email')) {
        $user->email = $request->email;
    }

    if ($request->hasFile('avatar')) {
        // Удаляем старый аватар, если он есть
        if ($user->avatar_url) {
            Storage::delete($user->avatar_url);
        }
        
        // Сохраняем новый аватар
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->avatar_url = $path;
    }

    // Обработка роли пользователя
    if ($request->has('role')) {
        $oldRole = $user->role;
        $user->role = $request->role;
        
        // Если пользователь стал фрилансером
        if ($request->role === 'freelancer' && !$user->freelancerProfile) {
            $user->freelancerProfile()->create([]);
        }
        // Если перестал быть фрилансером (стал клиентом)
        elseif ($request->role === 'client' && $user->freelancerProfile) {
            $user->freelancerProfile()->delete();
        }
    }

    $user->save();

    // Если пользователь фрилансер - обновляем его профиль
    if ($user->role === 'freelancer') {
        $freelancerProfile = $user->freelancerProfile ?? $user->freelancerProfile()->create([]);
        
        if ($request->has('description')) {
            $freelancerProfile->description = $request->description;
        }

        if ($request->has('experience')) {
            $freelancerProfile->experience = $request->experience;
        }

        if ($request->has('hourly_rate')) {
            $freelancerProfile->hourly_rate = $request->hourly_rate;
        }

        if ($request->has('category_id')) {
            $freelancerProfile->category_id = $request->category_id;
        }

        $freelancerProfile->save();

        // Обновляем навыки если они переданы
        if ($request->has('skills')) {
            $freelancerProfile->skills()->sync($request->skills);
        }
    }

    // Загружаем обновленные данные с отношениями
    $user->load('freelancerProfile.skills', 'freelancerProfile.category');

    // Формируем ответ
    $response = [
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
        'avatar_url' => $user->avatar_url,
        'role' => $user->role,
        'created_at' => $user->created_at,
        'updated_at' => $user->updated_at,
    ];

    // Добавляем данные фрилансера если они есть
    if ($user->role === 'freelancer' && $user->freelancerProfile) {
        $response['freelancer_profile'] = [
            'description' => $user->freelancerProfile->description,
            'experience' => $user->freelancerProfile->experience,
            'hourly_rate' => $user->freelancerProfile->hourly_rate,
            'skills' => $user->freelancerProfile->skills,
            'category' => $user->freelancerProfile->category ? [
                'id' => $user->freelancerProfile->category->id,
                'name' => $user->freelancerProfile->category->name
            ] : null,
        ];
    }

    return response()->json([
        'message' => 'Профиль успешно обновлен',
        'user' => $response,
    ]);
}
      public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        $user = $request->user();
        
        // Удаляем старый аватар, если он есть
        if ($user->avatar) {
            Storage::delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_url' => $path]);

        return response()->json([
            'message' => 'Аватар успешно обновлен',
            'avatar_url' => Storage::url($path)
        ]);
    }
}