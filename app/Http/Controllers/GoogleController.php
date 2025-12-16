<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Models\User;

class GoogleController extends Controller
{
    /**
     * Xử lý callback khi đăng nhập Google thành công
     */
    public function handleGoogleCallback(Request $request)
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['error' => 'Missing token'], 400);
        }

        try {
            // Gọi Google API để xác thực token
            $googleUser = Http::get("https://www.googleapis.com/oauth2/v3/tokeninfo?id_token={$token}");

            if ($googleUser->failed()) {
                Log::error('Google token invalid', ['response' => $googleUser->json()]);
                return response()->json(['error' => 'Invalid Google token'], 401);
            }

            $data = $googleUser->json();
            $email = $data['email'] ?? null;
            $googleId = $data['sub'] ?? null;

            if (!$email || !$googleId) {
                return response()->json(['error' => 'Email or Google ID missing'], 400);
            }

            // Tìm user theo email trước
            $user = User::where('email', $email)->first();

            if ($user) {
                // Nếu đã có user nhưng chưa có google_id → cập nhật
                if (!$user->google_id) {
                    $user->google_id = $googleId;
                    $user->save();
                }
            } else {
                // Nếu chưa tồn tại → tạo mới
                $user = User::create([
                    'name' => $data['name'] ?? 'Google User',
                    'email' => $email,
                    'google_id' => $googleId,
                    'password' => bcrypt(Str::random(16)), // random password
                    'email_verified_at' => now(), // Google đã xác minh email
                    'role' => 'customer', // Đảm bảo set role là customer
                ]);
                
                Log::info('GoogleController: New user created', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $user->role
                ]);

                // Xử lý referral code nếu có
                if ($request->filled('referral_code')) {
                    $referralService = app(\App\Services\ReferralService::class);
                    $result = $referralService->useReferralCode(
                        $request->referral_code,
                        $user->id
                    );

                    if ($result['success']) {
                        // Tạo thưởng cho người giới thiệu
                        $referralService->rewardReferrer($result['referrer_id']);
                    }
                }
            }

            // ✅ Tạo access token (Laravel Sanctum)
            $accessToken = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Google login successful',
                'access_token' => $accessToken,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $data['picture'] ?? null,
                ]
            ], 200);

        } catch (\Throwable $th) {
            Log::error('Google login error', ['error' => $th->getMessage()]);
            return response()->json([
                'error' => 'Server error during Google login',
                'details' => $th->getMessage()
            ], 500);
        }
    }
}