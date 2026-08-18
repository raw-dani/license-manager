<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivationLog;
use App\Models\License;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdminApiController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function suspend(Request $request, string $key): JsonResponse
    {
        $this->ensureAdmin($request);

        $license = License::where('license_key', $key)->first();

        if (!$license) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'License not found',
            ], 404);
        }

        if ($license->status === 'suspended') {
            return response()->json([
                'status' => 'error',
                'code' => 409,
                'message' => 'License is already suspended',
            ], 409);
        }

        $license->update([
            'status' => 'suspended',
            'suspended_at' => now(),
        ]);

        ActivationLog::create([
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'action' => 'suspend',
            'notes' => 'License suspended remotely via API by ' . ($request->user()?->email ?? 'unknown'),
        ]);

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'License suspended successfully',
            'data' => [
                'license_key' => $license->license_key,
                'status' => $license->status,
                'suspended_at' => $license->suspended_at?->toDateTimeString(),
            ],
        ]);
    }

    public function unsuspend(Request $request, string $key): JsonResponse
    {
        $this->ensureAdmin($request);

        $license = License::where('license_key', $key)->first();

        if (!$license) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'License not found',
            ], 404);
        }

        if ($license->status !== 'suspended') {
            return response()->json([
                'status' => 'error',
                'code' => 409,
                'message' => 'License is not suspended',
            ], 409);
        }

        $license->update([
            'status' => 'active',
            'suspended_at' => null,
        ]);

        ActivationLog::create([
            'license_id' => $license->id,
            'license_key' => $license->license_key,
            'action' => 'reactivate',
            'notes' => 'License unsuspended remotely via API by ' . ($request->user()?->email ?? 'unknown'),
        ]);

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'License unsuspended successfully',
            'data' => [
                'license_key' => $license->license_key,
                'status' => $license->status,
                'suspended_at' => null,
            ],
        ]);
    }

    private function ensureAdmin(Request $request): void
    {
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['admin', 'super-admin'])) {
            throw ValidationException::withMessages([
                'auth' => ['Unauthorized. Admin role required.'],
            ]);
        }
    }
}
