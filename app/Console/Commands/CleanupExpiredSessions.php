<?php

namespace App\Console\Commands;

use App\Models\Launcher\GameSession;
use App\Models\Launcher\ServerSetting;
use Illuminate\Console\Command;

class CleanupExpiredSessions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'launcher:cleanup-sessions';

    /**
     * The console command description.
     */
    protected $description = 'Cleanup expired launcher sessions (no heartbeat)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $timeout = (int) ServerSetting::getValue('session_timeout_seconds', 60);
        
        $expired = GameSession::active()
            ->where('last_heartbeat', '<', now()->subSeconds($timeout))
            ->get();
        
        if ($expired->isEmpty()) {
            $this->info('No expired sessions to clean.');
            return 0;
        }
        
        $count = $expired->count();
        
        // Mark as closed
        GameSession::active()
            ->where('last_heartbeat', '<', now()->subSeconds($timeout))
            ->update(['status' => 'closed']);
        
        $this->info("Cleaned up {$count} expired session(s).");
        
        // Log details
        foreach ($expired as $session) {
            $diff = now()->diffInSeconds($session->last_heartbeat);
            $this->line("  - Session {$session->session_id} (idle for {$diff}s)");
        }
        
        return 0;
    }
}
