<?php

namespace App\Services\License;

use App\Models\ActivationLog;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Models\LicenseInstallation;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class LicenseService
{
    /**
     * Activate a license for a device.
     *
     * @return array{token: string, expires_in: int, license: License}
     */
    public function activate(string $licenseKey, string $fingerprint, string $platform, array $deviceInfo = [], ?string $domain = null, ?string $ipAddress = null): array
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            $this->log($licenseKey, 'activate', $platform, $fingerprint, $deviceInfo, 'FAILED: License not found');
            throw new RuntimeException('License not found', 404);
        }

        if ($license->status !== 'active') {
            $this->log($licenseKey, 'activate', $platform, $fingerprint, $deviceInfo, 'FAILED: License is ' . $license->status);
            throw new RuntimeException('License is ' . $license->status, 403);
        }

        if ($license->isExpired()) {
            $license->update(['status' => 'expired']);
            $this->log($licenseKey, 'auto_expire', $platform, $fingerprint, $deviceInfo, 'License expired');
            throw new RuntimeException('License is expired', 403);
        }

        // Check if this device is already activated
        $existingActivation = LicenseActivation::where('license_id', $license->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($existingActivation) {
            // Re-activate existing device
            $existingActivation->update([
                'status' => 'active',
                'last_verified_at' => now(),
                'device_info' => $deviceInfo,
                'domain' => $domain,
                'ip_address' => $ipAddress,
            ]);

            $this->log($licenseKey, 'activate', $platform, $fingerprint, $deviceInfo, 'Device re-activated');

            return $this->buildResponse($license, $fingerprint, $platform, $domain);
        }

        // Check if max activations reached
        if ($license->current_activations >= $license->max_activations) {
            $this->log($licenseKey, 'activate', $platform, $fingerprint, $deviceInfo, 'FAILED: Max activations reached');
            throw new RuntimeException('Max activations reached', 403);
        }

        // Create new activation
        DB::transaction(function () use ($license, $fingerprint, $platform, $deviceInfo, $domain, $ipAddress) {
            LicenseActivation::create([
                'license_id' => $license->id,
                'fingerprint' => $fingerprint,
                'platform' => $platform,
                'device_info' => $deviceInfo,
                'domain' => $domain,
                'ip_address' => $ipAddress,
                'status' => 'active',
                'last_verified_at' => now(),
            ]);

            $license->increment('current_activations');
            $license->update([
                'activated_at' => $license->activated_at ?? now(),
                'last_verified_at' => now(),
            ]);
        });

        $this->log($licenseKey, 'activate', $platform, $fingerprint, $deviceInfo, 'SUCCESS');

        return $this->buildResponse($license, $fingerprint, $platform, $domain);
    }

    /**
     * Verify a license for a device.
     */
    public function verify(string $licenseKey, string $fingerprint, string $platform, array $deviceInfo = [], ?string $domain = null, ?string $ipAddress = null): array
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            $this->log($licenseKey, 'verify', $platform, $fingerprint, $deviceInfo, 'FAILED: License not found');
            throw new RuntimeException('License not found', 404);
        }

        if ($license->status !== 'active') {
            $this->log($licenseKey, 'verify', $platform, $fingerprint, $deviceInfo, 'FAILED: License is ' . $license->status);
            throw new RuntimeException('License is ' . $license->status, 403);
        }

        if ($license->isExpired()) {
            $license->update(['status' => 'expired']);
            $this->log($licenseKey, 'auto_expire', $platform, $fingerprint, $deviceInfo, 'License expired');
            throw new RuntimeException('License is expired', 403);
        }

        // Find activation for this device
        $activation = LicenseActivation::where('license_id', $license->id)
            ->where('fingerprint', $fingerprint)
            ->where('status', 'active')
            ->first();

        if (!$activation) {
            $this->log($licenseKey, 'verify', $platform, $fingerprint, $deviceInfo, 'FAILED: Device not activated');
            throw new RuntimeException('Device not activated', 403);
        }

        // Update last verified
        $activation->update([
            'last_verified_at' => now(),
            'device_info' => $deviceInfo,
            'domain' => $domain,
            'ip_address' => $ipAddress,
        ]);

        $license->update(['last_verified_at' => now()]);

        $this->checkInstallationBinding($license, $activation, $fingerprint, $platform, $domain, $ipAddress, $deviceInfo);

        $this->log($licenseKey, 'verify', $platform, $fingerprint, $deviceInfo, 'SUCCESS');

        return $this->buildResponse($license, $fingerprint, $platform, $domain);
    }

    /**
     * Deactivate a device from a license.
     */
    public function deactivate(string $licenseKey, string $fingerprint, string $platform, array $deviceInfo = []): void
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            $this->log($licenseKey, 'deactivate', $platform, $fingerprint, $deviceInfo, 'FAILED: License not found');
            throw new RuntimeException('License not found', 404);
        }

        $activation = LicenseActivation::where('license_id', $license->id)
            ->where('fingerprint', $fingerprint)
            ->first();

        if ($activation) {
            DB::transaction(function () use ($license, $activation) {
                $activation->update(['status' => 'inactive']);
                if ($license->current_activations > 0) {
                    $license->decrement('current_activations');
                }
            });
        }

        $this->log($licenseKey, 'deactivate', $platform, $fingerprint, $deviceInfo, 'SUCCESS');
    }

    /**
     * Build the response with a new token.
     */
    private function buildResponse(License $license, string $fingerprint, string $platform, ?string $domain): array
    {
        $ttl = (int) Setting::get('verify_ttl_hours', 24);

        $token = TokenService::generate([
            'license_key' => $license->license_key,
            'fingerprint' => $fingerprint,
            'platform' => $platform,
            'domain' => $domain,
            'status' => 'active',
        ], $ttl);

        return [
            'token' => $token,
            'expires_in' => $ttl * 3600,
            'expires_at' => now()->addHours($ttl)->toDateTimeString(),
            'license' => $license,
        ];
    }

    /**
     * Log an activity.
     */
    private function log(string $licenseKey, string $action, string $platform, string $fingerprint, array $deviceInfo, string $notes): void
    {
        $license = License::where('license_key', $licenseKey)->first();

        ActivationLog::create([
            'license_id' => $license?->id,
            'license_key' => $licenseKey,
            'action' => $action,
            'platform' => $platform,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'fingerprint' => $fingerprint,
            'device_info' => $deviceInfo,
            'notes' => $notes,
        ]);
    }

    /**
     * Check whether the current request is bound to the licensed installation.
     * If not bound yet, automatically bind. If bound to a different install_id, reject.
     */
    private function checkInstallationBinding(
        License $license,
        LicenseActivation $activation,
        string $fingerprint,
        string $platform,
        ?string $domain,
        ?string $ipAddress,
        array $deviceInfo
    ): void {
        $installId = $deviceInfo['install_id'] ?? request()->header('X-Install-Id');

        if (!$installId) {
            return;
        }

        $existing = LicenseInstallation::where('license_id', $license->id)
            ->where('is_active', true)
            ->first();

        if (!$existing) {
            LicenseInstallation::create([
                'license_id' => $license->id,
                'license_activation_id' => $activation->id,
                'install_id' => $installId,
                'fingerprint' => $fingerprint,
                'platform' => $platform,
                'domain' => $domain,
                'ip_address' => $ipAddress,
                'hostname' => $deviceInfo['hostname'] ?? null,
                'server_info' => $deviceInfo,
                'bound_at' => now(),
                'last_verified_at' => now(),
                'is_active' => true,
            ]);
            return;
        }

        if ($existing->install_id !== $installId) {
            $this->log(
                $license->license_key,
                'verify',
                $platform,
                $fingerprint,
                $deviceInfo,
                'FAILED: Install ID mismatch. Expected: ' . $existing->install_id . ', Got: ' . $installId
            );
            throw new RuntimeException(
                'License is bound to a different installation. Transfer the license to this server or contact admin.',
                403
            );
        }

        $existing->update([
            'last_verified_at' => now(),
            'fingerprint' => $fingerprint,
            'ip_address' => $ipAddress,
            'domain' => $domain,
        ]);
    }

    /**
     * Bind a license to a new installation (transfer).
     * Requires a valid transfer token.
     */
    public function bind(string $licenseKey, string $installId, ?string $transferToken = null, array $deviceInfo = []): array
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            throw new RuntimeException('License not found', 404);
        }

        if ($license->status !== 'active') {
            throw new RuntimeException('License is ' . $license->status, 403);
        }

        if ($license->isExpired()) {
            throw new RuntimeException('License is expired', 403);
        }

        $existing = LicenseInstallation::where('license_id', $license->id)
            ->where('is_active', true)
            ->first();

        if ($transferToken) {
            $tokenMatch = LicenseInstallation::where('license_id', $license->id)
                ->where('transfer_token', $transferToken)
                ->where('transfer_token_expires_at', '>', now())
                ->first();

            if (!$tokenMatch) {
                $this->log($licenseKey, 'bind', $deviceInfo['platform'] ?? 'unknown', $deviceInfo['fingerprint'] ?? '', $deviceInfo, 'FAILED: Invalid or expired transfer token');
                throw new RuntimeException('Invalid or expired transfer token', 403);
            }
        } elseif ($existing && $existing->install_id !== $installId) {
            $this->log($licenseKey, 'bind', $deviceInfo['platform'] ?? 'unknown', $deviceInfo['fingerprint'] ?? '', $deviceInfo, 'FAILED: Transfer token required to rebind to new server');
            throw new RuntimeException('Transfer token required to bind to a new server. Use POST /license/transfer-token to request one from admin.', 403);
        }

        DB::transaction(function () use ($license, $installId, $deviceInfo, $existing) {
            if ($existing) {
                $existing->update(['is_active' => false]);
            }

            LicenseInstallation::create([
                'license_id' => $license->id,
                'install_id' => $installId,
                'fingerprint' => $deviceInfo['fingerprint'] ?? null,
                'platform' => $deviceInfo['platform'] ?? 'unknown',
                'domain' => $deviceInfo['domain'] ?? null,
                'ip_address' => request()->ip(),
                'hostname' => $deviceInfo['hostname'] ?? null,
                'server_info' => $deviceInfo,
                'bound_at' => now(),
                'last_verified_at' => now(),
                'is_active' => true,
            ]);
        });

        $this->log($licenseKey, 'bind', $deviceInfo['platform'] ?? 'unknown', $deviceInfo['fingerprint'] ?? '', $deviceInfo, 'License rebound to install_id: ' . $installId);

        return [
            'license_key' => $license->license_key,
            'install_id' => $installId,
            'bound_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Generate a transfer token. Admin uses this to authorize moving
     * a license from one server to another.
     */
    public function generateTransferToken(string $licenseKey, int $ttlHours = 24): string
    {
        $license = License::where('license_key', $licenseKey)->first();

        if (!$license) {
            throw new RuntimeException('License not found', 404);
        }

        $token = Str::random(64);

        $existing = LicenseInstallation::where('license_id', $license->id)->first();

        if ($existing) {
            $existing->update([
                'transfer_token' => hash('sha256', $token),
                'transfer_token_expires_at' => now()->addHours($ttlHours),
            ]);
        } else {
            LicenseInstallation::create([
                'license_id' => $license->id,
                'install_id' => 'pending-transfer',
                'platform' => 'unknown',
                'transfer_token' => hash('sha256', $token),
                'transfer_token_expires_at' => now()->addHours($ttlHours),
                'is_active' => false,
            ]);
        }

        $this->log($licenseKey, 'transfer_token', 'admin', '', [], 'Transfer token generated (expires in ' . $ttlHours . 'h)');

        return $token;
    }
}