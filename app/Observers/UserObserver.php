<?php

namespace App\Observers;

use App\Models\User;
use App\Models\Referral;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    /**
     * Handle the User "created" event.
     * Tự động tạo mã referral cho user mới
     */
    public function created(User $user)
    {
        try {
            // Log để debug
            Log::info('UserObserver: User created', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role
            ]);

            // Chỉ tạo referral cho customer (không tạo cho admin)
            // Nếu role null hoặc empty, mặc định là customer
            $role = $user->role ?? 'customer';
            
            if ($role === 'customer') {
                $referralCode = $this->generateUniqueReferralCode();
                
                Log::info('UserObserver: Creating referral', [
                    'user_id' => $user->id,
                    'referral_code' => $referralCode
                ]);
                
                Referral::create([
                    'referrer_id' => $user->id,
                    'referral_code' => $referralCode,
                    'status' => 'pending',
                ]);
                
                Log::info('UserObserver: Referral created successfully', [
                    'user_id' => $user->id,
                    'referral_code' => $referralCode
                ]);
            } else {
                Log::info('UserObserver: Skipping referral creation (not customer)', [
                    'user_id' => $user->id,
                    'role' => $role
                ]);
            }
        } catch (\Exception $e) {
            Log::error('UserObserver: Error creating referral', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Generate unique referral code
     */
    private function generateUniqueReferralCode(): string
    {
        do {
            // Tạo mã referral: 6-8 ký tự chữ hoa và số
            $code = strtoupper(Str::random(8));
            
            // Kiểm tra xem mã đã tồn tại chưa
            $exists = Referral::where('referral_code', $code)->exists();
        } while ($exists);

        return $code;
    }
}

