<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PingController extends Controller
{
    /**
     * Health check endpoint.
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'code' => 200,
            'message' => 'pong',
            'data' => [
                'server_time' => now()->toDateTimeString(),
                'version' => config('app.version', '1.0.0'),
            ],
        ]);
    }
}