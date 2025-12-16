<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserAnniversary;
use Illuminate\Http\Request;

class UserAnniversaryController extends Controller
{
    // Lấy tất cả anniversaries của user
    public function index(User $user)
    {
        return response()->json($user->anniversaries()->orderBy('event_date')->get());
    }

    // Lấy 1 anniversary của user
    public function show(User $user, $anniversaryId)
    {
        $anniversary = $user->anniversaries()->where('anniversary_id', $anniversaryId)->first();

        if (!$anniversary) {
            return response()->json(['message' => 'Anniversary not found'], 404);
        }

        return response()->json($anniversary);
    }

    // Tạo mới
    public function store(Request $request, User $user)
    {
        $data = $request->validate([
            'event_name' => 'required|string|max:100',
            'event_date' => 'required|date',
        ]);

        $anniversary = $user->anniversaries()->create($data);

        return response()->json($anniversary, 201);
    }

    // Cập nhật
    public function update(Request $request, User $user, $anniversaryId)
    {
        $anniversary = $user->anniversaries()->where('anniversary_id', $anniversaryId)->first();

        if (!$anniversary) {
            return response()->json(['message' => 'Anniversary not found'], 404);
        }

        $data = $request->validate([
            'event_name' => 'required|string|max:100',
            'event_date' => 'required|date',
        ]);

        $anniversary->update($data);

        return response()->json($anniversary);
    }

    // Xóa
    public function destroy(User $user, $anniversaryId)
    {
        $anniversary = $user->anniversaries()->where('anniversary_id', $anniversaryId)->first();

        if (!$anniversary) {
            return response()->json(['message' => 'Anniversary not found'], 404);
        }

        $anniversary->delete();

        return response()->json(['message' => 'Deleted successfully']);
    }
}
