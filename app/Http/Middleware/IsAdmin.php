<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if ($user && in_array($user->role, ['admin', 'manager'])) {
            return $next($request);
        }
        
        return response()->json([
            'message' => 'Bạn không có quyền truy cập. Vui lòng đăng nhập với tài khoản admin.'
        ], 403);
    }
}
