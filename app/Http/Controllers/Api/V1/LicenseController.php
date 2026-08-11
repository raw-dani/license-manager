<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Http\JsonResponse;

class LicenseController extends Controller
{
    /**
     * Get license details by license key.
     */
    public function show(string $key): JsonResponse
    {
        $license = License::with('product')
            ->where('license_key', $key)
            ->first();

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
                'product' => $license->product ? [
                    'name' => $license->product->name,
                    'platform' => $license->product->platform,
                    'version' => $license->product->version,
                ] : null,
                'max_activations' => $license->max_activations,
                'current_activations' => $license->current_activations,
                'expires_at' => $license->expires_at?->toDateTimeString(),
                'activated_at' => $license->activated_at?->toDateTimeString(),
                'last_verified_at' => $license->last_verified_at?->toDateTimeString(),
                'server_time' => now()->toDateTimeString(),
            ],
        ]);
    }
}