<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DisableCloudflareCompression
{
    /**
     * Disable Cloudflare gzip compression for legacy client compatibility
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Force identity encoding (no compression)
        // This prevents Cloudflare from gzipping responses
        $response->header('Content-Encoding', 'identity');
        
        // Remove any existing compression headers
        $response->headers->remove('Transfer-Encoding');
        
        return $response;
    }
}
