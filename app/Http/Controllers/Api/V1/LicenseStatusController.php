<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LicenseStatusController extends Controller
{
    /**
     * Lightweight status check for client polling.
     *
     * This endpoint does NOT require fingerprint/device verification.
     * It only returns the current license status so clients can detect
     * status changes (suspend/terminate/expire) without waiting for
     * the next full verify cycle.
     *
     * Rate limited to prevent abuse.
     */
    public function status(Request $request, string $key): JsonResponse
    {
        $license = License::where('license_key', $key)->first();

        if (!$license) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'License not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'data' => [
                'license_key' => $license->license_key,
                'status' => $license->status,
                'expires_at' => $license->expires_at?->toDateTimeString(),
                'current_activations' => $license->current_activations,
                'max_activations' => $license->max_activations,
                'server_time' => now()->toDateTimeString(),
            ],
        ]);
    }
}
