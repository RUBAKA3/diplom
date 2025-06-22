<?php


use App\Http\Controllers\BalanceController;
use App\Http\Controllers\Api\BidController;
use App\Http\Controllers\DisputeController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\FrelancersController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Admin\UserManagementController;


Route::post('/register', [AuthController::class, 'register']);
Route::get('/email/verify/{token}', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');
Route::post('/resend-verification', [AuthController::class, 'resendVerificationEmail']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::get('/users/{id}', [AuthController::class, 'profile']);
Route::get('/orde', [OrderController::class, 'index']);
Route::get('/orders/{category_id}', [OrderController::class, 'byCategory']);
Route::get('/order/{id}', [OrderController::class, 'show']);
Route::get('/freelancers', [FrelancersController::class, 'index']);
Route::get('/freelancer-orders/{freelancerId}', [FrelancersController::class, 'getFreelancerOrders']);
Route::get('/reviews/{userId}', [ReviewController::class, 'getUserReviews']);


Route::middleware(['auth'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/logout', [AuthController::class, 'logout']);
    Route::post('/freelancer-profiles', [AuthController::class, 'store']);
    Route::get('/freelancer', [AuthController::class, 'frelancerprof']);
    Route::post('/orders', [OrderController::class, 'ordercr']);
    Route::get('/user/orders', [OrderController::class, 'userOrders']);
    Route::put('/orders/{order}/cancel', [OrderController::class, 'cancelOrder']);
    Route::put('/profile-refresh', [AuthController::class, 'updateProfile']);
    
    // Fixed avatar routes
    Route::post('/profile/user/avatar', [AuthController::class, 'updateAvatar']);
    Route::delete('/profile/user/avatar', [AuthController::class, 'deleteAvatar']);
    Route::post('/assign-freelancer', [FrelancersController::class, 'assignFreelancer']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::post('/orders/{order}/close', [ReviewController::class, 'closeOrder']);
    Route::post('/orders/{order}/complete', [ReviewController::class, 'completeOrder']);

    // Chat routes
    Route::post('/chat-create', [ChatController::class, 'getOrCreateChat']);
    Route::get('/chat/{chat}/messages', [ChatController::class, 'messages']);
    Route::post('/chat/{chatId}/send', [ChatController::class, 'sendMessage']);
    Route::get('/chats', [ChatController::class, 'getUserChats']);
    Route::post('/chat/{chat}/mark-read', [ChatController::class, 'markAsRead']);
    
    // WebSocket auth route
    Route::post('/broadcasting/auth', function() {
        return Broadcast::auth(request());
    });
    Route::post('/freelancer-applications', [FrelancersController::class, 'store']);
    Route::get('/freelancer-applications', [FrelancersController::class, 'inde']);
    Route::get('/freelancer-applications/check', [FrelancersController::class, 'check']);
     Route::get('/freelancer-applications/pending', [FrelancersController::class, 'pending']);
    Route::post('/freelancer-applications/{id}/approve', [FrelancersController::class, 'approve']);
    Route::post('/freelancer-applications/{id}/reject', [FrelancersController::class, 'reject']);
    // Admin routes group
    
    Route::get('/users', [UserManagementController::class, 'index']);
    Route::put('/users/{user}/role', [UserManagementController::class, 'updateRole']);
    Route::post('/users/{user}/toggle-ban', [UserManagementController::class, 'toggleBan']);
    Route::post('/users/ban-selected', [UserManagementController::class, 'banSelected']);
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy']);
    
    Route::get('/balance', [BalanceController::class, 'getBalance']);
    // Пополнение баланса
    Route::post('/balance/deposit', [BalanceController::class, 'deposit']);

    Route::get('/orders/{order}/bids', [BidController::class, 'index']);
    Route::post('/bids', [BidController::class, 'store']);
    Route::post('/bids/{bid}/accept', [BidController::class, 'accept']);
    Route::post('/bids/{bid}/reject', [BidController::class, 'reject']);
     Route::post('/orders/{order}/disputes', [DisputeController::class, 'openDispute']);
    Route::get('/disputes/{dispute}', [DisputeController::class, 'show']);

    // Только для админов
        Route::get('/disputes', [DisputeController::class, 'index']);
        Route::put('/disputes/{dispute}', [DisputeController::class, 'update']);


});