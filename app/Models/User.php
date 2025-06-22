<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject, MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = [
    'name',
    'email',
    'password_hash',
    'balance',
    'avatar_url',
    'jwt_token',
    'jwt_token_expiry',
    'role',
    'is_banned',
    // Добавляем, если нужно сохранить
    'email_verification_token',
    'email_verified_at',
    'is_active'
];

protected $casts = [
    'email_verified_at' => 'datetime',
    'is_active' => 'boolean',
    'is_banned' => 'boolean',
    'balance' => 'decimal:2'
];

    protected $hidden = [
        'password_hash',
        'jwt_token',
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
            'role' => $this->role, // Добавляем роль в JWT claims
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
    public function generateEmailVerificationToken()
    {
        $this->email_verification_token = Str::random(60);
        $this->save();
    }

    /**
     * Verify the user's email
     */
    public function markEmailAsVerified()
    {
        $this->email_verification_token = null;
        $this->email_verified_at = $this->freshTimestamp();
        $this->is_active = true;
        $this->save();
    }

    /**
     * Check if user has verified email
     */
    public function hasVerifiedEmail()
    {
        return !is_null($this->email_verified_at);
    }
    /**
     * Проверяет, является ли пользователь администратором
     */
    public function isAdmin()
    {
        return $this->role === 'admin';
    }
}