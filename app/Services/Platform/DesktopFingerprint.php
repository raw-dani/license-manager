<?php

namespace App\Services\Platform;

class DesktopFingerprint
{
    /**
     * Generate a hardware fingerprint hash from device identifiers.
     * Combines CPU ID, MAC address, and disk serial into a 128-char hash.
     */
    public static function generate(array $deviceInfo): string
    {
        $identifiers = [
            $deviceInfo['cpu_id'] ?? '',
            $deviceInfo['mac_address'] ?? '',
            $deviceInfo['disk_serial'] ?? '',
            $deviceInfo['motherboard_serial'] ?? '',
            $deviceInfo['hostname'] ?? '',
        ];

        $raw = implode('|', array_filter($identifiers));

        if (empty($raw)) {
            throw new \InvalidArgumentException('No valid hardware identifiers provided');
        }

        return hash('sha512', $raw);
    }

    /**
     * Validate that the fingerprint matches the expected format.
     */
    public static function isValid(string $fingerprint): bool
    {
        return strlen($fingerprint) === 128 && ctype_xdigit($fingerprint);
    }
}