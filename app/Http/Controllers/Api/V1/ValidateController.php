<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\License;
use App\Models\LicenseActivation;
use App\Services\Platform\AndroidFingerprint;
use App\Services\Platform\DesktopFingerprint;
use App\Services\Platform\HostingFingerprint;
use App\Services\Platform\ServerFingerprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ValidateController extends Controller
{
    /**
     * Validate hardware fingerprint/domain/IP for a license.
     */
    public function validate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'license_key' => ['required', 'string'],
                'fingerprint' => ['required', 'string', 'max:128'],
                'platform' => ['required', 'in:desktop,hosting,server,android'],
                'domain' => ['nullable', 'string', 'max:255'],
                'ip_address' => ['nullable', 'ip'],
            ]);

            $license = License::where('license_key', $validated['license_key'])->first();

            if (!$license) {
                return response()->json([
                    'status' => 'error',
                    'code' => 404,
                    'message' => 'License not found',
                ], 404);
            }

            if ($license->status !== 'active') {
                return response()->json([
                    'status' => 'error',
                    'code' => 403,
                    'message' => 'License is ' . $license->status,
                ], 403);
            }

            $activation = LicenseActivation::where('license_id', $license->id)
                ->where('fingerprint', $validated['fingerprint'])
                ->first();

            if (!$activation) {
                return response()->json([
                    'status' => 'error',
                    'code' => 403,
                    'message' => 'Device not activated',
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'Device validated',
                'data' => [
                    'license_key' => $license->license_key,
                    'valid' => true,
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
        }
    }
}