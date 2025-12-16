<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\User;
use App\Models\Promotion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReferralService
{
    /**
     * Xử lý khi ai đó sử dụng referral code
     * 
     * @param string $referralCode Mã referral code được sử dụng
     * @param int $referredUserId ID của user đang sử dụng code (người được giới thiệu)
     * @return array ['success' => bool, 'message' => string, 'referrer_id' => int|null]
     */
    public function useReferralCode(string $referralCode, int $referredUserId): array
    {
        try {
            DB::beginTransaction();

            // Tìm referral code
            $referral = Referral::where('referral_code', strtoupper($referralCode))
                ->where('status', 'pending')
                ->first();

            if (!$referral) {
                return [
                    'success' => false,
                    'message' => 'Mã giới thiệu không hợp lệ hoặc đã được sử dụng',
                    'referrer_id' => null
                ];
            }

            // Kiểm tra user không được sử dụng code của chính mình
            if ($referral->referrer_id == $referredUserId) {
                return [
                    'success' => false,
                    'message' => 'Bạn không thể sử dụng mã giới thiệu của chính mình',
                    'referrer_id' => null
                ];
            }

            // Kiểm tra user đã sử dụng code này chưa
            $alreadyUsed = Referral::where('referred_id', $referredUserId)
                ->where('referral_code', $referralCode)
                ->exists();

            if ($alreadyUsed) {
                return [
                    'success' => false,
                    'message' => 'Bạn đã sử dụng mã giới thiệu này rồi',
                    'referrer_id' => null
                ];
            }

            // Update referral: set referred_id và status = completed
            $referral->update([
                'referred_id' => $referredUserId,
                'status' => 'completed'
            ]);

            // Lấy thông tin người giới thiệu (referrer)
            $referrer = User::find($referral->referrer_id);

            Log::info('Referral code used successfully', [
                'referral_code' => $referralCode,
                'referrer_id' => $referral->referrer_id,
                'referred_id' => $referredUserId
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Mã giới thiệu đã được áp dụng thành công!',
                'referrer_id' => $referral->referrer_id,
                'referrer' => $referrer
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error using referral code', [
                'referral_code' => $referralCode,
                'referred_user_id' => $referredUserId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Có lỗi xảy ra khi xử lý mã giới thiệu',
                'referrer_id' => null
            ];
        }
    }

    /**
     * Validate referral code (kiểm tra code có hợp lệ không)
     * 
     * @param string $referralCode
     * @return array ['valid' => bool, 'message' => string]
     */
    public function validateReferralCode(string $referralCode): array
    {
        $referral = Referral::where('referral_code', strtoupper($referralCode))
            ->where('status', 'pending')
            ->first();

        if (!$referral) {
            return [
                'valid' => false,
                'message' => 'Mã giới thiệu không hợp lệ hoặc đã được sử dụng'
            ];
        }

        return [
            'valid' => true,
            'message' => 'Mã giới thiệu hợp lệ',
            'referrer' => $referral->referrer
        ];
    }

    /**
     * Tạo promotion/credit cho referrer khi referral được sử dụng
     * (Có thể mở rộng sau để tích hợp với hệ thống promotion)
     * 
     * @param int $referrerId
     * @return bool
     */
    public function rewardReferrer(int $referrerId): bool
    {
        try {
            // TODO: Tạo promotion hoặc credit cho referrer
            // Ví dụ: Tạo promotion code cho referrer, hoặc tăng credit/points
            
            Log::info('Rewarding referrer', ['referrer_id' => $referrerId]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error rewarding referrer', [
                'referrer_id' => $referrerId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}

