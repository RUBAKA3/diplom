<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(Request $request)
    {
        $query = User::query();
        
        // Поиск пользователей
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('role', 'like', "%{$search}%");
            });
        }
        
        // Пагинация
        $users = $query->paginate(10);
        
        return response()->json($users);
    }

    /**
     * Update the specified user's role.
     */
    public function updateRole(Request $request, User $user)
    {
        $request->validate([
            'role' => 'required|in:user,freelancer,admin',
        ]);
        
        $user->role = $request->role;
        $user->save();
        
        return response()->json(['message' => 'Роль пользователя обновлена', 'user' => $user]);
    }

    /**
     * Toggle user's banned status.
     */
    public function toggleBan(User $user)
    {
        $user->is_banned = !$user->is_banned;
        $user->save();
        
        $message = $user->is_banned ? 'Пользователь заблокирован' : 'Пользователь разблокирован';
        
        return response()->json(['message' => $message, 'user' => $user]);
    }

    /**
     * Ban multiple users.
     */
    public function banSelected(Request $request)
    {
        $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
        ]);
        
        User::whereIn('id', $request->user_ids)
            ->update(['is_banned' => true]);
            
        return response()->json(['message' => 'Выбранные пользователи заблокированы']);
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        $user->delete();
        
        return response()->json(['message' => 'Пользователь удален']);
    }
}