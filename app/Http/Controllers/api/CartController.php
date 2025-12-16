<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Database\QueryException;

class CartController extends Controller
{
    /**
     * 🛒 Lấy giỏ hàng của người dùng
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        return response()->json($this->buildCartSnapshot($user->id));
    }

    /**
     * ➕ Thêm sản phẩm vào giỏ hàng (hoặc cập nhật số lượng)
     * quantity có thể âm để giảm số lượng
     */
    public function add(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|integer'
        ]);

        $quantityDelta = (int) $validated['quantity'];
        $status = null;

        try {
            DB::transaction(function () use ($user, $validated, $quantityDelta, &$status) {
                $cartItem = Cart::where('user_id', $user->id)
                    ->where('product_id', $validated['product_id'])
                    ->lockForUpdate()
                    ->first();

                if ($cartItem) {
                    $newQuantity = $cartItem->quantity + $quantityDelta;

                    if ($newQuantity <= 0) {
                        $cartItem->delete();
                        $status = 'removed';
                        return;
                    }

                    $cartItem->update(['quantity' => $newQuantity]);
                    $status = 'updated';
                    return;
                }

                if ($quantityDelta <= 0) {
                    $status = 'invalid';
                    return;
                }

                $status = 'added';

                Cart::create([
                    'user_id' => $user->id,
                    'product_id' => $validated['product_id'],
                    'quantity' => $quantityDelta
                ]);
            });
        } catch (QueryException $exception) {
            if ($exception->getCode() !== '23000') {
                throw $exception;
            }

            $cartItem = Cart::where('user_id', $user->id)
                ->where('product_id', $validated['product_id'])
                ->first();

            if ($cartItem) {
                $newQuantity = $cartItem->quantity + $quantityDelta;

                if ($newQuantity <= 0) {
                    $cartItem->delete();
                    $status = 'removed';
                    $cartItem = null;
                } else {
                    $cartItem->update(['quantity' => $newQuantity]);
                    $status = 'updated';
                }
            } else {
                throw $exception;
            }
        }

        if ($status === 'invalid') {
            return response()->json([
                'message' => 'Không thể giảm số lượng sản phẩm chưa có trong giỏ hàng'
            ], 400);
        }

        $cartSnapshot = $this->buildCartSnapshot($user->id);

        switch ($status) {
            case 'removed':
                $message = 'Sản phẩm đã được xóa khỏi giỏ hàng';
                break;
            case 'updated':
                $message = 'Cập nhật số lượng thành công';
                break;
            default:
                $message = 'Thêm sản phẩm vào giỏ hàng thành công';
        }

        return response()->json([
            'message' => $message,
            'items' => $cartSnapshot['items'],
            'total_amount' => $cartSnapshot['total_amount'],
        ]);
    }


    /**
     * 💳 Thanh toán (tạo order)
     */
    public function checkout(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $validated = $request->validate([
            'delivery_address' => 'required|string|max:255',
            'payment_method'   => 'required|string|in:cod,momo,bank_transfer',
            'customer_name'    => 'nullable|string|max:255',
            'customer_phone'   => 'nullable|string|max:20',
            'customer_province' => 'nullable|string|max:255',
            'customer_district' => 'nullable|string|max:255',
            'customer_ward'    => 'nullable|string|max:255',
            'wrapping_paper_id' => 'nullable|exists:wrapping_papers,id',
            'wrapping_paper'   => 'nullable|string|max:255',
            'decorative_accessory_id' => 'nullable|exists:decorative_accessories,id',
            'decorative_accessories' => 'nullable|string|max:255',
            'card_type_id'     => 'nullable|exists:card_types,id',
            'card_type'        => 'nullable|string|max:255',
            'card_note'        => 'nullable|string|max:1000',
            'loyalty_points_used' => 'nullable|integer|min:0',
            'print_label'      => 'nullable|boolean',
            'shipping_fee'     => 'nullable|numeric|min:0',
        ]);

        $cartItems = Cart::with('product')->where('user_id', $user->id)->get();
        if ($cartItems->isEmpty()) return response()->json(['message' => 'Giỏ hàng trống'], 400);

        $total = $cartItems->sum(fn($item) => ($item->product->price ?? 0) * $item->quantity);
        
        // Xử lý sử dụng điểm thưởng (1 điểm = 100 VND)
        $loyaltyPointsUsed = $validated['loyalty_points_used'] ?? 0;
        $discountAmount = 0;
        
        DB::beginTransaction();
        try {
            if ($loyaltyPointsUsed > 0) {
                // Lấy lại user với điểm mới nhất trong transaction
                $user = \App\Models\User::lockForUpdate()->find($user->id);
                $availablePoints = $user->loyalty_points ?? 0;
                
                if ($loyaltyPointsUsed > $availablePoints) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'Bạn không đủ điểm thưởng. Điểm hiện có: ' . $availablePoints
                    ], 400);
                }
                
                // Tính số tiền giảm (1 điểm = 100 VND)
                $discountAmount = $loyaltyPointsUsed * 100;
                
                // Đảm bảo không giảm quá tổng tiền
                if ($discountAmount > $total) {
                    $discountAmount = $total;
                    $loyaltyPointsUsed = (int) ceil($total / 100);
                }
                
                // Trừ điểm từ user trong transaction
                $user->decrement('loyalty_points', $loyaltyPointsUsed);
            }
            
            // Tính tổng tiền sau khi giảm
            $shippingFee = $validated['shipping_fee'] ?? 0;
            
            // Nếu shipping_fee = 0 hoặc không có, tính lại từ GHTK
            if ($shippingFee == 0 && !empty($validated['customer_province']) && !empty($validated['customer_district']) && !empty($validated['delivery_address'])) {
                try {
                    // Tính trọng lượng từ giỏ hàng
                    $totalWeight = $cartItems->sum(function($item) {
                        return ($item->product->weight_in_gram ?? 200) * $item->quantity;
                    });
                    if ($totalWeight <= 0) $totalWeight = 500;
                    
                    // Gọi API tính phí ship từ GHTK
                    $shippingResponse = \Illuminate\Support\Facades\Http::timeout(10)
                        ->withHeaders(['Token' => config('services.ghtk.token')])
                        ->get('https://services.giaohangtietkiem.vn/services/shipment/fee', [
                            'address' => $validated['delivery_address'],
                            'province' => $validated['customer_province'],
                            'district' => $validated['customer_district'],
                            'ward' => $validated['customer_ward'] ?? '',
                            'weight' => $totalWeight,
                            'value' => $total,
                            'pick_province' => 'Bình Dương',
                            'pick_district' => 'Dĩ An',
                            'pick_ward' => 'Đông Hòa',
                            'pick_street' => 'Ký túc xá Khu B',
                            'pick_tel' => '0946403788',
                        ]);
                    
                    if ($shippingResponse->successful()) {
                        $shippingData = $shippingResponse->json();
                        if (isset($shippingData['success']) && $shippingData['success'] && isset($shippingData['fee']['fee'])) {
                            $shippingFee = (float) $shippingData['fee']['fee'];
                            \Log::info('Recalculated shipping fee from GHTK', [
                                'order_id' => 'pending',
                                'shipping_fee' => $shippingFee
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    \Log::warning('Failed to recalculate shipping fee from GHTK', [
                        'error' => $e->getMessage()
                    ]);
                    // Giữ nguyên shipping_fee = 0 hoặc giá trị từ request
                }
            }
            
            $finalTotal = max(0, $total - $discountAmount);
            // Trừ số lượng quà tặng nếu có
            if (isset($validated['wrapping_paper_id']) && $validated['wrapping_paper_id']) {
                $wrappingPaper = \App\Models\WrappingPaper::lockForUpdate()->find($validated['wrapping_paper_id']);
                if ($wrappingPaper && $wrappingPaper->quantity > 0) {
                    $wrappingPaper->decrement('quantity');
                } else {
                    DB::rollBack();
                    return response()->json(['message' => 'Giấy gói đã hết hàng'], 400);
                }
            }

            if (isset($validated['decorative_accessory_id']) && $validated['decorative_accessory_id']) {
                $accessory = \App\Models\DecorativeAccessory::lockForUpdate()->find($validated['decorative_accessory_id']);
                if ($accessory && $accessory->quantity > 0) {
                    $accessory->decrement('quantity');
                } else {
                    DB::rollBack();
                    return response()->json(['message' => 'Phụ kiện đã hết hàng'], 400);
                }
            }

            if (isset($validated['card_type_id']) && $validated['card_type_id']) {
                $cardType = \App\Models\CardType::lockForUpdate()->find($validated['card_type_id']);
                if ($cardType && $cardType->quantity > 0) {
                    $cardType->decrement('quantity');
                } else {
                    DB::rollBack();
                    return response()->json(['message' => 'Loại thiệp đã hết hàng'], 400);
                }
            }

            // Xử lý print_label: nhận từ request và convert sang boolean
            $printLabel = false;
            if (isset($validated['print_label'])) {
                $printLabel = filter_var($validated['print_label'], FILTER_VALIDATE_BOOLEAN);
            } elseif ($request->has('print_label')) {
                $printLabel = $request->boolean('print_label');
            }

            $order = Order::create([
                'user_id'           => $user->id,
                'delivery_address'  => $validated['delivery_address'],
                'customer_name'     => $validated['customer_name'] ?? null,
                'customer_phone'    => $validated['customer_phone'] ?? null,
                'customer_province' => $validated['customer_province'] ?? null,
                'customer_district' => $validated['customer_district'] ?? null,
                'customer_ward'     => $validated['customer_ward'] ?? null,
                'wrapping_paper_id' => $validated['wrapping_paper_id'] ?? null,
                'wrapping_paper'    => $validated['wrapping_paper'] ?? null,
                'decorative_accessory_id' => $validated['decorative_accessory_id'] ?? null,
                'decorative_accessories' => $validated['decorative_accessories'] ?? null,
                'card_type_id'      => $validated['card_type_id'] ?? null,
                'card_type'         => $validated['card_type'] ?? null,
                'card_note'         => $validated['card_note'] ?? null,
                'total_amount'      => $finalTotal,
                'shipping_fee'       => $shippingFee,
                'loyalty_points_used' => $loyaltyPointsUsed,
                'print_label'       => $printLabel,
                'payment_method'    => $validated['payment_method'],
                'status'            => 'pending', // COD và các phương thức khác đều là pending
                'expires_at'        => $validated['payment_method'] === 'cod' ? null : now()->addMinutes(5), // COD không có thời hạn thanh toán
            ]);

            foreach ($cartItems as $item) {
                OrderItem::create([
                    'order_id'   => $order->id,
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'price'      => $item->product->price ?? 0,
                ]);
            }

            // Xóa giỏ hàng sau khi tạo order thành công
            Cart::where('user_id', $user->id)->delete();

            // Refresh order để load items
            $order->refresh();
            $order->load('items.product');

            // Nếu là COD, tạo đơn GHTK ngay lập tức
            if ($validated['payment_method'] === 'cod') {
                try {
                    $ghtkService = app(\App\Services\GHTKService::class);
                    $ghtkOrder = $ghtkService->createShipment($order);
                    
                    \Log::info("GHTK order created for COD", [
                        'order_id' => $order->id,
                        'ghtk_order_id' => $ghtkOrder->id ?? null,
                        'label_id' => $ghtkOrder->label_id ?? null
                    ]);
                } catch (\Exception $e) {
                    \Log::error("Failed to create GHTK order for COD", [
                        'order_id' => $order->id,
                        'error' => $e->getMessage()
                    ]);
                    // Không throw error, vẫn cho phép tạo order thành công
                    // Admin có thể tạo GHTK order sau
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi khi tạo đơn hàng',
                'error'   => $e->getMessage()
            ], 500);
        }

        // Tính tổng tiền thanh toán: giá trị đơn hàng - điểm thưởng + phí ship
        $totalWithShipping = $finalTotal + $shippingFee;
        
        // Lấy lại user để trả về điểm mới nhất
        $user->refresh();
        
        // Xử lý theo payment method
        $paymentMethod = $validated['payment_method'];
        
        if ($paymentMethod === 'cod') {
            // COD: Không tạo QR code, chỉ thông báo thành công
            $responseData = [
                'message'  => 'Đơn hàng đã được tạo thành công! Bạn sẽ thanh toán khi nhận hàng.',
                'order_id' => $order->id,
                'payment_method' => 'cod',
                'loyalty_points_used' => $loyaltyPointsUsed,
                'discount_amount' => $discountAmount,
                'shipping_fee' => $shippingFee,
                'total_amount' => $finalTotal,
                'total_with_shipping' => $totalWithShipping,
                'remaining_loyalty_points' => $user->loyalty_points ?? 0,
                'qr_code' => null, // Không có QR code cho COD
                'amount' => $totalWithShipping, // Số tiền cần thanh toán khi nhận hàng
            ];
            
            \Log::info("COD checkout completed", [
                'order_id' => $order->id,
                'total_with_shipping' => $totalWithShipping,
                'shipping_fee' => $shippingFee
            ]);
        } else {
            // Bank transfer hoặc Momo: Tạo QR code
            $bankCode = "ACB";
            $accountNo = "22751921";
            $accountName = "NGUYEN DAI TUNG";
            
            $amountInt = intval($totalWithShipping);
            
            // Đảm bảo amount > 0
            if ($amountInt <= 0) {
                \Log::error("Invalid amount for QR code", [
                    'order_id' => $order->id,
                    'final_total' => $finalTotal,
                    'shipping_fee' => $shippingFee,
                    'total_with_shipping' => $totalWithShipping
                ]);
                $amountInt = max(1000, $finalTotal); // Tối thiểu 1000 VND
            }
            
            \Log::info("Checkout amount calculation", [
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'cart_total' => $total,
                'discount_amount' => $discountAmount,
                'final_total' => $finalTotal,
                'shipping_fee' => $shippingFee,
                'shipping_fee_from_request' => $validated['shipping_fee'] ?? 'not_provided',
                'total_with_shipping' => $totalWithShipping,
                'amount_int' => $amountInt,
            ]);
            
            $randomSuffix = strtoupper(Str::random(6));
            $addInfo = "Order{$order->id}{$randomSuffix}";
            $qrUrl = "https://img.vietqr.io/image/{$bankCode}-{$accountNo}-compact2.png"
                . "?amount={$amountInt}&addInfo=" . urlencode($addInfo)
                . "&accountName=" . urlencode($accountName);

            $responseData = [
                'message'  => 'Tạo mã thanh toán thành công',
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'amount'   => $amountInt, // Số tiền trong QR code (đã bao gồm shipping)
                'addInfo'  => $addInfo,
                'qr_code'  => $qrUrl,
                'loyalty_points_used' => $loyaltyPointsUsed,
                'discount_amount' => $discountAmount,
                'shipping_fee' => $shippingFee, // Phí ship từ GHTK
                'total_amount' => $finalTotal, // Tổng tiền sản phẩm (sau giảm điểm)
                'total_with_shipping' => $totalWithShipping, // Tổng tiền cần thanh toán (bao gồm ship)
                'remaining_loyalty_points' => $user->loyalty_points ?? 0,
            ];
            
            \Log::info("Checkout response data", [
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'response_amount' => $responseData['amount'],
                'response_total_with_shipping' => $responseData['total_with_shipping'],
                'response_shipping_fee' => $responseData['shipping_fee']
            ]);
        }
        
        return response()->json($responseData);
    }

    /**
     * ❌ Hủy đơn hàng quá hạn (tự động)
     */
    public function cancelOrder(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $orderId = $request->input('order_id');
        if (!$orderId) return response()->json(['message' => 'Thiếu order_id'], 400);

        $order = Order::where('id', $orderId)
            ->where('user_id', $user->id)
            ->first();

        if (!$order) return response()->json(['message' => 'Không tìm thấy đơn hàng'], 404);

        if ($order->status === 'pending' && now()->greaterThanOrEqualTo($order->expires_at)) {
            DB::beginTransaction();
            try {
                // Đơn hàng pending chưa thanh toán nên không cần cộng lại tồn kho
                $order->update(['status' => 'cancelled']);
                DB::commit();
                return response()->json(['message' => 'Đơn hàng đã bị hủy do hết thời gian thanh toán']);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Lỗi khi hủy đơn hàng',
                    'error' => $e->getMessage()
                ], 500);
            }
        }

        return response()->json(['message' => 'Đơn hàng chưa hết hạn hoặc đã được xử lý']);
    }

    /**
     * 🧹 Xóa toàn bộ giỏ hàng
     */
    public function clearCart(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        Cart::where('user_id', $user->id)->delete();

        return response()->json(['message' => 'Giỏ hàng đã được xóa']);
    }

    /**
     * 🔁 Tự động hủy đơn hàng pending hết hạn (cron job hoặc schedule)
     */
    public static function cancelExpiredOrders()
    {
        $expiredOrders = Order::with('items.product')
            ->where('status', 'pending')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expiredOrders as $order) {
            DB::beginTransaction();
            try {
                // Đơn hàng pending chưa thanh toán nên không cần cộng lại tồn kho
                $order->update(['status' => 'cancelled']);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error('Error cancelling expired order', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    public function updateQuantity(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $validated = $request->validate([
            'cart_id' => 'required|exists:carts,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $cartItem = Cart::where('id', $validated['cart_id'])
            ->where('user_id', $user->id)
            ->first();

        if (!$cartItem) return response()->json(['message' => 'Item not found'], 404);

        $cartItem->update(['quantity' => $validated['quantity']]);

        $cartSnapshot = $this->buildCartSnapshot($user->id);

        return response()->json([
            'message' => 'Cart updated',
            'items' => $cartSnapshot['items'],
            'total_amount' => $cartSnapshot['total_amount']
        ]);
    }
    public function removeItem(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $validated = $request->validate([
            'cart_id' => 'required|exists:carts,id'
        ]);

        Cart::where('id', $validated['cart_id'])
            ->where('user_id', $user->id)
            ->delete();

        $cartSnapshot = $this->buildCartSnapshot($user->id);

        return response()->json([
            'message' => 'Item removed',
            'items' => $cartSnapshot['items'],
            'total_amount' => $cartSnapshot['total_amount']
        ]);
    }

    private function buildCartSnapshot(int $userId): array
    {
        $items = Cart::with('product')->where('user_id', $userId)->get();
        $total = $items->sum(fn($item) => ($item->product->price ?? 0) * $item->quantity);

        return [
            'items' => $items,
            'total_amount' => $total,
        ];
    }
}
