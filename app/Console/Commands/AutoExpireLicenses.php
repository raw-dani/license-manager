<?php

namespace App\Console\Commands;

use App\Models\ActivationLog;
use App\Models\License;
use App\Notifications\LicenseStatusChanged;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

class AutoExpireLicenses extends Command
{
    protected $signature = 'license:auto-expire';

    protected $description = 'Auto-expire licenses that have passed their expiry date';

    public function handle(): int
    {
        $now = now();
        $mailEnabled = !in_array(Config::get('mail.default'), ['log', 'array'], true);

        $expiredLicenses = License::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->get();

        $count = 0;
        foreach ($expiredLicenses as $license) {
            $license->update(['status' => 'expired']);

            ActivationLog::create([
                'license_id' => $license->id,
                'license_key' => $license->license_key,
                'action' => 'auto_expire',
                'notes' => 'License automatically expired on ' . $now->toDateTimeString(),
            ]);

            if ($mailEnabled && $license->customer_email) {
                Notification::route('mail', $license->customer_email)
                    ->notify(new LicenseStatusChanged($license, 'expired'));
            }

            $count++;
        }

        $this->info("Auto-expired {$count} license(s).");

        return Command::SUCCESS;
    }
}