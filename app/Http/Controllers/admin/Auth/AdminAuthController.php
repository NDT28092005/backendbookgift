<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function me(Request $request)
    {
        $user = $request->user();

        // Chỉ trả về admin
        if ($user && $user->role === 'admin') {
            return response()->json([
                'admin' => $user
            ]);
        }

        return response()->json([
            'message' => 'Bạn không có quyền truy cập'
        ], 403);
    }
    public function login(Request $request)
    {
        // ✅ Validate input - accept cả email và name
        $request->validate([
            'email' => 'nullable|string',
            'name' => 'nullable|string',
            'password' => 'required',
        ]);

        // ✅ Kiểm tra có ít nhất email hoặc name
        if (!$request->email && !$request->name) {
            return response()->json(['message' => 'Vui lòng nhập email hoặc tên đăng nhập'], 422);
        }

        // ✅ Tìm user theo email hoặc name
        $user = null;
        if ($request->email) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->name) {
            $user = User::where('name', $request->name)->first();
        }

        // ✅ Kiểm tra user tồn tại và password đúng
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Sai email hoặc mật khẩu'], 401);
        }

        // ✅ Kiểm tra vai trò có phải admin không
        if ($user->role !== 'admin') {
            return response()->json(['message' => 'Bạn không có quyền truy cập admin'], 403);
        }

        // ✅ Tạo token đăng nhập (nếu dùng Sanctum)
        $token = $user->createToken('admin_token', ['admin'])->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'admin' => $user,
            'token' => $token,
        ]);
    }
}
