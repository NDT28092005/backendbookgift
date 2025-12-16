<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderService
{
    public function cancelExpiredOrders()
    {
        $expiredOrders = Order::with('items.product')
            ->where('status', 'pending')
            ->where('created_at', '<', Carbon::now()->subMinutes(30))
            ->get();

        $count = 0;
        foreach ($expiredOrders as $order) {
            DB::beginTransaction();
            try {
                // Đơn hàng pending chưa thanh toán nên không cần cộng lại tồn kho
                $order->update(['status' => 'cancelled']);
                DB::commit();
                $count++;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Error cancelling expired order', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $count;
    }
}
