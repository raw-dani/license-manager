<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\License\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DeactivateController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    /**
     * Deactivate a device from a license.
     */
    public function deactivate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'license_key' => ['required', 'string'],
                'fingerprint' => ['required', 'string', 'max:128'],
                'platform' => ['required', 'in:desktop,hosting,server,android'],
                'device_info' => ['nullable', 'array'],
            ]);

            $this->licenseService->deactivate(
                licenseKey: $validated['license_key'],
                fingerprint: $validated['fingerprint'],
                platform: $validated['platform'],
                deviceInfo: $validated['device_info'] ?? [],
            );

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'Deactivated',
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