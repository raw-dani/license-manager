<?php

namespace App\Services\Platform;

class ServerFingerprint
{
    /**
     * Generate a server fingerprint from IP + hostname + OS info.
     */
    public static function generate(string $ipAddress, ?string $hostname = null, ?string $osInfo = null): string
    {
        $raw = $ipAddress . '|' . ($hostname ?? '') . '|' . ($osInfo ?? '');

        if (empty($ipAddress)) {
            throw new \InvalidArgumentException('IP address is required for server platform');
        }

        return hash('sha256', $raw);
    }

    /**
     * Validate IP address format.
     */
    public static function isValidIp(string $ipAddress): bool
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP) !== false;
    }
}