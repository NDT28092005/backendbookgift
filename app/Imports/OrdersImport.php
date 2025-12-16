<?php

namespace App\Imports;

use App\Models\Order;
use App\Models\User;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Carbon\Carbon;

class OrdersImport implements ToModel, WithHeadingRow, WithValidation
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {
        // Tìm user theo email
        $user = null;
        if (isset($row['user_email']) && !empty($row['user_email'])) {
            $user = User::where('email', $row['user_email'])->first();
            // Nếu email được cung cấp nhưng không tìm thấy user, skip row
            if (!$user) {
                return null;
            }
        }
        // Nếu không có user_email, cho phép tạo order với user_id = null (guest order)

        // Xử lý created_at
        $createdAt = now();
        if (isset($row['created_at']) && !empty($row['created_at'])) {
            try {
                $createdAt = Carbon::parse($row['created_at']);
            } catch (\Exception $e) {
                $createdAt = now();
            }
        }

        // Tạo hoặc cập nhật order
        $orderData = [
            'user_id' => $user ? $user->id : null,
            'total_amount' => $row['total_amount'] ?? 0,
            'status' => $row['status'] ?? 'pending',
            'delivery_address' => $row['delivery_address'] ?? '',
        ];

        // Nếu có Order ID và order đã tồn tại, update
        if (isset($row['order_id']) && !empty($row['order_id'])) {
            $order = Order::find($row['order_id']);
            if ($order) {
                $order->update($orderData);
                return $order;
            }
        }

        // Tạo order mới
        $order = new Order($orderData);
        $order->save();
        
        // Set created_at nếu cần
        if ($createdAt !== now()) {
            $order->created_at = $createdAt;
            $order->save();
        }

        return $order;
    }

    public function rules(): array
    {
        return [
            'total_amount' => 'required|numeric|min:0',
            'status' => 'nullable|in:pending,processing,completed,cancelled',
            'delivery_address' => 'nullable|string',
        ];
    }
}

