<?php

namespace App\Exports;

use App\Models\Referral;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ReferralExport implements FromCollection, WithHeadings
{
    protected $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function collection()
    {
        $query = Referral::with(['referrer', 'referred']);

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['referrer'])) {
            $query->whereHas('referrer', fn($q) =>
                $q->where('name', 'like', '%' . $this->filters['referrer'] . '%')
            );
        }

        return $query->get()->map(function ($referral) {
            return [
                'ID' => $referral->referral_id,
                'Referrer' => $referral->referrer->name ?? 'N/A',
                'Referred' => $referral->referred->name ?? 'N/A',
                'Code' => $referral->referral_code,
                'Status' => ucfirst($referral->status),
                'Created At' => $referral->created_at->format('Y-m-d H:i'),
            ];
        });
    }

    public function headings(): array
    {
        return ['ID', 'Referrer', 'Referred', 'Code', 'Status', 'Created At'];
    }
}
