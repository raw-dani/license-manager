<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivationLog;
use App\Models\License;
use App\Services\License\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AdminApiController extends Controller
{
    public function __construct(
        protected WebhookService $webhookService
    ) {
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

        $webhookSent = $this->webhookService->notifySuspension($license);

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'License suspended successfully',
            'data' => [
                'license_key' => $license->license_key,
                'status' => $license->status,
                'suspended_at' => $license->suspended_at?->toDateTimeString(),
                'webhook_sent' => $webhookSent,
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

        $webhookSent = $this->webhookService->notifyReactivation($license);

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'License unsuspended successfully',
            'data' => [
                'license_key' => $license->license_key,
                'status' => $license->status,
                'suspended_at' => null,
                'webhook_sent' => $webhookSent,
            ],
        ]);
    }

    public function notify(Request $request, string $key): JsonResponse
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

        if (empty($license->webhook_url) || empty($license->webhook_secret)) {
            return response()->json([
                'status' => 'error',
                'code' => 422,
                'message' => 'Webhook URL or secret not configured for this license',
            ], 422);
        }

        $event = $request->input('event', 'license.' . $license->status);

        $webhookSent = match ($license->status) {
            'suspended' => $this->webhookService->notifySuspension($license),
            'active' => $this->webhookService->notifyReactivation($license),
            default => false,
        };

        return response()->json([
            'status' => $webhookSent ? 'success' : 'error',
            'code' => $webhookSent ? 200 : 500,
            'message' => $webhookSent ? 'Webhook notification sent' : 'Failed to send webhook notification',
            'data' => [
                'license_key' => $license->license_key,
                'event' => $event,
                'webhook_sent' => $webhookSent,
            ],
        ]);
    }

    public function transferToken(Request $request, string $key): JsonResponse
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

        $ttlHours = (int) $request->input('ttl_hours', 24);

        try {
            $token = app(\App\Services\License\LicenseService::class)->generateTransferToken($key, $ttlHours);

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'message' => 'Transfer token generated',
                'data' => [
                    'license_key' => $key,
                    'transfer_token' => $token,
                    'expires_in_hours' => $ttlHours,
                    'expires_at' => now()->addHours($ttlHours)->toDateTimeString(),
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => 'error',
                'code' => $e->getCode() ?: 500,
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 500);
        }
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
