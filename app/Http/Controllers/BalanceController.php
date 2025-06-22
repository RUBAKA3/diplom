<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BalanceController extends Controller
{
    /**
     * Получить текущий баланс пользователя
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBalance(Request $request)
    {
        return response()->json([
            'balance' => $request->user()->balance
        ]);
    }

    /**
     * Пополнить баланс пользователя
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function deposit(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1|max:100000'
        ]);

        $user = $request->user();
        $user->balance += $validated['amount'];
        $user->save();

        return response()->json([
            'success' => true,
            'new_balance' => $user->balance,
            'message' => 'Баланс успешно пополнен!'
        ]);
    }
}