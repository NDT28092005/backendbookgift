<?php

namespace App\Imports;

use App\Models\Occasion;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class OccasionsImport implements ToModel, WithHeadingRow, WithValidation
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

        // Kiểm tra xem occasion đã tồn tại chưa (theo tên)
        $existingOccasion = Occasion::where('name', $name)->first();
        
        if ($existingOccasion) {
            // Cập nhật occasion hiện có
            $existingOccasion->update([
                'description' => $description ?? $existingOccasion->description,
            ]);
            return null; // Không tạo mới, chỉ cập nhật
        }

        // Tạo occasion mới
        return new Occasion([
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

