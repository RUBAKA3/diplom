<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'password_hash',
        'name',
        'avatar_url',
        'is_freelancer',
        'jwt_token', // Добавляем поле для хранения токена
        'jwt_token_expiry' // Добавляем поле для срока действия токена
    ];

    protected $hidden = [
        'password_hash',
        'jwt_token', // Скрываем токен при сериализации
    ];

    protected $dates = [
        'jwt_token_expiry'
    ];

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     */
    public function getJWTCustomClaims()
    {
        return [
            'is_freelancer' => $this->is_freelancer,
            'email' => $this->email,
        ];
    }

    /**
     * Сохраняет JWT токен в базе данных
     */
    public function saveToken($token, $expiry)
    {
        $this->jwt_token = $token;
        $this->jwt_token_expiry = $expiry;
        $this->save();
    }

    /**
     * Удаляет JWT токен из базы данных
     */
    public function clearToken()
    {
        $this->jwt_token = null;
        $this->jwt_token_expiry = null;
        $this->save();
    }

    /**
     * Проверяет, действителен ли текущий токен
     */
    public function hasValidToken()
    {
        return $this->jwt_token && $this->jwt_token_expiry > now();
    }

    public function freelancerProfile()
    {
        return $this->hasOne(FreelancerProfile::class, 'user_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'client_id');
    }

    public function getAuthPassword()
    {
     
        return $this->password_hash;
    }
    public function chats()
    {
    return $this->belongsToMany(Chat::class, 'chat_user', 'user_id', 'chat_id');
    }
    
}