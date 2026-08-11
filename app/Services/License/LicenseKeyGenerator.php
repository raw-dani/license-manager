<?php

namespace App\Services\License;

use App\Models\Setting;

class LicenseKeyGenerator
{
    /**
     * Generate a new license key with the configured prefix.
     * Format: PREFIX-XXXX-XXXX-XXXX
     */
    public static function generate(): string
    {
        $prefix = Setting::get('license_key_prefix', 'SP-');
        $segments = [
            strtoupper(bin2hex(random_bytes(2))),
            strtoupper(bin2hex(random_bytes(2))),
            strtoupper(bin2hex(random_bytes(2))),
        ];

        return $prefix . implode('-', $segments);
    }

    /**
     * Generate a unique license key that doesn't exist in the database.
     */
    public static function generateUnique(): string
    {
        do {
            $key = self::generate();
        } while (\App\Models\License::where('license_key', $key)->exists());

        return $key;
    }
}