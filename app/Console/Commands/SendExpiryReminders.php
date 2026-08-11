<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Notifications\LicenseExpiringSoon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

class SendExpiryReminders extends Command
{
    protected $signature = 'license:reminder';

    protected $description = 'Send expiry reminders for licenses expiring in 7, 3, or 1 day(s)';

    public function handle(): int
    {
        $now = now();
        $reminderDays = [7, 3, 1];
        $count = 0;

        if (in_array(Config::get('mail.default'), ['log', 'array'], true)) {
            $this->info('Mail is not configured. Skipping email reminders.');
            return Command::SUCCESS;
        }

        foreach ($reminderDays as $days) {
            $targetDate = $now->copy()->addDays($days);

            $licenses = License::where('status', 'active')
                ->whereNotNull('expires_at')
                ->whereDate('expires_at', $targetDate->toDateString())
                ->whereNotNull('customer_email')
                ->get();

            foreach ($licenses as $license) {
                Notification::route('mail', $license->customer_email)
                    ->notify(new LicenseExpiringSoon($license, $days));

                ActivationLog::create([
                    'license_id' => $license->id,
                    'license_key' => $license->license_key,
                    'action' => 'verify',
                    'notes' => "Expiry reminder sent: license expires in {$days} day(s) on {$license->expires_at->toDateTimeString()}",
                ]);

                $count++;
            }
        }

        $this->info("Sent {$count} expiry reminder(s).");

        return Command::SUCCESS;
    }
}