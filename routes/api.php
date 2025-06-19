<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\FrelancersController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReviewController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/



Route::post('/register',[AuthController::class, 'register']);
Route::post('/login',[AuthController::class, 'login']);
Route::get('/users/{id}', [AuthController::class, 'profile']);
Route::get('/orde', [OrderController::class, 'index']);
Route::get('/orders/{category_id}', [OrderController::class, 'byCategory']);
Route::get('/order/{id}', [OrderController::class, 'show']);
Route::get('/freelancers', [FrelancersController::class, 'index']);
Route::get('/freelancer-orders/{freelancerId}', [FrelancersController::class, 'getFreelancerOrders']);
Route::get('/reviews/{userId}', [ReviewController::class, 'getUserReviews']);

Route::middleware(['auth:api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/logout', [AuthController::class, 'logout']);
    Route::post('/freelancer-profiles', [AuthController::class, 'store']);
    Route::get('/freelancer', [AuthController::class, 'frelancerprof']);
    Route::post('/orders', [OrderController::class, 'ordercr']);
    Route::get('/user/orders', [OrderController::class, 'userOrders']);
    Route::put('/orders/{order}/cancel', [OrderController::class, 'cancelOrder']);
    Route::put('/profile-refresh', [AuthController::class, 'updateProfile']);
    Route::post('/profile/user/avatar', [AuthController::class, 'updateAvatar']);
    Route::delete('/profile/user/avatar', [AuthController::class, 'updateAvatar']);
    Route::post('/assign-freelancer', [FrelancersController::class, 'assignFreelancer']);
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::post('/orders/{order}/close', [ReviewController::class, 'closeOrder']);
    Route::post('/orders/{order}/complete', [ReviewController::class, 'completeOrder']);

    Route::get('/chat-create', [ChatController::class, 'getOrCreateChat']);
    Route::get('/chat/{chat}/messages', [ChatController::class, 'messages']);
    Route::post('/chat/{chatId}/send', [ChatController::class, 'sendMessage']);
    Route::get('/chats', [ChatController::class, 'getUserChats']);
});
Broadcast::routes(['middleware' => ['auth:api']]);

Route::get('/test', function() {
    return response()->json(['status' => 'API работает']);
});