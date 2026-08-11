<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

class EnsureApiKeyIsValid
{
    /**
     * Handle an incoming request.
     *
     * Verifies the X-API-Key header against the configured API key.
     */
    public function handle(Request $request, Closure $next)
    {
        $apiKey = Setting::get('api_key', '');

        if (empty($apiKey)) {
            return response()->json([
                'status' => 'error',
                'code' => 500,
                'message' => 'API key is not configured on the server',
            ], 500);
        }

        $requestKey = $request->header('X-API-Key');

        if (empty($requestKey)) {
            return response()->json([
                'status' => 'error',
                'code' => 401,
                'message' => 'Missing API key. Send via X-API-Key header or api_key parameter.',
            ], 401);
        }

        if (!hash_equals($apiKey, $requestKey)) {
            return response()->json([
                'status' => 'error',
                'code' => 403,
                'message' => 'Invalid API key',
            ], 403);
        }

        return $next($request);
    }
}