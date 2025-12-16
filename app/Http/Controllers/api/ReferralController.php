<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReferralExport;

class ReferralController extends Controller
{
    protected $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }
    public function index(Request $request)
    {
        $query = Referral::with(['referrer', 'referred']);

        if ($search = $request->input('search')) {
            $query->whereHas('referrer', fn($q) => $q->where('name', 'like', "%$search%"))
                  ->orWhereHas('referred', fn($q) => $q->where('name', 'like', "%$search%"));
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $referrals = $query->orderBy('created_at', 'desc')->paginate(10);
        return response()->json($referrals);
    }

    public function show($id)
    {
        $referral = Referral::with(['referrer', 'referred'])->findOrFail($id);
        return response()->json($referral);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'referrer_id' => 'required|exists:users,id',
            'referral_code' => 'required|unique:referrals',
            'status' => 'in:pending,completed,expired',
        ]);

        $referral = Referral::create($data);
        return response()->json($referral, 201);
    }

    public function update(Request $request, $id)
    {
        $referral = Referral::findOrFail($id);
        $referral->update($request->only('status'));
        return response()->json($referral);
    }

    public function destroy($id)
    {
        Referral::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function export()
    {
        return Excel::download(new ReferralExport, 'referrals.xlsx');
    }

    public function dashboard()
    {
        $total = Referral::count();
        $completed = Referral::where('status', 'completed')->count();
        $pending = Referral::where('status', 'pending')->count();
        $expired = Referral::where('status', 'expired')->count();

        return response()->json([
            'total' => $total,
            'completed' => $completed,
            'pending' => $pending,
            'expired' => $expired,
        ]);
    }

    /**
     * Validate referral code (public endpoint)
     */
    public function validateCode(Request $request)
    {
        $request->validate([
            'referral_code' => 'required|string|max:50',
        ]);

        $result = $this->referralService->validateReferralCode($request->referral_code);
        
        return response()->json($result, $result['valid'] ? 200 : 400);
    }

    /**
     * Use referral code (public endpoint - cho user đã đăng nhập)
     */
    public function use(Request $request)
    {
        $request->validate([
            'referral_code' => 'required|string|max:50',
        ]);

        $user = auth()->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn cần đăng nhập để sử dụng mã giới thiệu'
            ], 401);
        }

        $result = $this->referralService->useReferralCode(
            $request->referral_code,
            $user->id
        );

        if ($result['success']) {
            // Tạo thưởng cho người giới thiệu
            $this->referralService->rewardReferrer($result['referrer_id']);
        }

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
