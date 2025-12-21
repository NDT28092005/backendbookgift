<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use App\Mail\AnniversaryReminderMail;
use App\Models\UserAnniversary;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

// DEBUG ROUTES - Chỉ dùng trong môi trường development
if (app()->environment(['local', 'testing'])) {
    Route::get('/test/anniversary-email/{anniversaryId?}', function ($anniversaryId = null) {
        try {
            $config = [
                'MAIL_MAILER' => config('mail.default'),
                'MAIL_HOST' => config('mail.mailers.smtp.host'),
                'MAIL_PORT' => config('mail.mailers.smtp.port'),
                'MAIL_FROM_ADDRESS' => config('mail.from.address'),
            ];

            if (!$anniversaryId) {
                // Hiển thị danh sách anniversaries
                $anniversaries = UserAnniversary::with('user')
                    ->orderBy('event_date')
                    ->limit(20)
                    ->get();

                $list = $anniversaries->map(function ($ann) {
                    $today = \Carbon\Carbon::today();
                    $eventDate = \Carbon\Carbon::parse($ann->event_date);
                    $eventThisYear = \Carbon\Carbon::create($today->year, $eventDate->month, $eventDate->day);
                    if ($eventThisYear->lt($today)) {
                        $eventThisYear->addYear();
                    }
                    $daysLeft = $today->diffInDays($eventThisYear, false);

                    return [
                        'id' => $ann->anniversary_id,
                        'event_name' => $ann->event_name,
                        'event_date' => $ann->event_date,
                        'user_email' => $ann->user->email ?? 'N/A',
                        'days_left' => $daysLeft,
                        'reminder_15_sent' => $ann->reminder_15_days_sent_at ? 'Yes' : 'No',
                        'reminder_10_sent' => $ann->reminder_10_days_sent_at ? 'Yes' : 'No',
                    ];
                });

                return response()->json([
                    'config' => $config,
                    'anniversaries' => $list,
                    'usage' => 'Thêm ?anniversary_id=ID vào URL để test gửi email cho anniversary cụ thể'
                ]);
            }

            // Test gửi email cho anniversary cụ thể
            $anniversary = UserAnniversary::with('user')->find($anniversaryId);
            
            if (!$anniversary) {
                return response()->json(['error' => 'Anniversary not found'], 404);
            }

            $today = \Carbon\Carbon::today();
            $eventDate = \Carbon\Carbon::parse($anniversary->event_date);
            $eventThisYear = \Carbon\Carbon::create($today->year, $eventDate->month, $eventDate->day);
            if ($eventThisYear->lt($today)) {
                $eventThisYear->addYear();
            }
            $daysLeft = $today->diffInDays($eventThisYear, false);
            
            // Sử dụng 15 hoặc 10 ngày, hoặc dùng giá trị thực tế
            $testDays = in_array($daysLeft, [15, 10]) ? $daysLeft : 15;

            \Illuminate\Support\Facades\Mail::to($anniversary->user->email)
                ->send(new AnniversaryReminderMail($anniversary->user, $anniversary, $testDays));

            return response()->json([
                'success' => true,
                'message' => 'Email đã được gửi',
                'config' => $config,
                'anniversary' => [
                    'id' => $anniversary->anniversary_id,
                    'event_name' => $anniversary->event_name,
                    'event_date' => $anniversary->event_date,
                    'user_email' => $anniversary->user->email,
                    'days_left' => $daysLeft,
                    'test_days' => $testDays,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'config' => $config ?? [],
            ], 500);
        }
    })->name('test.anniversary.email');
}