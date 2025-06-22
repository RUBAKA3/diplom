<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
class VerifControleer extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    
    public function verify($user_id, Request $request)
{   
    if (!$request->hasValidSignature()) {
        return response()->json(["msg" => 'invalid'], 401);
    }
    
    $user = User::where('id', $user_id)->first(); // Здесь изменяем 'id' на 'user_id'
    if (!$user) {
        return response()->json(["status" => 404, "message" => "Пользователь не найден"], 404);
    }

    if (!$user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        return redirect('http://localhost:3000/user');
    } else {
        return response()->json(["status" => 400, "message" => "Email уже подтвержден"], 400);
    }
}


    
}