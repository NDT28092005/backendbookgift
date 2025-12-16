<?php

namespace App\Imports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CategoriesImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Normalize keys - chấp nhận cả chữ hoa và chữ thường
        $name = $row['name'] ?? $row['Name'] ?? $row['NAME'] ?? null;
        $description = $row['description'] ?? $row['Description'] ?? $row['DESCRIPTION'] ?? null;
        
        // Bỏ qua dòng trống
        if (empty($name) || trim($name) === '') {
            return null;
        }

        $name = trim($name);
        $description = $description ? trim($description) : null;

        // Kiểm tra xem category đã tồn tại chưa (theo tên)
        $existingCategory = Category::where('name', $name)->first();
        
        if ($existingCategory) {
            // Cập nhật category hiện có
            $existingCategory->update([
                'description' => $description ?? $existingCategory->description,
            ]);
            return null; // Không tạo mới, chỉ cập nhật
        }

        // Tạo category mới
        return new Category([
            'name' => $name,
            'description' => $description,
        ]);
    }

    public function rules(): array
    {
        // Validation sẽ được thực hiện trong model() method
        // Vì WithHeadingRow có thể normalize keys khác nhau
        return [];
    }
}

