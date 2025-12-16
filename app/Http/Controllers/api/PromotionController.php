<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function index()
    {
        $promotions = Promotion::orderBy('created_at', 'desc')->paginate(10);
        return response()->json($promotions);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'required|string|unique:promotions',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'discount_percent' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
        ]);

        $promotion = Promotion::create($validated);
        return response()->json(['message' => 'Tạo khuyến mãi thành công', 'data' => $promotion], 201);
    }

    public function show(Promotion $promotion)
    {
        return response()->json($promotion);
    }

    public function update(Request $request, Promotion $promotion)
    {
        $promotion->update($request->all());
        return response()->json(['message' => 'Cập nhật thành công', 'data' => $promotion]);
    }

    public function destroy(Promotion $promotion)
    {
        $promotion->delete();
        return response()->json(['message' => 'Đã xóa khuyến mãi!']);
    }

    public function usages(Promotion $promotion)
    {
        $usages = $promotion->usages()->with(['user', 'order'])->get();
        return response()->json($usages);
    }
}
