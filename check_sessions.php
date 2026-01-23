<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Launcher\GameSession;

echo "=== Active Sessions Check ===\n\n";

$sessions = GameSession::where('status', 'active')->get();

if ($sessions->isEmpty()) {
    echo "No active sessions found.\n";
} else {
    foreach ($sessions as $s) {
        $diff = now()->diffInSeconds($s->last_heartbeat);
        $timeout = $diff > 60 ? "EXPIRED!" : "OK";
        
        echo sprintf(
            "ID: %d | Session: %s | Last HB: %s | Diff: %ds | Status: %s\n",
            $s->id,
            substr($s->session_id, 0, 8) . '...',
            $s->last_heartbeat->format('H:i:s'),
            $diff,
            $timeout
        );
    }
    
    echo "\n--- Summary ---\n";
    echo "Total Active: " . $sessions->count() . "\n";
    echo "Should be cleaned: " . $sessions->filter(fn($s) => now()->diffInSeconds($s->last_heartbeat) > 60)->count() . "\n";
}
