<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromotionUsage;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PromotionUsageExport;

class PromotionUsageController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = PromotionUsage::with(['promotion', 'user', 'order']);

            if ($search = $request->input('search')) {
                $query->whereHas('promotion', fn($q) => $q->where('code', 'like', "%$search%"));
            }

            $usages = $query->orderBy('created_at', 'desc')->paginate(10);
            return response()->json($usages);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        return response()->json(PromotionUsage::with(['promotion', 'user', 'order'])->findOrFail($id));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'promotion_id' => 'required|integer|exists:promotions,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'order_id' => 'nullable|integer|exists:orders,id',
        ]);

        // Convert empty strings to null
        if (isset($data['user_id']) && $data['user_id'] === '') {
            $data['user_id'] = null;
        }
        if (isset($data['order_id']) && $data['order_id'] === '') {
            $data['order_id'] = null;
        }

        $usage = PromotionUsage::create($data);
        return response()->json($usage, 201);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'promotion_id' => 'required|integer|exists:promotions,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'order_id' => 'nullable|integer|exists:orders,id',
        ]);

        // Convert empty strings to null
        if (isset($data['user_id']) && $data['user_id'] === '') {
            $data['user_id'] = null;
        }
        if (isset($data['order_id']) && $data['order_id'] === '') {
            $data['order_id'] = null;
        }

        $usage = PromotionUsage::findOrFail($id);
        $usage->update($data);
        return response()->json($usage);
    }

    public function destroy($id)
    {
        PromotionUsage::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function export()
    {
        return Excel::download(new PromotionUsageExport, 'promotion_usage.xlsx');
    }

    public function dashboard()
    {
        try {
            $total = PromotionUsage::count();
            $uniquePromotions = PromotionUsage::distinct()->count('promotion_id');
            $uniqueUsers = PromotionUsage::whereNotNull('user_id')->distinct()->count('user_id');

            return response()->json([
                'total' => $total,
                'uniquePromotions' => $uniquePromotions,
                'uniqueUsers' => $uniqueUsers,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
