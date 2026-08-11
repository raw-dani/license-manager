<?php

namespace App\Notifications;

use App\Models\License;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class LicenseStatusChanged extends Notification
{
    use Queueable;

    public function __construct(public License $license, public string $status)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->status) {
            'suspended' => 'Your License Has Been Suspended',
            'terminated' => 'Your License Has Been Terminated',
            'expired' => 'Your License Has Expired',
            'active' => 'Your License Has Been Reactivated',
            default => 'License Status Updated',
        };

        return (new MailMessage)
            ->subject($subject)
            ->greeting("Hello {$this->license->customer_name},")
            ->line("License key {$this->license->license_key} status has been updated to: " . ucfirst($this->status))
            ->action('View License', url('/admin/licenses/' . $this->license->id))
            ->line('If you have any questions, please contact support.');
    }
}
