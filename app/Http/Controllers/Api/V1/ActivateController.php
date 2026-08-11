<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\License\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ActivateController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    /**
     * Activate a license for a device.
     */
    public function activate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'license_key' => ['required', 'string'],
                'fingerprint' => ['required', 'string', 'max:128'],
                'platform' => ['required', 'in:desktop,hosting,server,android'],
                'device_info' => ['nullable', 'array'],
                'domain' => ['nullable', 'string', 'max:255'],
                'ip_address' => ['nullable', 'ip'],
            ]);

            $result = $this->licenseService->activate(
                licenseKey: $validated['license_key'],
                fingerprint: $validated['fingerprint'],
                platform: $validated['platform'],
                deviceInfo: $validated['device_info'] ?? [],
                domain: $validated['domain'] ?? null,
                ipAddress: $validated['ip_address'] ?? null,
            );

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'License activated successfully',
                'data' => [
                    'license_key' => $result['license']->license_key,
                    'token' => $result['token'],
                    'expires_in' => $result['expires_in'],
                    'expires_at' => $result['expires_at'],
                    'server_time' => now()->toDateTimeString(),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'code' => 422,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'code' => $e->getCode() ?: 500,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
    }
}