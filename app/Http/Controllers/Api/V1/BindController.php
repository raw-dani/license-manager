<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\License\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BindController extends Controller
{
    public function __construct(
        protected LicenseService $licenseService
    ) {}

    public function bind(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'license_key' => ['required', 'string'],
                'install_id' => ['required', 'string', 'max:128'],
                'transfer_token' => ['nullable', 'string', 'max:128'],
                'platform' => ['required', 'in:desktop,hosting,server,android'],
                'fingerprint' => ['required', 'string', 'max:128'],
                'domain' => ['nullable', 'string', 'max:255'],
                'hostname' => ['nullable', 'string', 'max:255'],
                'device_info' => ['nullable', 'array'],
            ]);

            $deviceInfo = array_merge($validated['device_info'] ?? [], [
                'platform' => $validated['platform'],
                'fingerprint' => $validated['fingerprint'],
                'domain' => $validated['domain'] ?? null,
                'hostname' => $validated['hostname'] ?? null,
            ]);

            $result = $this->licenseService->bind(
                licenseKey: $validated['license_key'],
                installId: $validated['install_id'],
                transferToken: $validated['transfer_token'] ?? null,
                deviceInfo: $deviceInfo,
            );

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'License bound to this installation',
                'data' => $result,
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
