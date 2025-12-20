<?php

namespace App\Jobs;

use App\Mail\AnniversaryReminderMail;
use App\Models\UserAnniversary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class SendAnniversaryReminderJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function handle()
    {
        $today = Carbon::today();
        $currentYear = $today->year;

        $anniversaries = UserAnniversary::with('user')
            ->get()
            ->filter(function ($anniversary) use ($today, $currentYear) {
                // Lấy ngày event trong năm hiện tại
                $eventDate = Carbon::parse($anniversary->event_date);
                $eventThisYear = Carbon::create($currentYear, $eventDate->month, $eventDate->day);
                
                // Nếu ngày đã qua trong năm này, tính cho năm sau
                if ($eventThisYear->lt($today)) {
                    $eventThisYear->addYear();
                }
                
                // Tính số ngày còn lại
                $daysLeft = $today->diffInDays($eventThisYear, false);
                
                // Chỉ xử lý nếu còn 15 hoặc 10 ngày
                return in_array($daysLeft, [15, 10]);
            });

        foreach ($anniversaries as $anniversary) {
            $eventDate = Carbon::parse($anniversary->event_date);
            $eventThisYear = Carbon::create($currentYear, $eventDate->month, $eventDate->day);
            
            if ($eventThisYear->lt($today)) {
                $eventThisYear->addYear();
            }
            
            $daysLeft = $today->diffInDays($eventThisYear, false);
            
            // Kiểm tra xem đã gửi email chưa
            $shouldSend = false;
            $trackingField = null;
            
            if ($daysLeft == 15 && !$anniversary->reminder_15_days_sent_at) {
                $shouldSend = true;
                $trackingField = 'reminder_15_days_sent_at';
            } elseif ($daysLeft == 10 && !$anniversary->reminder_10_days_sent_at) {
                $shouldSend = true;
                $trackingField = 'reminder_10_days_sent_at';
            }
            
            if ($shouldSend && $anniversary->user) {
                try {
                    Mail::to($anniversary->user->email)
                        ->send(new AnniversaryReminderMail($anniversary->user, $anniversary, $daysLeft));
                    
                    // Cập nhật tracking field
                    $anniversary->update([$trackingField => $today]);
                    
                    Log::info("Anniversary reminder email sent for user {$anniversary->user->id}, anniversary {$anniversary->anniversary_id}, {$daysLeft} days left");
                } catch (\Exception $e) {
                    Log::error('Failed to send anniversary reminder email: ' . $e->getMessage(), [
                        'anniversary_id' => $anniversary->anniversary_id,
                        'user_id' => $anniversary->user_id,
                        'days_left' => $daysLeft
                    ]);
                }
            }
        }
    }
}
