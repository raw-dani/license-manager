<?php

namespace App\Http\Controllers\Api\License;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LicenseCallbackController extends Controller
{
    public function __construct()
    {
        $this->middleware('license.callback');
    }

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        $event = $payload['event'] ?? 'unknown';
        $licenseKey = $payload['license_key'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$licenseKey) {
            return response()->json([
                'status' => 'error',
                'message' => 'Missing license_key',
            ], 422);
        }

        Log::info('License callback received', [
            'event' => $event,
            'license_key' => $licenseKey,
            'status' => $status,
        ]);

        switch ($event) {
            case 'license.suspended':
                $this->handleSuspension($licenseKey, $payload);
                break;

            case 'license.reactivated':
                $this->handleReactivation($licenseKey, $payload);
                break;

            default:
                Log::warning('Unknown license event', ['event' => $event]);
                return response()->json([
                    'status' => 'error',
                    'message' => 'Unknown event type',
                ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Callback processed',
        ]);
    }

    private function handleSuspension(string $licenseKey, array $payload): void
    {
        Cache::forget('license_verify_' . md5($licenseKey));

        Cache::put(
            'license_status_' . md5($licenseKey),
            [
                'status' => 'suspended',
                'suspended_at' => $payload['suspended_at'] ?? now()->toDateTimeString(),
                'cached_at' => now()->toDateTimeString(),
            ],
            now()->addDays(7)
        );

        Log::info('License suspended via webhook', [
            'license_key' => $licenseKey,
            'suspended_at' => $payload['suspended_at'] ?? null,
        ]);
    }

    private function handleReactivation(string $licenseKey, array $payload): void
    {
        Cache::forget('license_verify_' . md5($licenseKey));

        Cache::put(
            'license_status_' . md5($licenseKey),
            [
                'status' => 'active',
                'cached_at' => now()->toDateTimeString(),
            ],
            now()->addDays(7)
        );

        Log::info('License reactivated via webhook', [
            'license_key' => $licenseKey,
        ]);
    }
}
