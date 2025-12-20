<?php

namespace App\Console\Commands;

use App\Models\UserAnniversary;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class DebugAnniversary extends Command
{
    protected $signature = 'anniversary:debug {anniversary_id}';
    protected $description = 'Debug một anniversary cụ thể';

    public function handle()
    {
        $id = $this->argument('anniversary_id');
        
        $anniversary = UserAnniversary::with('user')->find($id);
        
        if (!$anniversary) {
            $this->error("Không tìm thấy anniversary với ID: {$id}");
            return;
        }

        $today = Carbon::today();
        $currentYear = $today->year;
        $eventDate = Carbon::parse($anniversary->event_date);
        $eventThisYear = Carbon::create($currentYear, $eventDate->month, $eventDate->day);
        
        if ($eventThisYear->lt($today)) {
            $eventThisYear->addYear();
        }
        
        $daysLeft = $today->diffInDays($eventThisYear, false);

        $this->info('=== DEBUG ANNIVERSARY ===');
        $this->table(
            ['Field', 'Value'],
            [
                ['Anniversary ID', $anniversary->anniversary_id],
                ['Event Name', $anniversary->event_name],
                ['Event Date (DB)', $anniversary->event_date],
                ['Event Date (Parsed)', $eventDate->format('Y-m-d H:i:s')],
                ['Today', $today->format('Y-m-d H:i:s')],
                ['Event This Year', $eventThisYear->format('Y-m-d')],
                ['Days Left', $daysLeft],
                ['Is 15 days?', $daysLeft == 15 ? 'YES' : 'NO'],
                ['Is 10 days?', $daysLeft == 10 ? 'YES' : 'NO'],
                ['15 days sent', $anniversary->reminder_15_days_sent_at ?? 'NULL'],
                ['10 days sent', $anniversary->reminder_10_days_sent_at ?? 'NULL'],
                ['User ID', $anniversary->user_id],
                ['User Email', $anniversary->user->email ?? 'NULL'],
            ]
        );

        $this->newLine();
        $this->info('=== LOGIC CHECK ===');
        
        $shouldSend = false;
        $reason = '';
        
        if (!in_array($daysLeft, [15, 10])) {
            $reason = "Không trong vòng 15-10 ngày (hiện tại: {$daysLeft} ngày)";
        } elseif ($daysLeft == 15 && $anniversary->reminder_15_days_sent_at) {
            $reason = "Đã gửi email 15 ngày rồi (sent at: {$anniversary->reminder_15_days_sent_at})";
        } elseif ($daysLeft == 10 && $anniversary->reminder_10_days_sent_at) {
            $reason = "Đã gửi email 10 ngày rồi (sent at: {$anniversary->reminder_10_days_sent_at})";
        } elseif (!$anniversary->user) {
            $reason = "User không tồn tại";
        } else {
            $shouldSend = true;
            $reason = "✓ SẼ GỬI EMAIL";
        }
        
        $this->info("Kết quả: {$reason}");
        
        if ($shouldSend) {
            $this->newLine();
            if ($this->confirm('Bạn có muốn test gửi email ngay bây giờ?', false)) {
                try {
                    \App\Mail\AnniversaryReminderMail::class;
                    \Illuminate\Support\Facades\Mail::to($anniversary->user->email)
                        ->send(new \App\Mail\AnniversaryReminderMail($anniversary->user, $anniversary, $daysLeft));
                    
                    $this->info("✓ Email đã được gửi!");
                } catch (\Exception $e) {
                    $this->error("✗ Lỗi: " . $e->getMessage());
                }
            }
        }
    }
}
