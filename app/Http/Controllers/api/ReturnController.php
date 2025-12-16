<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ReturnRequest;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use App\Models\Refund;
use Illuminate\Support\Facades\Log;

class ReturnController extends Controller
{
    /**
     * Lấy danh sách return requests của user hiện tại
     */
    public function index(Request $request)
    {
        $returns = ReturnRequest::with(['order', 'refund'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($returns);
    }

    /**
     * Lấy danh sách tất cả return requests (Admin)
     */
    public function adminIndex(Request $request)
    {
        $status = $request->query('status');
        $type = $request->query('type');

        $query = ReturnRequest::with(['order', 'refund', 'order.user']);

        if ($status) {
            $query->where('status', $status);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $returns = $query->orderBy('created_at', 'desc')->get();

        return response()->json($returns);
    }

    /**
     * Xem chi tiết return request
     */
    public function show($id)
    {
        $return = ReturnRequest::with(['order.items.product', 'refund'])
            ->findOrFail($id);

        return response()->json($return);
    }

    public function store(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer',
            'type'     => 'required|in:refund,exchange',
            'reason'   => 'required|string'
        ]);

        $order = Order::with('ghtkOrder')
            ->where('id', $request->order_id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'completed')
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Đơn hàng không hợp lệ hoặc chưa hoàn thành'], 400);
        }

        // Kiểm tra GHTK status: chỉ cho phép return khi đã delivered hoặc returned
        if ($order->ghtkOrder) {
            $ghtkStatus = $order->ghtkOrder->status;
            $allowedStatuses = ['delivered', 'returned'];
            
            if (!in_array($ghtkStatus, $allowedStatuses)) {
                $statusLabels = [
                    'created' => 'Đã tạo đơn',
                    'picking' => 'Đang lấy hàng',
                    'delivering' => 'Đang giao hàng',
                    'delivered' => 'Đã giao hàng',
                    'returned' => 'Đã hoàn trả',
                    'cancelled' => 'Đã hủy',
                    'lost' => 'Thất lạc'
                ];
                
                $currentLabel = $statusLabels[$ghtkStatus] ?? $ghtkStatus;
                return response()->json([
                    'message' => "Không thể yêu cầu đổi/trả hàng. Trạng thái vận chuyển hiện tại: {$currentLabel}. Chỉ có thể yêu cầu khi đơn hàng đã được giao hoặc đã hoàn trả."
                ], 400);
            }
        }

        $exists = ReturnRequest::where('order_id', $order->id)->first();
        if ($exists) {
            return response()->json(['message' => 'Đơn hàng đã có yêu cầu xử lý'], 400);
        }

        $return = ReturnRequest::create([
            'order_id' => $order->id,
            'user_id'  => $request->user()->id,
            'type'     => $request->type,
            'reason'   => $request->reason,
            'note'     => $request->note,
        ]);

        return response()->json([
            'message' => 'Đã gửi yêu cầu thành công',
            'data' => $return
        ]);
    }
    public function approve($id)
    {
        $return = ReturnRequest::findOrFail($id);

        if ($return->status !== 'requested') {
            return response()->json(['message' => 'Trạng thái không hợp lệ'], 400);
        }

        $return->update(['status' => 'approved']);

        return response()->json(['message' => 'Đã duyệt yêu cầu']);
    }

    public function reject(Request $request, $id)
    {
        $return = ReturnRequest::findOrFail($id);

        $return->update([
            'status' => 'rejected',
            'note' => $request->note
        ]);

        return response()->json(['message' => 'Đã từ chối yêu cầu']);
    }
    public function received($id)
    {
        $return = ReturnRequest::with('order.items.product')->findOrFail($id);

        if ($return->status !== 'approved') {
            return response()->json(['message' => 'Chưa được duyệt'], 400);
        }

        DB::beginTransaction();
        try {
            foreach ($return->order->items as $item) {
                $product = Product::lockForUpdate()->find($item->product_id);
                $product->increment('stock_quantity', $item->quantity);
            }

            $return->update(['status' => 'received']);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json(['message' => 'Đã nhận hàng hoàn']);
    }
    public function refund(Request $request, $id)
    {
        $request->validate([
            'method' => 'nullable|in:bank,wallet,original',
            'note' => 'nullable|string|max:500',
        ]);

        $return = ReturnRequest::with(['order', 'refund', 'order.user'])->findOrFail($id);

        if ($return->type !== 'refund') {
            return response()->json([
                'success' => false,
                'message' => 'Không phải yêu cầu hoàn tiền'
            ], 400);
        }

        if ($return->status !== 'received') {
            return response()->json([
                'success' => false,
                'message' => 'Chưa nhận hàng hoàn. Vui lòng xác nhận đã nhận hàng trước.'
            ], 400);
        }

        if ($return->refund) {
            return response()->json([
                'success' => false,
                'message' => 'Đã hoàn tiền trước đó'
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Tính toán số tiền hoàn (có thể trừ phí ship nếu cần)
            $refundAmount = $return->order->total_amount;
            
            // Tạo refund record
            $refund = Refund::create([
                'return_request_id' => $return->id,
                'amount' => $refundAmount,
                'status' => 'completed',
                'method' => $request->method ?? 'bank',
                'note' => $request->note ?? 'Hoàn tiền tự động sau khi nhận hàng hoàn'
            ]);

            // Cập nhật trạng thái return request
            $return->update(['status' => 'completed']);

            // TODO: Nếu cần, có thể cập nhật user balance hoặc tạo payment record
            // Ví dụ: $return->order->user->increment('balance', $refundAmount);

            DB::commit();

            Log::info('Refund processed', [
                'return_request_id' => $return->id,
                'order_id' => $return->order_id,
                'amount' => $refundAmount,
                'method' => $refund->method
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Hoàn tiền thành công',
                'data' => [
                    'refund' => $refund,
                    'return_request' => $return->fresh(['order', 'refund'])
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Refund processing failed', [
                'return_request_id' => $return->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xử lý hoàn tiền: ' . $e->getMessage()
            ], 500);
        }
    }
}
