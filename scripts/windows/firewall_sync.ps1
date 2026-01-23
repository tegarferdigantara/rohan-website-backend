# ============================================================================
# Rohan Game Server - Firewall Whitelist Sync
# ============================================================================
# Script ini berjalan di Windows Game Server dan membaca tabel game_sessions
# dari database RohanManage untuk whitelist IP yang aktif.
#
# Setup:
# 1. Copy script ini ke C:\RohanServer\firewall_sync.ps1
# 2. Edit konfigurasi database di bawah
# 3. Jalankan sebagai Administrator atau setup sebagai Scheduled Task
# ============================================================================

# ==================== KONFIGURASI ====================
$Config = @{
    # Database Connection
    SqlServer = "127.0.0.1"          # IP SQL Server (localhost jika di server yang sama)
    SqlDatabase = "RohanManage"
    SqlUsername = "sa"
    SqlPassword = "YourPasswordHere"  # Ganti dengan password yang benar
    
    # Firewall Settings
    GamePort = 22100
    RulePrefix = "Rohan_Allow_"
    
    # Polling Settings
    PollIntervalSeconds = 5           # Cek setiap 5 detik
    
    # Logging
    LogFile = "C:\RohanServer\firewall_sync.log"
    MaxLogSizeMB = 10
}

# ==================== FUNCTIONS ====================

function Write-Log {
    param([string]$Message, [string]$Level = "INFO")
    
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    $logEntry = "[$timestamp] [$Level] $Message"
    
    # Console output with colors
    switch ($Level) {
        "ERROR" { Write-Host $logEntry -ForegroundColor Red }
        "WARN"  { Write-Host $logEntry -ForegroundColor Yellow }
        "SUCCESS" { Write-Host $logEntry -ForegroundColor Green }
        default { Write-Host $logEntry }
    }
    
    # File output
    Add-Content -Path $Config.LogFile -Value $logEntry -ErrorAction SilentlyContinue
    
    # Rotate log if too large
    $logFile = Get-Item $Config.LogFile -ErrorAction SilentlyContinue
    if ($logFile -and ($logFile.Length / 1MB) -gt $Config.MaxLogSizeMB) {
        $backupName = $Config.LogFile -replace '\.log$', "_$(Get-Date -Format 'yyyyMMdd_HHmmss').log"
        Move-Item $Config.LogFile $backupName -Force
    }
}

function Get-ActiveSessionsFromDB {
    <#
    .SYNOPSIS
    Query database untuk mendapatkan IP dengan session aktif
    #>
    
    try {
        $connectionString = "Server=$($Config.SqlServer);Database=$($Config.SqlDatabase);User Id=$($Config.SqlUsername);Password=$($Config.SqlPassword);TrustServerCertificate=True;"
        
        $query = @"
            SELECT DISTINCT ip_address 
            FROM game_sessions 
            WHERE status = 'active' 
            AND last_heartbeat > DATEADD(SECOND, -120, GETDATE())
"@
        
        $connection = New-Object System.Data.SqlClient.SqlConnection($connectionString)
        $connection.Open()
        
        $command = New-Object System.Data.SqlClient.SqlCommand($query, $connection)
        $reader = $command.ExecuteReader()
        
        $activeIPs = @()
        while ($reader.Read()) {
            $ip = $reader["ip_address"].ToString().Trim()
            if ($ip -and $ip -ne "") {
                $activeIPs += $ip
            }
        }
        
        $reader.Close()
        $connection.Close()
        
        return $activeIPs
    }
    catch {
        Write-Log "Database error: $($_.Exception.Message)" "ERROR"
        return @()
    }
}

function Get-CurrentFirewallRules {
    <#
    .SYNOPSIS
    Dapatkan semua IP yang sudah di-whitelist di firewall
    #>
    
    $rules = Get-NetFirewallRule -DisplayName "$($Config.RulePrefix)*" -ErrorAction SilentlyContinue
    
    $whitelistedIPs = @()
    foreach ($rule in $rules) {
        # Extract IP from rule name: Rohan_Allow_192_168_1_100 -> 192.168.1.100
        $ipPart = $rule.DisplayName -replace [regex]::Escape($Config.RulePrefix), ""
        $ip = $ipPart -replace "_", "."
        $whitelistedIPs += $ip
    }
    
    return $whitelistedIPs
}

function Add-FirewallWhitelist {
    param([string]$IP)
    
    $ruleName = "$($Config.RulePrefix)$($IP -replace '\.', '_')"
    
    # Check if rule already exists
    $existing = Get-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
    if ($existing) {
        return $false  # Already exists
    }
    
    try {
        New-NetFirewallRule -DisplayName $ruleName `
            -Direction Inbound `
            -Protocol TCP `
            -LocalPort $Config.GamePort `
            -RemoteAddress $IP `
            -Action Allow `
            -Enabled True `
            -Profile Any | Out-Null
            
        Write-Log "✅ WHITELISTED: $IP for port $($Config.GamePort)" "SUCCESS"
        return $true
    }
    catch {
        Write-Log "Failed to whitelist $IP : $($_.Exception.Message)" "ERROR"
        return $false
    }
}

function Remove-FirewallWhitelist {
    param([string]$IP)
    
    $ruleName = "$($Config.RulePrefix)$($IP -replace '\.', '_')"
    
    try {
        Remove-NetFirewallRule -DisplayName $ruleName -ErrorAction SilentlyContinue
        Write-Log "🚫 REMOVED: $IP from whitelist" "WARN"
        return $true
    }
    catch {
        return $false
    }
}

function Sync-FirewallWithDatabase {
    <#
    .SYNOPSIS
    Main sync function - compare DB sessions with firewall rules
    #>
    
    # Get active sessions from database
    $activeIPs = Get-ActiveSessionsFromDB
    
    # Get current firewall whitelist
    $firewallIPs = Get-CurrentFirewallRules
    
    # Add new IPs (in DB but not in firewall)
    $toAdd = $activeIPs | Where-Object { $_ -notin $firewallIPs }
    foreach ($ip in $toAdd) {
        Add-FirewallWhitelist -IP $ip
    }
    
    # Remove expired IPs (in firewall but not in DB)
    $toRemove = $firewallIPs | Where-Object { $_ -notin $activeIPs }
    foreach ($ip in $toRemove) {
        Remove-FirewallWhitelist -IP $ip
    }
    
    # Return stats
    return @{
        ActiveInDB = $activeIPs.Count
        InFirewall = $firewallIPs.Count
        Added = $toAdd.Count
        Removed = $toRemove.Count
    }
}

function Setup-DefaultBlockRule {
    <#
    .SYNOPSIS
    Setup default BLOCK rule for game port (hanya dijalankan sekali)
    #>
    
    $blockRuleName = "Rohan_Block_All_22100"
    $existing = Get-NetFirewallRule -DisplayName $blockRuleName -ErrorAction SilentlyContinue
    
    if (-not $existing) {
        Write-Log "Setting up default BLOCK rule for port $($Config.GamePort)..." "INFO"
        
        New-NetFirewallRule -DisplayName $blockRuleName `
            -Direction Inbound `
            -Protocol TCP `
            -LocalPort $Config.GamePort `
            -Action Block `
            -Enabled True `
            -Profile Any | Out-Null
            
        Write-Log "✅ Default BLOCK rule created" "SUCCESS"
    }
}

# ==================== MAIN LOOP ====================

# Ensure log directory exists
$logDir = Split-Path $Config.LogFile -Parent
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
}

Write-Log "========================================" "INFO"
Write-Log "Rohan Firewall Sync Starting..." "INFO"
Write-Log "Database: $($Config.SqlServer)/$($Config.SqlDatabase)" "INFO"
Write-Log "Game Port: $($Config.GamePort)" "INFO"
Write-Log "Poll Interval: $($Config.PollIntervalSeconds) seconds" "INFO"
Write-Log "========================================" "INFO"

# Setup default block rule
Setup-DefaultBlockRule

# Main polling loop
$lastStats = $null
while ($true) {
    try {
        $stats = Sync-FirewallWithDatabase
        
        # Only log if something changed or every 60 iterations (5 minutes)
        if ($stats.Added -gt 0 -or $stats.Removed -gt 0 -or $null -eq $lastStats) {
            Write-Log "📊 Active: $($stats.ActiveInDB) | Firewall: $($stats.InFirewall) | +$($stats.Added) -$($stats.Removed)" "INFO"
        }
        
        $lastStats = $stats
    }
    catch {
        Write-Log "Sync error: $($_.Exception.Message)" "ERROR"
    }
    
    Start-Sleep -Seconds $Config.PollIntervalSeconds
}
