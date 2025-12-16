<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Imports\CategoriesImport;
use App\Exports\CategoriesExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class CategoryController extends Controller
{
    public function index()
    {
        return Category::all();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        // Xử lý upload ảnh
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('categories', 'public');
            $data['image_url'] = asset('storage/' . $path);
        }

        $category = Category::create($data);
        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        return $category;
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        // Xử lý upload ảnh mới
        if ($request->hasFile('image')) {
            // Xóa ảnh cũ nếu tồn tại
            if ($category->image_url) {
                $oldPath = str_replace(asset('storage/'), '', $category->image_url);
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('image')->store('categories', 'public');
            $data['image_url'] = asset('storage/' . $path);
        }

        $category->update($data);
        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        // Xóa ảnh nếu tồn tại
        if ($category->image_url) {
            $oldPath = str_replace(asset('storage/'), '', $category->image_url);
            Storage::disk('public')->delete($oldPath);
        }

        $category->delete();
        return response()->json(null, 204);
    }

    /**
     * Import categories from Excel file
     */
    public function import(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|mimes:xlsx,xls|max:5120', // Max 5MB
            ]);

            if (!$request->hasFile('file')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Không tìm thấy file upload'
                ], 400);
            }

            $file = $request->file('file');
            
            Excel::import(new CategoriesImport, $file);
            
            return response()->json([
                'success' => true,
                'message' => 'Import danh mục thành công!'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errors = $e->errors();
            $errorMessages = [];
            foreach ($errors as $field => $messages) {
                $errorMessages = array_merge($errorMessages, $messages);
            }
            return response()->json([
                'success' => false,
                'message' => 'Lỗi validation: ' . implode(', ', $errorMessages),
                'errors' => $errors
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Category import error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi import: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export categories to Excel file
     */
    public function export()
    {
        return Excel::download(new CategoriesExport, 'categories_' . date('Y-m-d_His') . '.xlsx');
    }

    /**
     * Delete all categories
     */
    public function deleteAll()
    {
        try {
            DB::beginTransaction();
            
            // Xóa tất cả ảnh trước
            $categories = Category::all();
            foreach ($categories as $category) {
                if ($category->image_url) {
                    $oldPath = str_replace(asset('storage/'), '', $category->image_url);
                    Storage::disk('public')->delete($oldPath);
                }
            }
            
            // Xóa tất cả categories
            $count = Category::count();
            Category::truncate();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => "Đã xóa tất cả {$count} danh mục thành công!"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa: ' . $e->getMessage()
            ], 500);
        }
    }
}
