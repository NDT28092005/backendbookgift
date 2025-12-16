<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Services\GHTKService;

class OrderController extends Controller
{
    /**
     * 🧾 Danh sách đơn hàng
     */
    public function index(Request $request)
    {
        $orders = Order::with(['user', 'items.product.images'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(10);

        return response()->json($orders);
    }

    /**
     * 🧾 Tạo đơn hàng khi checkout
     */
    public function store(Request $request)
    {
        $request->validate([
            'delivery_address'   => 'required|string|max:255',
            'customer_name'      => 'required|string|max:100',
            'customer_phone'     => 'required|string|max:20',
            'customer_province'  => 'required|string|max:100',
            'customer_district'  => 'required|string|max:100',
            'customer_ward'      => 'required|string|max:100',
            'shipping_fee'       => 'required|numeric|min:0',
            'print_label'        => 'nullable|boolean',
        ]);

        $user = $request->user();

        // Tính tổng tiền từ giỏ hàng
        $cartItems = DB::table('cart_items')
            ->join('products', 'cart_items.product_id', '=', 'products.id')
            ->where('cart_items.user_id', $user->id)
            ->select('cart_items.*', 'products.price')
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['message' => 'Giỏ hàng trống'], 400);
        }

        $total = $cartItems->sum(fn($item) => $item->price_per_unit * $item->quantity);

        // Tạo order
        $order = Order::create([
            'user_id'            => $user->id,
            'total_amount'       => $total,
            'shipping_fee' => $request->shipping_fee,
            'delivery_address'   => $request->delivery_address,
            'customer_name'      => $request->customer_name,
            'customer_phone'     => $request->customer_phone,
            'customer_province'  => $request->customer_province,
            'customer_district'  => $request->customer_district,
            'customer_ward'      => $request->customer_ward,
            'expires_at'         => now()->addMinutes(5),
            'print_label'        => $request->boolean('print_label'),
        ]);

        // Thêm order_items
        foreach ($cartItems as $item) {
            $order->items()->create([
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'price' => $item->price_per_unit,
            ]);
        }

        // Xóa giỏ hàng sau khi checkout
        DB::table('cart_items')->where('user_id', $user->id)->delete();

        // Tạo VietQR fake (mô phỏng)
        $transferContent = "ORDER_" . $order->id . "_" . strtoupper(substr(md5($user->email), 0, 5));
        $qrCode = "https://api.vietqr.io/image/$transferContent.png";

        return response()->json([
            'order_id' => $order->id,
            'qr_code' => $qrCode,
            'addInfo' => $transferContent,
            'shipping_fee' => $order->shipping_fee,
            'amount' => $order->total_amount + $order->shipping_fee,
        ]);
    }
    public function printLabel($orderId, GHTKService $ghtkService)
    {
        $order = Order::findOrFail($orderId);

        // 1️⃣ Chưa có mã vận đơn
        if (!$order->tracking_code) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng chưa có mã vận đơn. Vui lòng đợi đơn hàng được xử lý.'
            ], 400);
        }

        // 2️⃣ Kiểm tra print_label: nếu print_label = false thì không cho in
        // Logic: print_label = true → cho phép in, print_label = false → không cho in
        if ($order->print_label === false) {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng này không yêu cầu in nhãn (phù hợp khi tặng quà)'
            ], 400);
        }

        // 3️⃣ Gọi service lấy PDF
        return $ghtkService->getLabelPdf(
            $order->tracking_code,
            request('page_size', 'A6'),
            request('original', 'portrait')
        );
    }
    /**
     * ✅ Đánh dấu đã thanh toán
     */
    public function markPaid(Request $request, GHTKService $ghtkService)
    {
        $request->validate(['order_id' => 'required|integer']);
        $order = Order::with('items.product')->find($request->order_id);

        if (!$order || $order->status !== 'pending') {
            return response()->json(['message' => 'Không thể cập nhật đơn hàng'], 400);
        }

        DB::beginTransaction();
        try {
            // 📦 Giảm tồn kho khi thanh toán thành công
            foreach ($order->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);
                if ($product) {
                    $newStock = $product->stock_quantity - $item->quantity;
                    if ($newStock < 0) {
                        DB::rollBack();
                        return response()->json([
                            'message' => "Sản phẩm '{$product->name}' không đủ tồn kho. Tồn kho hiện tại: {$product->stock_quantity}, yêu cầu: {$item->quantity}",
                        ], 400);
                    }
                    $product->update(['stock_quantity' => $newStock]);
                }
            }

            $order->update(['status' => 'paid']);

            // Tích điểm thưởng: 10,000 VND = 1 điểm
            // Tính điểm dựa trên tổng tiền đơn hàng (total_amount + shipping_fee)
            $orderTotal = $order->total_amount + ($order->shipping_fee ?? 0);
            $pointsEarned = (int) floor($orderTotal / 10000);

            if ($pointsEarned > 0) {
                $order->user->increment('loyalty_points', $pointsEarned);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi khi cập nhật đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }

        // 🚚 Tạo vận đơn GHTK
        try {
            $ghtkOrder = $ghtkService->createShipment($order);
            
            // Refresh order và ghtkOrder để lấy dữ liệu mới nhất
            $order->refresh();
            if ($ghtkOrder) {
                $ghtkOrder->refresh();
                
                // Lấy label_id từ ghtk_orders và cập nhật vào tracking_code của orders
                if ($ghtkOrder->label_id) {
                    $order->update(['tracking_code' => $ghtkOrder->label_id]);
                    \Log::info("✅ Updated tracking_code for order {$order->id} from ghtk_orders.label_id: {$ghtkOrder->label_id}");
                } else {
                    // Nếu label_id chưa có, thử lấy từ response JSON
                    $response = json_decode($ghtkOrder->response, true);
                    $labelId = $response['order']['label'] ?? $response['order']['label_id'] ?? null;
                    if ($labelId) {
                        // Cập nhật cả ghtk_orders và orders
                        $ghtkOrder->update(['label_id' => $labelId]);
                        $order->update(['tracking_code' => $labelId]);
                        \Log::info("✅ Updated tracking_code from response JSON for order {$order->id}: {$labelId}");
                    } else {
                        \Log::warning("⚠️ GHTK order created but label_id is null", [
                            'order_id' => $order->id,
                            'ghtk_order_id' => $ghtkOrder->id,
                            'response_keys' => array_keys($response['order'] ?? [])
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            \Log::error("❌ GHTK shipment creation failed", [
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'message' => 'Thanh toán thành công nhưng tạo đơn GHTK thất bại',
                'error' => $e->getMessage()
            ], 500);
        }

        // Refresh lại order để có tracking_code mới nhất
        $order->refresh();

        return response()->json([
            'message' => 'Thanh toán & tạo đơn GHTK thành công',
            'order' => $order->fresh(['ghtkOrder']),
            'ghtk_order' => $ghtkOrder,
            'tracking_code' => $order->tracking_code
        ]);
    }

    /**
     * 🔄 Sync tracking_code từ ghtk_orders.label_id cho tất cả đơn hàng
     */
    public function syncTrackingCodes()
    {
        $orders = Order::whereNull('tracking_code')
            ->whereHas('ghtkOrder', function($q) {
                $q->whereNotNull('label_id');
            })
            ->with('ghtkOrder')
            ->get();

        $synced = 0;
        foreach ($orders as $order) {
            if ($order->syncTrackingCode()) {
                $synced++;
            }
        }

        return response()->json([
            'message' => "Đã sync {$synced} đơn hàng",
            'synced_count' => $synced,
            'total_orders' => $orders->count()
        ]);
    }


    /**
     * 🔍 Xem chi tiết đơn hàng
     */
    public function show($id)
    {
        $order = Order::with(['user', 'items.product.images', 'payment', 'ghtkOrder'])
            ->findOrFail($id);

        // Tự động sync tracking_code nếu chưa có nhưng có ghtkOrder với label_id
        if (!$order->tracking_code && $order->ghtkOrder && $order->ghtkOrder->label_id) {
            $order->syncTrackingCode();
            $order->refresh();
        }

        // Tự động sync GHTK status nếu có ghtkOrder
        if ($order->ghtkOrder && $order->ghtkOrder->label_id) {
            try {
                $ghtkService = app(\App\Services\GHTKService::class);
                $ghtkService->syncOrderStatus($order->ghtkOrder);
                $order->refresh();
                // Load lại tất cả relationships sau khi refresh
                $order->load(['user', 'items.product.images', 'payment', 'ghtkOrder']);
            } catch (\Exception $e) {
                \Log::warning("Failed to sync GHTK status for order {$order->id}", [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json($order);
    }

    /**
     * ❌ Hủy các đơn quá hạn (tự động)
     */
    public function cancelExpired()
    {
        $expiredOrders = Order::with('items.product')
            ->where('status', 'pending')
            ->where('expires_at', '<', now())
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
                \Log::error('Error cancelling expired order', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json(['message' => "Đã hủy $count đơn hàng quá hạn"]);
    }

    /**
     * 🔄 Cập nhật trạng thái đơn hàng (Admin)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|string|in:pending,paid,processing,completed,cancelled'
        ]);

        $order = Order::with('items.product')->findOrFail($id);
        $oldStatus = $order->status;
        $newStatus = $request->status;

        // Nếu trạng thái không thay đổi, không cần làm gì
        if ($oldStatus === $newStatus) {
            return response()->json([
                'message' => 'Trạng thái đơn hàng không thay đổi',
                'order' => $order
            ]);
        }

        DB::beginTransaction();
        try {
            // 📦 Xử lý tồn kho khi thay đổi trạng thái

            // Trường hợp 1: pending → paid (thanh toán thành công) → Giảm tồn kho và tích điểm
            if ($oldStatus === 'pending' && $newStatus === 'paid') {
                foreach ($order->items as $item) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $newStock = $product->stock_quantity - $item->quantity;
                        if ($newStock < 0) {
                            DB::rollBack();
                            return response()->json([
                                'message' => "Sản phẩm '{$product->name}' không đủ tồn kho. Tồn kho hiện tại: {$product->stock_quantity}, yêu cầu: {$item->quantity}",
                            ], 400);
                        }
                        $product->update(['stock_quantity' => $newStock]);
                    }
                }

                // Tích điểm thưởng: 10,000 VND = 1 điểm
                // Tính điểm dựa trên tổng tiền đơn hàng (total_amount + shipping_fee)
                $orderTotal = $order->total_amount + ($order->shipping_fee ?? 0);
                $pointsEarned = (int) floor($orderTotal / 10000);

                if ($pointsEarned > 0) {
                    $order->user->increment('loyalty_points', $pointsEarned);
                }
            }

            // Trường hợp 2: paid/processing → cancelled → Cộng lại tồn kho và trừ điểm đã tích
            if (in_array($oldStatus, ['paid', 'processing']) && $newStatus === 'cancelled') {
                foreach ($order->items as $item) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $product->increment('stock_quantity', $item->quantity);
                    }
                }

                // Trừ điểm đã tích khi hủy đơn (nếu đã tích điểm)
                $orderTotal = $order->total_amount + ($order->shipping_fee ?? 0);
                $pointsEarned = (int) floor($orderTotal / 10000);

                if ($pointsEarned > 0) {
                    $user = $order->user;
                    $currentPoints = $user->loyalty_points ?? 0;
                    $pointsToDeduct = min($pointsEarned, $currentPoints);
                    if ($pointsToDeduct > 0) {
                        $user->decrement('loyalty_points', $pointsToDeduct);
                    }
                }
            }

            // Trường hợp 3: cancelled → paid (khôi phục đơn hàng) → Giảm tồn kho lại và tích điểm
            if ($oldStatus === 'cancelled' && $newStatus === 'paid') {
                foreach ($order->items as $item) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $newStock = $product->stock_quantity - $item->quantity;
                        if ($newStock < 0) {
                            DB::rollBack();
                            return response()->json([
                                'message' => "Sản phẩm '{$product->name}' không đủ tồn kho. Tồn kho hiện tại: {$product->stock_quantity}, yêu cầu: {$item->quantity}",
                            ], 400);
                        }
                        $product->update(['stock_quantity' => $newStock]);
                    }
                }

                // Tích điểm thưởng: 10,000 VND = 1 điểm
                $orderTotal = $order->total_amount + ($order->shipping_fee ?? 0);
                $pointsEarned = (int) floor($orderTotal / 10000);

                if ($pointsEarned > 0) {
                    $order->user->increment('loyalty_points', $pointsEarned);
                }
            }

            // Cập nhật trạng thái đơn hàng
            $updateData = ['status' => $newStatus];

            // Nếu hủy đơn, thêm thông tin hủy
            if ($newStatus === 'cancelled') {
                $updateData['cancelled_at'] = now();
                if (!$order->cancellation_reason) {
                    $updateData['cancellation_reason'] = 'admin_cancelled';
                }
            }

            $order->update($updateData);
            DB::commit();

            // Load lại order với relationships
            $order->load(['user', 'items.product.images']);

            return response()->json([
                'message' => 'Cập nhật trạng thái đơn hàng thành công',
                'order' => $order
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi khi cập nhật trạng thái đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function cancel(Request $request, $orderId)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $order = Order::with(['items.product.images'])
            ->where('id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);
        }

        $allowedStatuses = ['pending', 'paid', 'processing'];
        if (!in_array($order->status, $allowedStatuses, true)) {
            return response()->json([
                'message' => 'Đơn hàng không thể hủy ở trạng thái hiện tại'
            ], 400);
        }

        // Lấy lý do hủy từ request (hỗ trợ cả 'reason' và 'cancel_reason')
        $cancellationReason = $request->input('reason')
            ?? $request->input('cancel_reason')
            ?? 'customer_cancelled';

        DB::beginTransaction();
        try {
            // 📦 Cộng lại tồn kho nếu đơn hàng đã được thanh toán (paid hoặc processing)
            $shouldRestoreStock = in_array($order->status, ['paid', 'processing']);

            if ($shouldRestoreStock) {
                foreach ($order->items as $item) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $product->increment('stock_quantity', $item->quantity);
                    }
                }

                // Trừ điểm đã tích khi hủy đơn (nếu đã tích điểm)
                $orderTotal = $order->total_amount + ($order->shipping_fee ?? 0);
                $pointsEarned = (int) floor($orderTotal / 10000);

                if ($pointsEarned > 0) {
                    $currentPoints = $user->loyalty_points ?? 0;
                    $pointsToDeduct = min($pointsEarned, $currentPoints);
                    if ($pointsToDeduct > 0) {
                        $user->decrement('loyalty_points', $pointsToDeduct);
                    }
                }
            }

            // Hoàn lại điểm đã sử dụng nếu có
            if ($order->loyalty_points_used > 0) {
                $user->increment('loyalty_points', $order->loyalty_points_used);
            }

            $order->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $cancellationReason,
            ]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi khi hủy đơn hàng',
                'error' => $e->getMessage()
            ], 500);
        }

        // Load lại order với các relationships bao gồm images
        $order->load(['items.product.images', 'user', 'ghtkOrder']);

        // Xác định message dựa trên payment_method
        // COD: Không hiển thị thông báo hoàn tiền (vì chưa thanh toán)
        // Bank transfer/Momo: Hiển thị thông báo hoàn tiền (vì đã thanh toán)
        $message = 'Đơn hàng đã được hủy.';
        
        // Chỉ hiển thị thông báo hoàn tiền nếu không phải COD
        // (vì bank_transfer và momo đều đã thanh toán trước)
        if ($order->payment_method && $order->payment_method !== 'cod') {
            $message = 'Đơn hàng đã được hủy. Chúng tôi sẽ hoàn tiền lại trong vòng 24 giờ.';
        }

        return response()->json([
            'message' => $message,
            'order' => $order
        ]);
    }
}
