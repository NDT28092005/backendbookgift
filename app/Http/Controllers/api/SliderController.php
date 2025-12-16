<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Slider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SliderController extends Controller
{
    /**
     * Lấy danh sách sliders (public - không cần auth)
     */
    public function index()
    {
        $sliders = Slider::where('is_active', true)
            ->orderBy('order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json($sliders);
    }

    /**
     * Lấy tất cả sliders (admin - cần auth)
     */
    public function adminIndex()
    {
        $sliders = Slider::orderBy('order', 'asc')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json($sliders);
    }

    /**
     * Tạo slider mới
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'link' => 'nullable|string|max:500',
        ]);

        // Upload ảnh
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('sliders', 'public');
            $validated['image_url'] = asset('storage/' . $path);
        }

        $validated['order'] = $validated['order'] ?? 0;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $slider = Slider::create([
            'image_url' => $validated['image_url'],
            'order' => $validated['order'],
            'is_active' => $validated['is_active'],
            'link' => $validated['link'] ?? null,
        ]);

        return response()->json(['message' => 'Tạo slider thành công', 'data' => $slider], 201);
    }

    /**
     * Cập nhật slider
     */
    public function update(Request $request, Slider $slider)
    {
        $validated = $request->validate([
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'link' => 'nullable|string|max:500',
        ]);

        // Nếu có ảnh mới, upload và xóa ảnh cũ
        if ($request->hasFile('image')) {
            // Xóa ảnh cũ
            if ($slider->image_url) {
                $oldPath = str_replace(asset('storage/'), '', $slider->image_url);
                Storage::disk('public')->delete($oldPath);
            }

            // Upload ảnh mới
            $path = $request->file('image')->store('sliders', 'public');
            $validated['image_url'] = asset('storage/' . $path);
        }

        $slider->update($validated);

        return response()->json(['message' => 'Cập nhật slider thành công', 'data' => $slider]);
    }

    /**
     * Xóa slider
     */
    public function destroy(Slider $slider)
    {
        // Xóa ảnh
        if ($slider->image_url) {
            $oldPath = str_replace(asset('storage/'), '', $slider->image_url);
            Storage::disk('public')->delete($oldPath);
        }

        $slider->delete();

        return response()->json(['message' => 'Xóa slider thành công']);
    }

    /**
     * Xem chi tiết slider
     */
    public function show(Slider $slider)
    {
        return response()->json($slider);
    }
}

