<?php

namespace App\Console\Commands;

use App\Services\GameServerFirewall;
use Illuminate\Console\Command;

class CleanupGameServerWhitelist extends Command
{
    protected $signature = 'gameserver:cleanup';
    protected $description = 'Clean up expired IP addresses from game server whitelist';

    public function handle()
    {
        $this->info('Cleaning up expired IPs from game server whitelist...');
        
        $firewall = new GameServerFirewall();
        $removed = $firewall->cleanExpired();
        
        $stats = $firewall->getStats();
        
        $this->info("✅ Removed $removed expired IP(s)");
        $this->info("📊 Stats: {$stats['active']} active, {$stats['total']} total");
        
        return Command::SUCCESS;
    }
}
