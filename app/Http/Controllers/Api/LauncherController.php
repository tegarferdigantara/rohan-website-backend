<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Launcher\GameSession;
use App\Models\Launcher\IpRule;
use App\Models\Launcher\ServerSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LauncherController extends Controller
{
    private const DEFAULT_MAX_CLIENTS = 4;
    private const DEFAULT_SESSION_TIMEOUT = 60;

    public function __construct()
    {
        try {
            $this->cleanupExpiredSessions();
        } catch (\Exception $e) {
            \Log::warning('LauncherController: Failed to cleanup expired sessions - ' . $e->getMessage());
        }
    }

    /**
     * Request permission to launch game
     * Returns session_id if allowed, error if max clients reached
     */
    public function requestLaunch(Request $request): JsonResponse
    {
        $ip = $this->getClientIP($request);
        $hwid = $request->input('hwid');
        $clientHash = $request->input('client_hash');

        // Check if IP is blacklisted
        $isBlacklisted = IpRule::forIp($ip)->blacklist()->exists();
        if ($isBlacklisted) {
            return response()->json([
                'success' => false,
                'error' => 'Access denied',
                'code' => 'IP_BLOCKED'
            ], 403);
        }

        // HWID validation (prevent device abuse)
        if (!empty($hwid) && !$this->validateHWID($hwid, $ip)) {
            return response()->json([
                'success' => false,
                'error' => 'Too many devices from this IP address',
                'code' => 'HWID_LIMIT_EXCEEDED'
            ], 403);
        }

        // Get max clients for this IP
        $maxClients = $this->getMaxClientsForIP($ip);

        // Count active sessions for this IP
        $activeCount = GameSession::forIp($ip)->active()->count();

        // Check if max clients reached
        if ($activeCount >= $maxClients) {
            return response()->json([
                'success' => false,
                'error' => "Maximum clients reached ({$activeCount}/{$maxClients})",
                'code' => 'MAX_CLIENTS_REACHED',
                'active_sessions' => $activeCount,
                'max_allowed' => $maxClients
            ], 429);
        }

        // Create new session
        $sessionId = Str::random(64);

        GameSession::create([
            'session_id' => $sessionId,
            'ip_address' => $ip,
            'hwid' => $hwid,
            'client_hash' => $clientHash,
            'launched_at' => now(),
            'last_heartbeat' => now(),
            'status' => 'active',
        ]);

        return response()->json([
            'success' => true,
            'session_id' => $sessionId,
            'active_sessions' => $activeCount + 1,
            'max_allowed' => $maxClients,
            'heartbeat_interval' => $this->getSessionTimeout() / 2
        ]);
    }

    /**
     * Keep session alive - must be called periodically
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id');

        if (!$sessionId) {
            return response()->json([
                'success' => false,
                'error' => 'Session ID required'
            ], 400);
        }

        $updated = GameSession::where('session_id', $sessionId)
            ->active()
            ->update(['last_heartbeat' => now()]);

        if ($updated === 0) {
            return response()->json([
                'success' => false,
                'error' => 'Session not found or expired',
                'code' => 'SESSION_INVALID'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Session updated'
        ]);
    }

    /**
     * Close a session when game exits
     */
    public function closeSession(Request $request): JsonResponse
    {
        $sessionId = $request->input('session_id');

        if (!$sessionId) {
            return response()->json([
                'success' => false,
                'error' => 'Session ID required'
            ], 400);
        }

        GameSession::where('session_id', $sessionId)
            ->update(['status' => 'closed']);

        return response()->json([
            'success' => true,
            'message' => 'Session closed'
        ]);
    }

    /**
     * Get server status
     */
    public function status(Request $request): JsonResponse
    {
        $ip = $this->getClientIP($request);

        // Get active sessions for this IP
        $activeCount = GameSession::forIp($ip)->active()->count();
        $maxClients = $this->getMaxClientsForIP($ip);

        // Check maintenance mode
        $maintenance = ServerSetting::getValue('maintenance_mode', '0') === '1';

        return response()->json([
            'success' => true,
            'ip' => $ip,
            'active_sessions' => $activeCount,
            'max_allowed' => $maxClients,
            'slots_available' => max(0, $maxClients - $activeCount),
            'maintenance' => $maintenance,
            'server_time' => now()->format('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get max clients allowed for an IP
     */
    private function getMaxClientsForIP(string $ip): int
    {
        // Check whitelist for custom limit
        $rule = IpRule::forIp($ip)
            ->whitelist()
            ->whereNotNull('max_clients')
            ->first();

        if ($rule && $rule->max_clients) {
            return (int) $rule->max_clients;
        }

        // Get default from database settings
        return (int) ServerSetting::getValue('max_clients_per_ip', self::DEFAULT_MAX_CLIENTS);
    }

    /**
     * Get session timeout in seconds
     */
    private function getSessionTimeout(): int
    {
        return (int) ServerSetting::getValue('session_timeout_seconds', self::DEFAULT_SESSION_TIMEOUT);
    }

    /**
     * Cleanup expired sessions (no heartbeat for SESSION_TIMEOUT seconds)
     */
    private function cleanupExpiredSessions(): void
    {
        $timeout = $this->getSessionTimeout();

        GameSession::active()
            ->where('last_heartbeat', '<', now()->subSeconds($timeout))
            ->update(['status' => 'closed']);
    }

    /**
     * Validate HWID to prevent device abuse
     * Limits number of unique devices per IP address
     */
    private function validateHWID(string $hwid, string $ip): bool
    {
        // Get max HWIDs per IP from settings (default: 3)
        $maxHwidsPerIP = (int) ServerSetting::getValue('max_hwids_per_ip', 3);

        // Check if this specific HWID already exists for this IP
        $hwidExists = GameSession::where('ip_address', $ip)
            ->where('hwid', $hwid)
            ->where('launched_at', '>', now()->subHours(24))
            ->exists();

        // If HWID already registered, allow it
        if ($hwidExists) {
            return true;
        }

        // Count unique HWIDs for this IP in last 24 hours
        $uniqueHwids = GameSession::where('ip_address', $ip)
            ->where('launched_at', '>', now()->subHours(24))
            ->distinct()
            ->pluck('hwid')
            ->filter() // Remove nulls
            ->count();

        // Allow if we haven't reached the limit
        $allowed = $uniqueHwids < $maxHwidsPerIP;

        if (!$allowed) {
            \Log::warning('HWID limit exceeded', [
                'ip' => $ip,
                'hwid' => substr($hwid, 0, 8) . '...',
                'unique_hwids' => $uniqueHwids,
                'max_allowed' => $maxHwidsPerIP
            ]);
        }

        return $allowed;
    }

    /**
     * Get Client IP Address
     */
    private function getClientIP(Request $request): string
    {
        if ($request->header('X-Forwarded-For')) {
            $ip = explode(',', $request->header('X-Forwarded-For'))[0];
        } elseif ($request->header('X-Real-IP')) {
            $ip = $request->header('X-Real-IP');
        } else {
            $ip = $request->ip();
        }

        return trim($ip);
    }
}
