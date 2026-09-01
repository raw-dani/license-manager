<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ValidateLicenseCallback
{
    public function handle(Request $request, Closure $next)
    {
        $payload = $request->all();

        if (empty($payload['signature'])) {
            Log::warning('License callback missing signature', [
                'ip' => $request->ip(),
                'payload' => $payload,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Missing signature',
            ], 401);
        }

        $secret = config('license.webhook_secret');

        if (empty($secret)) {
            Log::error('License webhook secret not configured');

            return response()->json([
                'status' => 'error',
                'message' => 'Webhook secret not configured',
            ], 500);
        }

        $providedSignature = $payload['signature'];

        $dataToVerify = $payload;
        unset($dataToVerify['signature']);

        $json = json_encode($dataToVerify, JSON_UNESCAPED_SLASHES);
        $expectedSignature = hash_hmac('sha256', $json, $secret);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            Log::warning('License callback invalid signature', [
                'ip' => $request->ip(),
                'license_key' => $payload['license_key'] ?? null,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Invalid signature',
            ], 403);
        }

        return $next($request);
    }
}
