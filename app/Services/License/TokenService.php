<?php

namespace App\Services\License;

use App\Models\Setting;
use RuntimeException;

class TokenService
{
    /**
     * Generate a signed token for a license.
     */
    public static function generate(array $payload, int $ttlHours = 24): string
    {
        $secret = Setting::get('api_key', '');
        if (empty($secret)) {
            throw new RuntimeException('API key not configured');
        }

        $payload['iat'] = time();
        $payload['exp'] = time() + ($ttlHours * 3600);
        $payload['jti'] = bin2hex(random_bytes(16));
        ksort($payload);

        $payloadEncoded = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $payloadEncoded, $secret, true);

        return $payloadEncoded . '.' . base64_encode($signature);
    }

    /**
     * Verify a token and return the payload, or null if invalid/expired.
     */
    public static function verify(?string $token): ?array
    {
        if (empty($token)) {
            return null;
        }

        $secret = Setting::get('api_key', '');
        if (empty($secret)) {
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 2) {
            return null;
        }

        [$payloadEncoded, $signatureEncoded] = $parts;

        // Verify signature
        $expectedSignature = hash_hmac('sha256', $payloadEncoded, $secret, true);
        if (!hash_equals($expectedSignature, base64_decode($signatureEncoded))) {
            return null;
        }

        // Decode payload
        $payload = json_decode(base64_decode($payloadEncoded), true);
        if (!is_array($payload)) {
            return null;
        }

        // Check expiry
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return null;
        }

        return $payload;
    }
}