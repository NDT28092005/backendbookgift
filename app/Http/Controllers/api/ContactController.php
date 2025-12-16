<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * Lưu tin nhắn liên hệ từ form
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Lấy user_id nếu đã đăng nhập
        $userId = null;
        if ($request->user()) {
            $userId = $request->user()->id;
        }

        $contactMessage = ContactMessage::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'message' => $request->message,
            'user_id' => $userId,
            'status' => 'new', // new, read, replied
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cảm ơn bạn đã liên hệ! Chúng tôi sẽ phản hồi sớm nhất có thể.',
            'data' => $contactMessage
        ], 201);
    }

    /**
     * Lấy danh sách tin nhắn liên hệ (cho admin)
     */
    public function index(Request $request)
    {
        // Chỉ admin mới xem được
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $status = $request->query('status', 'all');
        $query = ContactMessage::with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return response()->json($query->get());
    }

    /**
     * Đánh dấu đã đọc
     */
    public function markAsRead(Request $request, $id)
    {
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $contactMessage = ContactMessage::findOrFail($id);
        $contactMessage->update(['status' => 'read']);

        return response()->json([
            'success' => true,
            'message' => 'Đã đánh dấu là đã đọc'
        ]);
    }
}

