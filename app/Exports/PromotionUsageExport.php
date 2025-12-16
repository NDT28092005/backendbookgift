<?php

namespace App\Exports;

use App\Models\PromotionUsage;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PromotionUsageExport implements FromCollection, WithHeadings
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = PromotionUsage::with(['promotion', 'user', 'order']);

        if (!empty($this->filters['promotion_code'])) {
            $query->whereHas('promotion', fn($q) =>
                $q->where('code', 'like', '%' . $this->filters['promotion_code'] . '%')
            );
        }

        if (!empty($this->filters['user'])) {
            $query->whereHas('user', fn($q) =>
                $q->where('name', 'like', '%' . $this->filters['user'] . '%')
            );
        }

        if (isset($this->filters['is_valid'])) {
            $query->where('is_valid', $this->filters['is_valid']);
        }

        return $query->get()->map(function ($usage) {
            return [
                'ID' => $usage->usage_id,
                'Promotion' => $usage->promotion->title ?? 'N/A',
                'User' => $usage->user->name ?? 'Guest',
                'Order ID' => $usage->order_id,
                'Discount Amount' => $usage->discount_amount,
                'Valid' => $usage->is_valid ? 'Yes' : 'No',
                'Used At' => $usage->created_at->format('Y-m-d H:i'),
            ];
        });
    }

    public function headings(): array
    {
        return ['ID', 'Promotion', 'User', 'Order ID', 'Discount Amount', 'Valid', 'Used At'];
    }
}
