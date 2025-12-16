<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ReferralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerifyEmail;

class RegisterController extends Controller
{
    protected $referralService;

    public function __construct(ReferralService $referralService)
    {
        $this->referralService = $referralService;
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'referral_code' => 'nullable|string|max:50',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

        // Xử lý referral code nếu có
        if ($request->filled('referral_code')) {
            $result = $this->referralService->useReferralCode(
                $request->referral_code,
                $user->id
            );

            if ($result['success']) {
                // Tạo thưởng cho người giới thiệu
                $this->referralService->rewardReferrer($result['referrer_id']);
            }
            // Không trả lỗi nếu referral code không hợp lệ, chỉ log
        }

        // Gửi email xác nhận
        $token = Hash::make($user->email);
        Mail::to($user->email)->send(new VerifyEmail($user->id, $token));

        return response()->json([
            'message' => 'Đăng ký thành công. Kiểm tra email để xác nhận!',
            'referral_applied' => $request->filled('referral_code') ? true : false
        ], 201);
    }
}