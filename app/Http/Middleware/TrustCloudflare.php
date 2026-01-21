<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustCloudflare
{
    /**
     * Handle an incoming request and trust Cloudflare headers
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Trust Cloudflare Connecting IP
        if ($request->hasHeader('CF-Connecting-IP')) {
            $realIp = $request->header('CF-Connecting-IP');
            
            // Set the real client IP
            $request->server->set('REMOTE_ADDR', $realIp);
            
            // Also override getClientIp() result
            $request->setTrustedProxies(
                [$request->server->get('REMOTE_ADDR')],
                Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
            );
        }
        
        // Trust Cloudflare Visitor for HTTPS detection
        if ($request->hasHeader('CF-Visitor')) {
            $visitor = json_decode($request->header('CF-Visitor'), true);
            
            if (isset($visitor['scheme'])) {
                if ($visitor['scheme'] === 'https') {
                    // Set HTTPS indicators
                    $request->server->set('HTTPS', 'on');
                    $request->server->set('SERVER_PORT', 443);
                } else {
                    // Ensure HTTP
                    $request->server->set('HTTPS', 'off');
                    $request->server->set('SERVER_PORT', 80);
                }
            }
        }
        
        // Normalize host header if needed
        // Some backends check HTTP_HOST for routing
        if ($request->getHost() === 'emulsis-realm.my.id') {
            // Rewrite to auth subdomain to match backend expectation
            $request->headers->set('Host', 'auth.emulsis-realm.my.id');
        }
        
        return $next($request);
    }
}
