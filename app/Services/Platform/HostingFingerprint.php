<?php

namespace App\Services\Platform;

class HostingFingerprint
{
    /**
     * Generate a hosting fingerprint from domain + cPanel username.
     */
    public static function generate(string $domain, ?string $username = null): string
    {
        $raw = $domain . '|' . ($username ?? '');

        if (empty($domain)) {
            throw new \InvalidArgumentException('Domain is required for hosting platform');
        }

        return hash('sha256', $raw);
    }

    /**
     * Validate domain format.
     */
    public static function isValidDomain(string $domain): bool
    {
        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;
    }
}