<?php

namespace App\Notifications;

use App\Models\License;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class LicenseExpiringSoon extends Notification
{
    use Queueable;

    public function __construct(public License $license, public int $daysRemaining)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("License Expiring in {$this->daysRemaining} Day(s)")
            ->greeting("Hello {$this->license->customer_name},")
            ->line("Your license key {$this->license->license_key} will expire in {$this->daysRemaining} day(s).")
            ->line("Expires at: " . $this->license->expires_at->toDateTimeString())
            ->action('Renew License', url('/admin/licenses/' . $this->license->id))
            ->line('Please renew your license to avoid service interruption.');
    }
}
