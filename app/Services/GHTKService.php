<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use App\Models\GhtkOrder;
use App\Models\Product;

class GHTKService
{
    protected $apiUrl;
    protected $token;

    public function __construct()
    {
        $this->apiUrl = config('services.ghtk.api_url');  // ex: https://services.giaohangtietkiem.vn/services/shipment/order/?ver=1.5
        $this->token  = config('services.ghtk.token');    // Token từ GHTK Dashboard
    }

    public function createShipment($order)
    {
        // ============================================
        // 1. Tính trọng lượng
        // ============================================
        $totalWeightGrams = $order->items->sum(function ($item) {
            return ($item->product->weight_in_gram ?? 200) * $item->quantity;
        });

        if ($totalWeightGrams <= 0) $totalWeightGrams = 300;

        // ============================================
        // 2. Danh sách sản phẩm
        // ============================================
        $payload = [
            "products" => $order->items->map(function ($item) {
                return [
                    "name"     => $item->product->name,
                    "weight"   => (int) ($item->product->weight_in_gram ?? 200),
                    "quantity" => (int) $item->quantity,
                    "price"    => (int) $item->price,
                ];
            })->toArray(),
        ];

        // ============================================
        // 3. Chuẩn hóa địa chỉ GHTK
        // ============================================
        $province = $order->customer_province;
        $district = $order->customer_district;
        $ward     = $order->customer_ward;

        // Auto FIX lỗi đảo tỉnh ↔ huyện
        $this->fixAddress($province, $district, $ward);

        // ============================================
        // 4. Tính pick_money dựa trên payment_method
        // ============================================
        // Nếu COD (thanh toán khi nhận hàng) → pick_money = tổng tiền
        // Nếu bank_transfer hoặc momo (đã thanh toán) → pick_money = 0
        $pickMoney = 0;
        $totalOrderAmount = $order->total_amount + ($order->shipping_fee ?? 0);
        
        if ($order->payment_method === 'cod') {
            // COD: GHTK sẽ thu tiền khi giao hàng
            $pickMoney = (int) $totalOrderAmount;
        } else {
            // bank_transfer, momo: Đã thanh toán trước → GHTK không thu tiền
            $pickMoney = 0;
        }

        \Log::info("GHTK pick_money calculation", [
            'order_id' => $order->id,
            'payment_method' => $order->payment_method,
            'total_amount' => $order->total_amount,
            'shipping_fee' => $order->shipping_fee ?? 0,
            'total_order_amount' => $totalOrderAmount,
            'pick_money' => $pickMoney
        ]);

        // ============================================
        // 5. Tạo ORDER payload
        // ============================================
        $orderPayload = [
            "id"            => "ORDER_" . $order->id,
            "weight"        => (int) $totalWeightGrams,
            "total_weight"  => (int) $totalWeightGrams,
            "weight_option" => "gram",
            "transport"      => "road",
            "deliver_option" => "none",
            "pick_money"     => $pickMoney,
            "value"          => (int) $order->total_amount,
            "tel"            => $order->customer_phone ?? "0905123456",
            "name"           => $order->customer_name ?? "Khách hàng",
            "address"        => $order->delivery_address,
            "province"       => $province,
            "district"       => $district,
            "ward"           => $ward,
            "hamlet"         => "Khác",
            "is_freeship"    => 1,
        ];

        // ============================================
        // 6. Pick address
        // ============================================
        if ($order->pick_address_id) {
            $orderPayload["pick_address_id"] = $order->pick_address_id;
        } else {
            $orderPayload["pick_name"]     = "Kho sách BookGift";
            $orderPayload["pick_address"]  = "1312, Phường 1, Bình Thạnh, TP.HCM";
            $orderPayload["pick_province"] = "TP Hồ Chí Minh";
            $orderPayload["pick_district"] = "Bình Thạnh";
            $orderPayload["pick_ward"]     = "Phường 1";
            $orderPayload["pick_tel"]      = "0946403788";
        }

        $payload["order"] = $orderPayload;

        // ============================================
        // 7. Log payload
        // ============================================
        \Log::info("GHTK PAYLOAD FINAL => " . json_encode($payload, JSON_UNESCAPED_UNICODE));

        // ============================================
        // 8. Gửi API
        // ============================================
        $response = Http::withHeaders([
            "Token" => $this->token,
        ])->post($this->apiUrl, $payload);

        $data = $response->json();

        \Log::info("GHTK RESPONSE => " . json_encode($data, JSON_UNESCAPED_UNICODE));

        // ============================================
        // 9. Lưu vào database
        // ============================================
        if ($response->successful() && ($data["success"] ?? false)) {
            // Lấy label_id từ response - có thể ở nhiều vị trí khác nhau
            $labelId = $data["order"]["label"] 
                    ?? $data["order"]["label_id"] 
                    ?? $data["label"] 
                    ?? null;
            
            $orderCode = $data["order"]["order_code"] ?? null;
            $fee = $data["order"]["fee"] ?? null;
            $trackingUrl = $data["order"]["url"] ?? null;
            
            \Log::info("GHTK Order created", [
                'order_id' => $order->id,
                'label_id' => $labelId,
                'order_code' => $orderCode,
                'response_structure' => array_keys($data["order"] ?? [])
            ]);
            
            $ghtkOrder = GhtkOrder::create([
                "order_id"     => $order->id,
                "order_code"   => $orderCode,
                "label_id"     => $labelId,
                "fee"          => $fee,
                "tracking_url" => $trackingUrl,
                "response"     => json_encode($data),
                "status"       => "created",
            ]);
            
            // Nếu có label_id, cập nhật ngay vào order
            if ($labelId) {
                $order->update(['tracking_code' => $labelId]);
                \Log::info("Updated tracking_code immediately after GHTK creation", [
                    'order_id' => $order->id,
                    'tracking_code' => $labelId
                ]);
            }
            
            return $ghtkOrder;
        }

        // ============================================
        // 10. Throw lỗi rõ ràng
        // ============================================
        throw new \Exception(
            "GHTK API error: " . json_encode($data, JSON_UNESCAPED_UNICODE)
        );
    }
    private function fixAddress(&$province, &$district, &$ward)
    {
        // Mapping BASIC để auto sửa
        $provinces = ["Bình Dương", "Hồ Chí Minh", "TP Hồ Chí Minh", "Hà Nội"];
        $districts = ["Dĩ An", "Thủ Đức", "Bình Thạnh", "Quận 1", "Quận 3"];
        $wards     = ["Đông Hoà", "Phường 1", "Linh Trung", "Hiệp Bình Chánh"];

        //-- Nếu province nằm trong danh sách district => swap
        if (in_array($province, $districts)) {
            $tmp = $province;
            $province = $district;
            $district = $tmp;
        }

        //-- Nếu ward trống => cố gắng đoán
        if (!$ward && $district === "Dĩ An") {
            $ward = "Đông Hoà";
        }

        //-- Fix đặc thù KTX khu B
        if (str_contains($district, "Dĩ An") && !$ward) {
            $ward = "Đông Hoà";
        }

        //-- Nếu vẫn trống => đảm bảo không crash API
        if (!$province) $province = "Bình Dương";
        if (!$district) $district = "Dĩ An";
        if (!$ward)     $ward     = "Đông Hoà";
    }

    public function getOrderStatus($trackingCode)
    {
        $url = "https://services.giaohangtietkiem.vn/services/shipment/v2/" . $trackingCode;

        $response = Http::withHeaders([
            "Token" => $this->token,
            "X-Client-Source" => config("services.ghtk.client_source"),
        ])->get($url);

        return $response->json();
    }
    public function getLabelPdf(
        string $trackingCode,
        string $pageSize = 'A6',
        string $original = 'portrait'
    ) {
        // URL đúng theo API GHTK: GET /services/label/{TRACKING_ORDER}
        $labelUrl = "https://services.giaohangtietkiem.vn/services/label/{$trackingCode}";
        
        $response = Http::withHeaders([
            'Token' => config('services.ghtk.token'),
            'X-Client-Source' => config('services.ghtk.client_source', ''),
        ])->get(
            $labelUrl,
            [
                'page_size' => $pageSize,
                'original' => $original,
            ]
        );

        // ❌ Lỗi GHTK trả JSON
        if (!$response->successful()) {
            $errorData = $response->json();
            return response()->json([
                'success' => false,
                'message' => $errorData['message'] ?? 'Không thể in nhãn'
            ], 400);
        }

        // Kiểm tra nếu response là JSON (lỗi từ GHTK)
        $contentType = $response->header('Content-Type', '');
        if (str_contains($contentType, 'application/json')) {
            $errorData = $response->json();
            return response()->json([
                'success' => false,
                'message' => $errorData['message'] ?? 'Không thể in nhãn'
            ], 400);
        }

        // ✅ Trả file PDF
        return response($response->body(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="ghtk-label-' . $trackingCode . '.pdf"',
            'Content-Transfer-Encoding' => 'binary',
        ]);
    }
    public function syncOrderStatus(GhtkOrder $ghtkOrder)
    {
        if (!$ghtkOrder->label_id) {
            return "no_label";
        }

        $data = $this->getOrderStatus($ghtkOrder->label_id);

        if (!($data["success"] ?? false)) {
            \Log::warning("GHTK sync failed", $data);
            return "failed";
        }

        $orderInfo = $data["order"];

        $map = [
            "1"  => "created",
            "2"  => "picking",
            "3"  => "delivering",
            "4"  => "delivered",
            "5"  => "returned",
            "-1" => "cancelled",
            "-2" => "lost",
        ];

        $newStatus = $map[$orderInfo["status"]] ?? "unknown";

        // 👉 Cập nhật ghtk_orders
        $ghtkOrder->update([
            "status"   => $newStatus,
            "response" => json_encode($data),
        ]);

        // 👉 Cập nhật bảng orders
        $order = $ghtkOrder->order()->with('items.product')->first();

        if (!$order) return $newStatus;

        // Sync tracking_code từ label_id nếu chưa có
        if (!$order->tracking_code && $ghtkOrder->label_id) {
            $order->update(['tracking_code' => $ghtkOrder->label_id]);
            \Log::info("✅ Synced tracking_code in syncOrderStatus for order {$order->id}: {$ghtkOrder->label_id}");
        }

        if ($newStatus === "delivered") {
            $order->update([
                "status" => "completed"
            ]);
        }

        if (in_array($newStatus, ["cancelled", "returned"])) {

            DB::beginTransaction();

            try {
                // ⚡ Nếu đơn đã thu tiền hoặc đã xử lý → cộng lại kho
                $shouldRestoreStock = in_array($order->status, ['paid', 'processing', 'shipping']);

                if ($shouldRestoreStock) {
                    foreach ($order->items as $item) {
                        $product = Product::lockForUpdate()->find($item->product_id);
                        if ($product) {
                            $product->increment('stock_quantity', $item->quantity);
                        }
                    }
                }

                $order->update(["status" => "cancelled"]);

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                \Log::error("GHTK restore stock FAILED", [
                    "order_id" => $order->id,
                    "error" => $e->getMessage()
                ]);
            }
        }

        return $newStatus;
    }
}
