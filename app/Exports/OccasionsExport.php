<?php

namespace App\Exports;

use App\Models\Occasion;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OccasionsExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Occasion::all();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Name',
            'Description',
            'Created At',
            'Updated At'
        ];
    }

    public function map($occasion): array
    {
        return [
            $occasion->id,
            $occasion->name,
            $occasion->description ?? '',
            $occasion->created_at ? $occasion->created_at->format('Y-m-d H:i:s') : '',
            $occasion->updated_at ? $occasion->updated_at->format('Y-m-d H:i:s') : '',
        ];
    }
}

