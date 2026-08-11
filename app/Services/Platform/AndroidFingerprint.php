<?php

namespace App\Services\Platform;

class AndroidFingerprint
{
    /**
     * Generate an Android device fingerprint from Android ID + package name.
     * Note: Android SDK is pending, but the endpoint is prepared.
     */
    public static function generate(string $androidId, ?string $packageName = null): string
    {
        $raw = $androidId . '|' . ($packageName ?? '');

        if (empty($androidId)) {
            throw new \InvalidArgumentException('Android ID is required for android platform');
        }

        return hash('sha256', $raw);
    }

    /**
     * Validate Android ID format (typically 16 hex chars).
     */
    public static function isValidAndroidId(string $androidId): bool
    {
        return preg_match('/^[a-fA-F0-9]{16}$/', $androidId) === 1;
    }
}