<?php

namespace App\Services\License;

use App\Models\License;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    public function notifySuspension(License $license): bool
    {
        if (empty($license->webhook_url) || empty($license->webhook_secret)) {
            return false;
        }

        $payload = [
            'event' => 'license.suspended',
            'license_key' => $license->license_key,
            'status' => 'suspended',
            'suspended_at' => $license->suspended_at?->toDateTimeString(),
            'timestamp' => now()->toDateTimeString(),
        ];

        $payload['signature'] = $this->generateSignature($payload, $license->webhook_secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-License-Event' => 'license.suspended',
                ])
                ->post($license->webhook_url, $payload);

            $success = $response->successful();

            if (!$success) {
                Log::warning('Webhook notification failed', [
                    'license_key' => $license->license_key,
                    'webhook_url' => $license->webhook_url,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }

            return $success;
        } catch (\Exception $e) {
            Log::error('Webhook notification exception', [
                'license_key' => $license->license_key,
                'webhook_url' => $license->webhook_url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function notifyReactivation(License $license): bool
    {
        if (empty($license->webhook_url) || empty($license->webhook_secret)) {
            return false;
        }

        $payload = [
            'event' => 'license.reactivated',
            'license_key' => $license->license_key,
            'status' => 'active',
            'timestamp' => now()->toDateTimeString(),
        ];

        $payload['signature'] = $this->generateSignature($payload, $license->webhook_secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-License-Event' => 'license.reactivated',
                ])
                ->post($license->webhook_url, $payload);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Webhook reactivation notification exception', [
                'license_key' => $license->license_key,
                'webhook_url' => $license->webhook_url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function verifySignature(array $payload, string $secret): bool
    {
        if (empty($payload['signature'])) {
            return false;
        }

        $providedSignature = $payload['signature'];
        $expectedSignature = $this->generateSignature($payload, $secret);

        return hash_equals($expectedSignature, $providedSignature);
    }

    private function generateSignature(array $payload, string $secret): string
    {
        $dataToSign = $payload;
        unset($dataToSign['signature']);

        $json = json_encode($dataToSign, JSON_UNESCAPED_SLASHES);

        return hash_hmac('sha256', $json, $secret);
    }
}
