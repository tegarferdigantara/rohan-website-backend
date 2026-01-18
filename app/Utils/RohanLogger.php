<?php

namespace App\Utils;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RohanLogger
{
    /**
     * Generate unique request ID for logging
     */
    public static function generateRequestId(): string
    {
        return substr(md5(uniqid(mt_rand(), true)), 0, 8);
    }

    /**
     * Log Rohan auth request
     */
    public static function logRequest(string $endpoint, Request $request, string $requestId, string $ip): void
    {
        $data = [
            'request_id' => $requestId,
            'endpoint' => $endpoint,
            'ip' => $ip,
            'method' => $request->method(),
            'user_agent' => $request->header('User-Agent'),
            'params' => array_merge(
                $request->query(),
                collect($request->post())->except('passwd')->toArray()
            ),
        ];

        Log::channel('rohan')->info("[$requestId] Request: $endpoint", $data);
    }

    /**
     * Log Rohan auth response
     */
    public static function logResponse(string $endpoint, string $requestId, $response, float $startTime): void
    {
        $elapsed = round((microtime(true) - $startTime) * 1000, 2);
        
        Log::channel('rohan')->info("[$requestId] Response: $endpoint", [
            'request_id' => $requestId,
            'endpoint' => $endpoint,
            'response' => is_string($response) ? $response : json_encode($response),
            'elapsed_ms' => $elapsed,
        ]);
    }

    /**
     * Log error
     */
    public static function logError(string $endpoint, string $requestId, \Exception $e): void
    {
        Log::channel('rohan')->error("[$requestId] Error: $endpoint", [
            'request_id' => $requestId,
            'endpoint' => $endpoint,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);
    }

    /**
     * Log debug information
     */
    public static function logDebug(string $requestId, string $message, array $context = []): void
    {
        Log::channel('rohan')->debug("[$requestId] $message", array_merge(['request_id' => $requestId], $context));
    }

    /**
     * Log info message
     */
    public static function logInfo(string $requestId, string $message, array $context = []): void
    {
        Log::channel('rohan')->info("[$requestId] $message", array_merge(['request_id' => $requestId], $context));
    }

    /**
     * Log warning message
     */
    public static function logWarning(string $requestId, string $message, array $context = []): void
    {
        Log::channel('rohan')->warning("[$requestId] $message", array_merge(['request_id' => $requestId], $context));
    }
}
