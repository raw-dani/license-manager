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

        if (!$this->isSafeWebhookUrl($license->webhook_url)) {
            Log::warning('Webhook URL blocked (SSRF protection)', [
                'license_key' => $license->license_key,
                'webhook_url' => $license->webhook_url,
            ]);
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

        if (!$this->isSafeWebhookUrl($license->webhook_url)) {
            Log::warning('Webhook URL blocked (SSRF protection)', [
                'license_key' => $license->license_key,
                'webhook_url' => $license->webhook_url,
            ]);
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

    private function isSafeWebhookUrl(string $url): bool
    {
        $parsed = parse_url($url);

        if (!$parsed || !in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            return false;
        }

        $host = $parsed['host'] ?? '';

        if (empty($host)) {
            return false;
        }

        $blockedHosts = ['localhost', '0.0.0.0', '::1', '[::1]'];
        if (in_array(strtolower($host), $blockedHosts, true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $this->isPublicIp($host);
        }

        $ip = gethostbyname($host);
        if ($ip === $host) {
            return true;
        }

        return $this->isPublicIp($ip);
    }

    private function isPublicIp(string $ip): bool
    {
        return (bool) filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
}
