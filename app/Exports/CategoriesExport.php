<?php

namespace App\Exports;

use App\Models\Category;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CategoriesExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Category::all();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Description',
            'Image URL',
            'Created At',
            'Updated At'
        ];
    }

    public function map($category): array
    {
        return [
            $category->id,
            $category->name,
            $category->description ?? '',
            $category->image_url ?? '',
            $category->created_at ? $category->created_at->format('Y-m-d H:i:s') : '',
            $category->updated_at ? $category->updated_at->format('Y-m-d H:i:s') : '',
        ];
    }
}

