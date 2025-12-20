<?php

namespace App\Console\Commands;

use App\Jobs\SendAnniversaryReminderJob;
use App\Mail\AnniversaryReminderMail;
use App\Models\UserAnniversary;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class TestAnniversaryEmail extends Command
{
    protected $signature = 'anniversary:test-email 
                            {--anniversary-id= : ID của anniversary cần test}
                            {--force : Force gửi email (bỏ qua tracking)}
                            {--all : Test tất cả anniversaries hợp lệ}
                            {--run-job : Chạy job SendAnniversaryReminderJob}';

    protected $description = 'Test gửi email nhắc nhở anniversary - Debug tool';

    public function handle()
    {
        $this->info('=== TEST ANNIVERSARY EMAIL REMINDER ===');
        $this->newLine();

        // Hiển thị cấu hình mail
        $this->displayMailConfig();

        if ($this->option('run-job')) {
            $this->info('Chạy SendAnniversaryReminderJob...');
            try {
                $job = new SendAnniversaryReminderJob();
                $job->handle();
                $this->info('✓ Job đã chạy xong!');
            } catch (\Exception $e) {
                $this->error('✗ Lỗi khi chạy job: ' . $e->getMessage());
                $this->error('Stack trace: ' . $e->getTraceAsString());
            }
            return;
        }

        if ($this->option('all')) {
            $this->testAllAnniversaries($this->option('force'));
            return;
        }

        $anniversaryId = $this->option('anniversary-id');
        if ($anniversaryId) {
            $this->testSpecificAnniversary($anniversaryId, $this->option('force'));
            return;
        }

        // Interactive mode
        $this->info('Chọn chế độ test:');
        $this->info('1. Test một anniversary cụ thể (nhập ID)');
        $this->info('2. Test tất cả anniversaries hợp lệ');
        $this->info('3. Chạy job SendAnniversaryReminderJob');
        $this->info('4. Hiển thị danh sách anniversaries sắp tới');

        $choice = $this->ask('Nhập lựa chọn (1-4)');

        switch ($choice) {
            case '1':
                $id = $this->ask('Nhập anniversary_id');
                $force = $this->confirm('Force gửi (bỏ qua tracking)?', false);
                $this->testSpecificAnniversary($id, $force);
                break;
            case '2':
                $force = $this->confirm('Force gửi (bỏ qua tracking)?', false);
                $this->testAllAnniversaries($force);
                break;
            case '3':
                $this->info('Chạy SendAnniversaryReminderJob...');
                try {
                    $job = new SendAnniversaryReminderJob();
                    $job->handle();
                    $this->info('✓ Job đã chạy xong!');
                } catch (\Exception $e) {
                    $this->error('✗ Lỗi khi chạy job: ' . $e->getMessage());
                    $this->error('Stack trace: ' . $e->getTraceAsString());
                }
                break;
            case '4':
                $this->displayUpcomingAnniversaries();
                break;
            default:
                $this->error('Lựa chọn không hợp lệ!');
        }
    }

    private function displayMailConfig()
    {
        $this->info('Cấu hình Mail hiện tại:');
        $this->table(
            ['Key', 'Value'],
            [
                ['MAIL_MAILER', Config::get('mail.default')],
                ['MAIL_HOST', Config::get('mail.mailers.smtp.host')],
                ['MAIL_PORT', Config::get('mail.mailers.smtp.port')],
                ['MAIL_ENCRYPTION', Config::get('mail.mailers.smtp.encryption')],
                ['MAIL_USERNAME', Config::get('mail.mailers.smtp.username') ?: '(not set)'],
                ['MAIL_FROM_ADDRESS', Config::get('mail.from.address')],
                ['MAIL_FROM_NAME', Config::get('mail.from.name')],
            ]
        );
        $this->newLine();
    }

    private function testSpecificAnniversary($anniversaryId, $force = false)
    {
        $anniversary = UserAnniversary::with('user')->find($anniversaryId);

        if (!$anniversary) {
            $this->error("Không tìm thấy anniversary với ID: {$anniversaryId}");
            return;
        }

        $this->info("Testing anniversary: ID {$anniversary->anniversary_id}");
        $this->info("Event: {$anniversary->event_name}");
        $this->info("Date: {$anniversary->event_date}");
        $this->info("User: {$anniversary->user->name} ({$anniversary->user->email})");
        $this->newLine();

        $today = Carbon::today();
        $currentYear = $today->year;
        $eventDate = Carbon::parse($anniversary->event_date);
        $eventThisYear = Carbon::create($currentYear, $eventDate->month, $eventDate->day);

        if ($eventThisYear->lt($today)) {
            $eventThisYear->addYear();
        }

        $daysLeft = $today->diffInDays($eventThisYear, false);
        $this->info("Số ngày còn lại: {$daysLeft}");

        if (!in_array($daysLeft, [15, 10])) {
            $this->warn("⚠ Anniversary không trong vòng 15-10 ngày (hiện tại: {$daysLeft} ngày)");
            if (!$this->confirm('Vẫn muốn test gửi email?', false)) {
                return;
            }
        }

        $daysToTest = $this->choice('Chọn số ngày để test', ['15', '10'], $daysLeft == 15 ? 0 : 1);

        $this->info("Đang gửi email test với {$daysToTest} ngày...");

        try {
            Mail::to($anniversary->user->email)
                ->send(new AnniversaryReminderMail($anniversary->user, $anniversary, (int)$daysToTest));

            $this->info("✓ Email đã được gửi đến: {$anniversary->user->email}");

            if (!$force && in_array($daysLeft, [15, 10])) {
                $trackingField = $daysToTest == 15 ? 'reminder_15_days_sent_at' : 'reminder_10_days_sent_at';
                $anniversary->update([$trackingField => $today]);
                $this->info("✓ Đã cập nhật tracking field: {$trackingField}");
            }

        } catch (\Exception $e) {
            $this->error('✗ Lỗi khi gửi email: ' . $e->getMessage());
            $this->error('Stack trace:');
            $this->line($e->getTraceAsString());
            Log::error('Test email failed', [
                'anniversary_id' => $anniversary->anniversary_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    private function testAllAnniversaries($force = false)
    {
        $today = Carbon::today();
        $currentYear = $today->year;

        $anniversaries = UserAnniversary::with('user')->get();
        $validAnniversaries = [];

        foreach ($anniversaries as $anniversary) {
            $eventDate = Carbon::parse($anniversary->event_date);
            $eventThisYear = Carbon::create($currentYear, $eventDate->month, $eventDate->day);

            if ($eventThisYear->lt($today)) {
                $eventThisYear->addYear();
            }

            $daysLeft = $today->diffInDays($eventThisYear, false);

            if (in_array($daysLeft, [15, 10])) {
                $shouldSend = $force;
                if (!$force) {
                    if ($daysLeft == 15 && !$anniversary->reminder_15_days_sent_at) {
                        $shouldSend = true;
                    } elseif ($daysLeft == 10 && !$anniversary->reminder_10_days_sent_at) {
                        $shouldSend = true;
                    }
                }

                if ($shouldSend) {
                    $validAnniversaries[] = [
                        'anniversary' => $anniversary,
                        'days_left' => $daysLeft,
                    ];
                }
            }
        }

        if (empty($validAnniversaries)) {
            $this->warn('Không có anniversary nào hợp lệ để gửi email!');
            return;
        }

        $this->info("Tìm thấy " . count($validAnniversaries) . " anniversary(s) hợp lệ:");
        $this->newLine();

        foreach ($validAnniversaries as $item) {
            $anniversary = $item['anniversary'];
            $daysLeft = $item['days_left'];
            $this->info("ID: {$anniversary->anniversary_id} | {$anniversary->event_name} | {$anniversary->event_date} | User: {$anniversary->user->email} | Còn {$daysLeft} ngày");
        }

        $this->newLine();
        if (!$this->confirm('Bạn có muốn gửi email cho tất cả?', true)) {
            return;
        }

        $successCount = 0;
        $failCount = 0;

        foreach ($validAnniversaries as $item) {
            $anniversary = $item['anniversary'];
            $daysLeft = $item['days_left'];

            try {
                Mail::to($anniversary->user->email)
                    ->send(new AnniversaryReminderMail($anniversary->user, $anniversary, $daysLeft));

                $successCount++;
                $this->info("✓ Đã gửi email đến: {$anniversary->user->email}");

                if (!$force) {
                    $trackingField = $daysLeft == 15 ? 'reminder_15_days_sent_at' : 'reminder_10_days_sent_at';
                    $anniversary->update([$trackingField => $today]);
                }
            } catch (\Exception $e) {
                $failCount++;
                $this->error("✗ Lỗi gửi email đến {$anniversary->user->email}: " . $e->getMessage());
                Log::error('Test email failed', [
                    'anniversary_id' => $anniversary->anniversary_id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("Kết quả: {$successCount} thành công, {$failCount} thất bại");
    }

    private function displayUpcomingAnniversaries()
    {
        $today = Carbon::today();
        $currentYear = $today->year;

        $anniversaries = UserAnniversary::with('user')
            ->orderBy('event_date')
            ->get();

        $upcoming = [];

        foreach ($anniversaries as $anniversary) {
            $eventDate = Carbon::parse($anniversary->event_date);
            $eventThisYear = Carbon::create($currentYear, $eventDate->month, $eventDate->day);

            if ($eventThisYear->lt($today)) {
                $eventThisYear->addYear();
            }

            $daysLeft = $today->diffInDays($eventThisYear, false);

            if ($daysLeft >= 0 && $daysLeft <= 20) {
                $upcoming[] = [
                    'id' => $anniversary->anniversary_id,
                    'event_name' => $anniversary->event_name,
                    'event_date' => $anniversary->event_date,
                    'user_email' => $anniversary->user->email ?? 'N/A',
                    'days_left' => $daysLeft,
                    'reminder_15_sent' => $anniversary->reminder_15_days_sent_at ? 'Yes' : 'No',
                    'reminder_10_sent' => $anniversary->reminder_10_days_sent_at ? 'Yes' : 'No',
                ];
            }
        }

        if (empty($upcoming)) {
            $this->warn('Không có anniversary nào sắp tới (trong vòng 20 ngày)!');
            return;
        }

        $this->info('Danh sách anniversaries sắp tới (trong vòng 20 ngày):');
        $this->table(
            ['ID', 'Event Name', 'Date', 'User Email', 'Days Left', '15d Sent', '10d Sent'],
            array_map(function ($item) {
                return [
                    $item['id'],
                    $item['event_name'],
                    $item['event_date'],
                    $item['user_email'],
                    $item['days_left'],
                    $item['reminder_15_sent'],
                    $item['reminder_10_sent'],
                ];
            }, $upcoming)
        );
    }
}
