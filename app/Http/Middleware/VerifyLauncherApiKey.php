<?php

namespace App\Http\Middleware;

use App\Models\Launcher\ServerSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyLauncherApiKey
{
    /**
     * Handle an incoming request.
     * Uses HMAC + Date verification
     */
    public function handle(Request $request, Closure $next): Response
    {
        $clientKey = $request->header('X-API-Key') ?? $request->input('api_key');

        if (!$clientKey) {
            return response()->json([
                'success' => false,
                'error' => 'API key required',
                'code' => 'API_KEY_MISSING'
            ], 401);
        }

        // Get shared secret from database
        $secret = ServerSetting::getValue('launcher_secret');

        if (!$secret) {
            return response()->json([
                'success' => false,
                'error' => 'Server not configured',
                'code' => 'SERVER_ERROR'
            ], 500);
        }

        // Generate expected key: HMAC(secret, date)
        // Check today, yesterday, and tomorrow (for timezone edge cases)
        $validKeys = [
            hash_hmac('sha256', date('Y-m-d'), $secret),
            hash_hmac('sha256', date('Y-m-d', strtotime('-1 day')), $secret),
            hash_hmac('sha256', date('Y-m-d', strtotime('+1 day')), $secret),
        ];

        if (!in_array($clientKey, $validKeys, true)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or expired API key',
                'code' => 'API_KEY_INVALID'
            ], 401);
        }

        return $next($request);
    }
}
