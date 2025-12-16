<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\OrderService;

class CancelExpiredOrders extends Command
{
    protected $signature = 'orders:cancel-expired';
    protected $description = 'Tự động hủy các đơn hàng pending đã quá hạn';

    public function handle(OrderService $orderService)
    {
        $count = $orderService->cancelExpiredOrders();

        $this->info("Đã hủy {$count} đơn hàng expired.");
    }
}
