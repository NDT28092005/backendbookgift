<?php

namespace App\Models;

use App\Mail\AnniversaryReminderMail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserAnniversary extends Model
{
    use HasFactory;

    protected $primaryKey = 'anniversary_id'; // vì id trong migration là anniversary_id
    protected $fillable = [
        'user_id',
        'event_name',
        'event_date',
        'reminder_15_days_sent_at',
        'reminder_10_days_sent_at',
    ];

    // Relationship: một anniversary thuộc về 1 user
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Kiểm tra và gửi email nhắc nhở nếu anniversary trong vòng 15-10 ngày tới
     */
    public function checkAndSendReminderEmail()
    {
        $today = Carbon::today();
        $currentYear = $today->year;
        
        // Lấy ngày event trong năm hiện tại
        $eventDate = Carbon::parse($this->event_date);
        $eventThisYear = Carbon::create($currentYear, $eventDate->month, $eventDate->day);
        
        // Nếu ngày đã qua trong năm này, tính cho năm sau
        if ($eventThisYear->lt($today)) {
            $eventThisYear->addYear();
        }
        
        // Tính số ngày còn lại
        $daysLeft = $today->diffInDays($eventThisYear, false);
        
        // Chỉ gửi email nếu còn 15 hoặc 10 ngày
        if (in_array($daysLeft, [15, 10])) {
            // Kiểm tra xem đã gửi email chưa
            $shouldSend = false;
            $trackingField = null;
            
            if ($daysLeft == 15 && !$this->reminder_15_days_sent_at) {
                $shouldSend = true;
                $trackingField = 'reminder_15_days_sent_at';
            } elseif ($daysLeft == 10 && !$this->reminder_10_days_sent_at) {
                $shouldSend = true;
                $trackingField = 'reminder_10_days_sent_at';
            }
            
            if ($shouldSend && $this->user) {
                try {
                    Mail::to($this->user->email)
                        ->send(new AnniversaryReminderMail($this->user, $this, $daysLeft));
                    
                    // Cập nhật tracking field
                    $this->update([$trackingField => $today]);
                    
                    Log::info("Anniversary reminder email sent for user {$this->user->id}, anniversary {$this->anniversary_id}, {$daysLeft} days left");
                } catch (\Exception $e) {
                    Log::error('Failed to send anniversary reminder email: ' . $e->getMessage(), [
                        'anniversary_id' => $this->anniversary_id,
                        'user_id' => $this->user_id,
                        'days_left' => $daysLeft
                    ]);
                }
            }
        }
    }
}