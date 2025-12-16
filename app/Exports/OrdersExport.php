<?php

namespace App\Exports;

use App\Models\Order;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class OrdersExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        return Order::with('user')->get();
    }

    public function headings(): array
    {
        return [
            'Order ID',
            'User Email',
            'Total Amount',
            'Status',
            'Delivery Address',
            'Created At'
        ];
    }

    public function map($order): array
    {
        return [
            $order->id,
            $order->user->email ?? 'Guest',
            $order->total_amount,
            $order->status,
            $order->delivery_address,
            $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : '',
        ];
    }
}
