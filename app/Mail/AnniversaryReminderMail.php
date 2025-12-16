<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AnniversaryReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $anniversary;
    public $daysLeft;

    public function __construct($user, $anniversary, $daysLeft)
    {
        $this->user = $user;
        $this->anniversary = $anniversary;
        $this->daysLeft = $daysLeft;
    }

    public function build()
    {
        return $this->subject('🎉 Nhắc bạn sắp tới dịp ' . $this->anniversary->event_name)
                    ->view('emails.anniversary_reminder');
    }
}
