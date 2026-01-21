<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Helpers\CloudflareDebug;
use Illuminate\Support\Facades\Log;

class CloudflareDebugger
{
    /**
     * Handle an incoming request and log Cloudflare details
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Log request details
        $endpoint = $request->path();
        CloudflareDebug::logRequest($request, $endpoint);
        CloudflareDebug::logComparison($request, $endpoint);
        
        // Continue to next middleware/controller
        $response = $next($request);
        
        // Log response status
        $this->logResponse($request, $response, $endpoint);
        
        return $response;
    }
    
    /**
     * Log response details
     */
    private function logResponse($request, $response, $endpoint)
    {
        $logData = [
            'timestamp' => now()->toDateTimeString(),
            'endpoint' => $endpoint,
            'status' => $response->status(),
            'cf_ray' => $request->header('CF-Ray', 'DIRECT'),
            'response_size' => strlen($response->getContent()),
        ];
        
        $logFile = storage_path('logs/cf_response.log');
        file_put_contents(
            $logFile,
            json_encode($logData, JSON_PRETTY_PRINT) . PHP_EOL,
            FILE_APPEND
        );
    }
}
