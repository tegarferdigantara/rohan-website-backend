# ============================================================================
# Install Rohan Firewall Sync sebagai Scheduled Task
# ============================================================================
# Jalankan script ini sebagai Administrator
# ============================================================================

$scriptPath = "C:\RohanServer\firewall_sync.ps1"
$taskName = "RohanFirewallSync"

# 1. Buat direktori jika belum ada
if (-not (Test-Path "C:\RohanServer")) {
    New-Item -ItemType Directory -Path "C:\RohanServer" -Force
    Write-Host "✅ Created C:\RohanServer directory" -ForegroundColor Green
}

# 2. Copy script ke lokasi yang benar
$sourceScript = Split-Path -Parent $MyInvocation.MyCommand.Path
$sourceFile = Join-Path $sourceScript "firewall_sync.ps1"

if (Test-Path $sourceFile) {
    Copy-Item $sourceFile $scriptPath -Force
    Write-Host "✅ Copied firewall_sync.ps1 to C:\RohanServer\" -ForegroundColor Green
} else {
    Write-Host "⚠️  Please copy firewall_sync.ps1 to C:\RohanServer\ manually" -ForegroundColor Yellow
}

# 3. Hapus task lama jika ada
$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existingTask) {
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
    Write-Host "✅ Removed old scheduled task" -ForegroundColor Green
}

# 4. Buat scheduled task baru
$action = New-ScheduledTaskAction -Execute "powershell.exe" `
    -Argument "-ExecutionPolicy Bypass -WindowStyle Hidden -File `"$scriptPath`""

$trigger = New-ScheduledTaskTrigger -AtStartup

$principal = New-ScheduledTaskPrincipal -UserId "SYSTEM" -RunLevel Highest

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RestartInterval (New-TimeSpan -Minutes 1) `
    -RestartCount 3 `
    -ExecutionTimeLimit (New-TimeSpan -Days 365)

Register-ScheduledTask -TaskName $taskName `
    -Action $action `
    -Trigger $trigger `
    -Principal $principal `
    -Settings $settings `
    -Description "Rohan Game Server Firewall Whitelist Sync - Reads game_sessions from database and updates Windows Firewall"

Write-Host "✅ Scheduled task '$taskName' created" -ForegroundColor Green

# 5. Start task sekarang
Start-ScheduledTask -TaskName $taskName
Write-Host "✅ Task started" -ForegroundColor Green

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Installation Complete!" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "📝 PENTING: Edit C:\RohanServer\firewall_sync.ps1" -ForegroundColor Yellow
Write-Host "   dan ganti konfigurasi database:" -ForegroundColor Yellow
Write-Host "   - SqlServer  = IP database server" -ForegroundColor White
Write-Host "   - SqlPassword = Password database" -ForegroundColor White
Write-Host ""
Write-Host "📊 Cek status:" -ForegroundColor Cyan
Write-Host "   Get-ScheduledTask -TaskName $taskName" -ForegroundColor White
Write-Host ""
Write-Host "📋 Cek log:" -ForegroundColor Cyan
Write-Host "   Get-Content C:\RohanServer\firewall_sync.log -Tail 20" -ForegroundColor White
Write-Host ""
Write-Host "🔥 Cek firewall rules:" -ForegroundColor Cyan
Write-Host "   Get-NetFirewallRule -DisplayName 'Rohan_*'" -ForegroundColor White
