<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class CloudflareDebug
{
    /**
     * Log detailed Cloudflare request information
     */
    public static function logRequest(Request $request, string $endpoint = 'unknown')
    {
        $logFile = storage_path('logs/cloudflare_debug.log');
        
        $debugData = [
            'timestamp' => now()->toDateTimeString(),
            'endpoint' => $endpoint,
            
            // Request Info
            'request' => [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            
            // Cloudflare Headers
            'cloudflare' => [
                'cf_ray' => $request->header('CF-Ray', 'NOT_VIA_CLOUDFLARE'),
                'cf_visitor' => $request->header('CF-Visitor'),
                'cf_connecting_ip' => $request->header('CF-Connecting-IP'),
                'cf_ipcountry' => $request->header('CF-IPCountry'),
                'cf_request_id' => $request->header('CF-Request-ID'),
            ],
            
            // SSL/Protocol Detection
            'ssl' => [
                'is_secure' => $request->secure(),
                'scheme' => $request->getScheme(),
                'x_forwarded_proto' => $request->header('X-Forwarded-Proto'),
                'x_forwarded_for' => $request->header('X-Forwarded-For'),
            ],
            
            // Request Data
            'data' => [
                'query' => $request->query(),
                'post' => $request->post(),
                'all' => $request->all(),
            ],
            
            // All Headers
            'headers' => $request->headers->all(),
        ];
        
        // Write to log
        $logEntry = str_repeat('=', 100) . PHP_EOL;
        $logEntry .= json_encode($debugData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        $logEntry .= str_repeat('=', 100) . PHP_EOL . PHP_EOL;
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        
        // Also log to Laravel log for easy viewing
        Log::channel('single')->info('Cloudflare Request Debug', [
            'endpoint' => $endpoint,
            'via_cf' => self::isViaCloudflare($request),
            'cf_ray' => $request->header('CF-Ray', 'DIRECT'),
            'real_ip' => self::getRealIp($request),
        ]);
        
        return $debugData;
    }
    
    /**
     * Check if request is via Cloudflare
     */
    public static function isViaCloudflare(Request $request): bool
    {
        return $request->hasHeader('CF-Ray');
    }
    
    /**
     * Get real client IP (considering Cloudflare)
     */
    public static function getRealIp(Request $request): string
    {
        if ($cfIp = $request->header('CF-Connecting-IP')) {
            return $cfIp;
        }
        
        if ($forwarded = $request->header('X-Forwarded-For')) {
            $ips = explode(',', $forwarded);
            return trim($ips[0]);
        }
        
        return $request->ip();
    }
    
    /**
     * Get protocol (HTTP/HTTPS) considering proxies
     */
    public static function getProtocol(Request $request): string
    {
        // Check CF-Visitor header
        if ($visitor = $request->header('CF-Visitor')) {
            $visitorData = json_decode($visitor, true);
            if (isset($visitorData['scheme'])) {
                return strtoupper($visitorData['scheme']);
            }
        }
        
        // Check X-Forwarded-Proto
        if ($proto = $request->header('X-Forwarded-Proto')) {
            return strtoupper($proto);
        }
        
        // Fallback to request scheme
        return $request->secure() ? 'HTTPS' : 'HTTP';
    }
    
    /**
     * Log comparison between proxied and direct
     */
    public static function logComparison(Request $request, string $endpoint)
    {
        $isProxied = self::isViaCloudflare($request);
        $protocol = self::getProtocol($request);
        $realIp = self::getRealIp($request);
        
        $comparison = [
            'timestamp' => now()->toDateTimeString(),
            'endpoint' => $endpoint,
            'connection_type' => $isProxied ? 'PROXIED (Cloudflare)' : 'DIRECT',
            'protocol' => $protocol,
            'real_ip' => $realIp,
            'server_ip' => $request->server('SERVER_ADDR'),
            'cf_ray' => $request->header('CF-Ray', 'N/A'),
        ];
        
        $logFile = storage_path('logs/cf_comparison.log');
        file_put_contents(
            $logFile, 
            json_encode($comparison, JSON_PRETTY_PRINT) . PHP_EOL . PHP_EOL,
            FILE_APPEND
        );
        
        return $comparison;
    }
}
