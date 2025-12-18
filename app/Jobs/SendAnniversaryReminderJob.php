<?php

namespace App\Jobs;

use App\Mail\AnniversaryReminderMail;
use App\Models\UserAnniversary;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Carbon;

class SendAnniversaryReminderJob implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function handle()
    {
        $today = Carbon::today();

        // Lấy tất cả anniversaries và filter những cái sắp tới trong 15 hoặc 10 ngày
        $anniversaries = UserAnniversary::with('user')
            ->get()
            ->filter(function ($anniversary) use ($today) {
                $eventDate = Carbon::parse($anniversary->event_date);
                $daysUntil = $today->diffInDays($eventDate, false); // false = không tính giá trị tuyệt đối
                
                // Chỉ lấy những anniversary sắp tới (daysUntil > 0) và đúng 15 hoặc 10 ngày
                if ($daysUntil <= 0 || !in_array($daysUntil, [15, 10])) {
                    return false;
                }
                
                // Kiểm tra xem đã gửi reminder cho milestone này chưa
                if ($daysUntil == 15) {
                    // Nếu đã gửi reminder 15 ngày rồi thì không gửi lại
                    return !$anniversary->reminder_15_days_sent_at;
                } elseif ($daysUntil == 10) {
                    // Nếu đã gửi reminder 10 ngày rồi thì không gửi lại
                    return !$anniversary->reminder_10_days_sent_at;
                }
                
                return false;
            });

        foreach ($anniversaries as $anniversary) {
            $eventDate = Carbon::parse($anniversary->event_date);
            $daysLeft = $today->diffInDays($eventDate, false);
            
            // Chỉ gửi email nếu user có email
            if ($anniversary->user && $anniversary->user->email) {
                try {
                    Mail::to($anniversary->user->email)
                        ->send(new AnniversaryReminderMail($anniversary->user, $anniversary, $daysLeft));
                    
                    // Đánh dấu đã gửi reminder
                    if ($daysLeft == 15) {
                        $anniversary->reminder_15_days_sent_at = $today;
                    } elseif ($daysLeft == 10) {
                        $anniversary->reminder_10_days_sent_at = $today;
                    }
                    $anniversary->save();
                    
                    \Log::info('Anniversary reminder sent', [
                        'user_id' => $anniversary->user->id,
                        'anniversary_id' => $anniversary->anniversary_id,
                        'event_name' => $anniversary->event_name,
                        'event_date' => $anniversary->event_date,
                        'days_left' => $daysLeft
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Failed to send anniversary reminder', [
                        'user_id' => $anniversary->user->id,
                        'anniversary_id' => $anniversary->anniversary_id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
        }
    }
}
