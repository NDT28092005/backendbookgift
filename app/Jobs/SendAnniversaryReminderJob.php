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

        $anniversaries = UserAnniversary::with('user')
            ->get()
            ->filter(function ($anniversary) use ($today) {
                $daysUntil = Carbon::parse($anniversary->event_date)->diffInDays($today, false);
                return in_array($daysUntil, [-7, -1]); // sắp tới trong 7 hoặc 1 ngày
            });

        foreach ($anniversaries as $anniversary) {
            $daysLeft = Carbon::parse($anniversary->event_date)->diffInDays($today, false) * -1;

            Mail::to($anniversary->user->email)
                ->send(new AnniversaryReminderMail($anniversary->user, $anniversary, $daysLeft));
        }
    }
}
