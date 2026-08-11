<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => [
                'verify_ttl_hours' => Setting::get('verify_ttl_hours', 24),
                'grace_period_days' => Setting::get('grace_period_days', 7),
                'license_key_prefix' => Setting::get('license_key_prefix', 'SP-'),
                'api_enabled' => Setting::get('api_enabled', 1),
                'api_key' => Setting::get('api_key', ''),
                'whmcs_enabled' => Setting::get('whmcs_enabled', 0),
                'whmcs_url' => Setting::get('whmcs_url', ''),
                'whmcs_api_identifier' => Setting::get('whmcs_api_identifier', ''),
                'whmcs_api_secret' => Setting::get('whmcs_api_secret', ''),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'verify_ttl_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'grace_period_days' => ['required', 'integer', 'min:0', 'max:90'],
            'license_key_prefix' => ['required', 'string', 'max:10'],
            'api_enabled' => ['boolean'],
            'whmcs_enabled' => ['boolean'],
            'whmcs_url' => ['nullable', 'url'],
            'whmcs_api_identifier' => ['nullable', 'string', 'max:255'],
            'whmcs_api_secret' => ['nullable', 'string', 'max:255'],
        ]);

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        return back()->with('success', 'Settings updated successfully.');
    }

    public function regenerateApiKey(): RedirectResponse
    {
        $newKey = bin2hex(random_bytes(32));
        Setting::set('api_key', $newKey);

        return back()->with('success', 'API key regenerated successfully.');
    }
}