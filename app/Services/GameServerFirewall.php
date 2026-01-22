<?php

namespace App\Services;

class GameServerFirewall
{
    private $whitelistFile = 'C:/RohanServer/whitelist_ips.txt';
    private $firewallScript = 'C:/RohanServer/update_firewall.ps1';
    
    /**
     * Add IP to whitelist after successful login
     */
    public function allowIP(string $ip, int $userId, int $ttl = 3600): bool
    {
        try {
            // Add to whitelist file
            $entry = [
                'ip' => $ip,
                'user_id' => $userId,
                'expires_at' => time() + $ttl,
                'added_at' => time()
            ];
            
            $whitelist = $this->getWhitelist();
            $whitelist[$ip] = $entry;
            
            // Save whitelist
            file_put_contents(
                $this->whitelistFile,
                json_encode($whitelist, JSON_PRETTY_PRINT)
            );
            
            // Update Windows Firewall
            $this->updateFirewall($ip, 'allow');
            
            \Log::info("IP whitelisted for game server", [
                'ip' => $ip,
                'user_id' => $userId
            ]);
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to whitelist IP", [
                'ip' => $ip,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Remove IP from whitelist
     */
    public function removeIP(string $ip): bool
    {
        try {
            $whitelist = $this->getWhitelist();
            unset($whitelist[$ip]);
            
            file_put_contents(
                $this->whitelistFile,
                json_encode($whitelist, JSON_PRETTY_PRINT)
            );
            
            // Remove from firewall (optional - or let it expire)
            // $this->updateFirewall($ip, 'remove');
            
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Clean expired IPs
     */
    public function cleanExpired(): int
    {
        $whitelist = $this->getWhitelist();
        $removed = 0;
        $now = time();
        
        foreach ($whitelist as $ip => $entry) {
            if ($entry['expires_at'] < $now) {
                unset($whitelist[$ip]);
                $removed++;
            }
        }
        
        if ($removed > 0) {
            file_put_contents(
                $this->whitelistFile,
                json_encode($whitelist, JSON_PRETTY_PRINT)
            );
        }
        
        return $removed;
    }
    
    /**
     * Get current whitelist
     */
    private function getWhitelist(): array
    {
        if (!file_exists($this->whitelistFile)) {
            return [];
        }
        
        $content = file_get_contents($this->whitelistFile);
        return json_decode($content, true) ?: [];
    }
    
    /**
     * Update Windows Firewall via PowerShell
     */
    private function updateFirewall(string $ip, string $action): void
    {
        // Create PowerShell script
        $script = "
            # Add IP to firewall whitelist for port 22100
            `$ruleName = 'Rohan_Allow_' + '{$ip}'.Replace('.', '_')
            
            if ('{$action}' -eq 'allow') {
                # Remove old rule if exists
                Remove-NetFirewallRule -DisplayName `$ruleName -ErrorAction SilentlyContinue
                
                # Add new rule
                New-NetFirewallRule -DisplayName `$ruleName ``
                    -Direction Inbound ``
                    -Protocol TCP ``
                    -LocalPort 22100 ``
                    -RemoteAddress {$ip} ``
                    -Action Allow ``
                    -Enabled True
                    
                Write-Host \"✅ Allowed {$ip} for port 22100\"
            } else {
                Remove-NetFirewallRule -DisplayName `$ruleName -ErrorAction SilentlyContinue
                Write-Host \"❌ Removed {$ip} from whitelist\"
            }
        ";
        
        file_put_contents($this->firewallScript, $script);
        
        // Execute PowerShell script
        $command = "powershell.exe -ExecutionPolicy Bypass -File \"{$this->firewallScript}\"";
        exec($command . " 2>&1", $output, $returnCode);
        
        \Log::debug("Firewall update", [
            'ip' => $ip,
            'action' => $action,
            'output' => implode("\n", $output)
        ]);
    }
    
    /**
     * Get whitelist statistics
     */
    public function getStats(): array
    {
        $whitelist = $this->getWhitelist();
        $now = time();
        
        $active = 0;
        $expired = 0;
        
        foreach ($whitelist as $entry) {
            if ($entry['expires_at'] > $now) {
                $active++;
            } else {
                $expired++;
            }
        }
        
        return [
            'total' => count($whitelist),
            'active' => $active,
            'expired' => $expired
        ];
    }
}
