# Laravel Scheduler Setup - Windows Task Scheduler

## ✅ COMPLETED: Session Cleanup Auto-Fix

### Problem Found:
- **Expired sessions** (672 seconds / 11+ minutes idle)
- Still marked as `active` in database ❌
- Cleanup only runs when API requests come in

### Solution Implemented:

#### 1. **Created Cleanup Command** ✅
File: `app/Console/Commands/CleanupExpiredSessions.php`

```bash
php artisan launcher:cleanup-sessions
```

This command:
- Finds sessions with `last_heartbeat > 60 seconds ago`
- Marks them as `closed`
- Logs the cleanup

#### 2. **Added to Laravel Scheduler** ✅
File: `app/Console/Kernel.php`

```php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('launcher:cleanup-sessions')
             ->everyMinute()
             ->withoutOverlapping()
             ->runInBackground();
}
```

#### 3. **Test Results** ✅
```
Before: 2 active sessions (expired 11+ minutes ago)
After:  0 active sessions (cleaned successfully!)
```

---

## 🚀 Setup Windows Task Scheduler

### **Option 1: GUI Setup (Recommended for Laragon)**

1. **Open Task Scheduler:**
   - Press `Win + R`
   - Type: `taskschd.msc`
   - Press Enter

2. **Create Basic Task:**
   - Click "Create Basic Task..." (right panel)
   - Name: `Laravel Scheduler - Emulsis`
   - Description: `Runs Laravel scheduled tasks including session cleanup`

3. **Trigger:**
   - Select: "Daily"
   - Start: Today
   - Recur every: 1 days
   - Click Next

4. **Action:**
   - Select: "Start a program"
   - Program/script: `C:\laragon\bin\php\php-8.3.1-nts-Win32-vs16-x64\php.exe`
   - Add arguments: `artisan schedule:run`
   - Start in: `C:\laragon\www\emulsis-web`

5. **Advanced Settings (IMPORTANT!):**
   - After creating, right-click task → Properties
   - Go to **Triggers** tab → Edit trigger
   - Click "Repeat task every:" → Select **1 minute**
   - Duration: **Indefinitely**
   - Enabled: ✅ Checked

6. **General Tab:**
   - ✅ Run whether user is logged on or not
   - ✅ Run with highest privileges
   - Configure for: Windows 10/11

---

### **Option 2: PowerShell Setup (Auto)**

Run this as **Administrator**:

```powershell
$action = New-ScheduledTaskAction `
    -Execute 'C:\laragon\bin\php\php-8.3.1-nts-Win32-vs16-x64\php.exe' `
    -Argument 'artisan schedule:run' `
    -WorkingDirectory 'C:\laragon\www\emulsis-web'

$trigger = New-ScheduledTaskTrigger `
    -Once `
    -At (Get-Date) `
    -RepetitionInterval (New-TimeSpan -Minutes 1) `
    -RepetitionDuration ([TimeSpan]::MaxValue)

$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RunOnlyIfNetworkAvailable `
    -MultipleInstances IgnoreNew

Register-ScheduledTask `
    -TaskName "Laravel Scheduler - Emulsis" `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description "Runs Laravel scheduled tasks every minute" `
    -RunLevel Highest
```

---

### **Option 3: Batch File (Simple)**

File: `run-scheduler.bat` ✅ (Already created)

**Manual run (for testing):**
```batch
cd C:\laragon\www\emulsis-web
run-scheduler.bat
```

Then add this batch file to Task Scheduler instead of direct PHP command.

---

## 🧪 Testing

### **1. Manual Test:**
```bash
cd C:\laragon\www\emulsis-web
php artisan launcher:cleanup-sessions
```

Expected output:
```
Cleaned up 0 expired session(s).
# or
Cleaned up 2 expired session(s).
  - Session abc123... (idle for 120s)
```

### **2. Verify Scheduler:**
```bash
php artisan schedule:list
```

Should show:
```
0 * * * * php artisan launcher:cleanup-sessions
```

### **3. Test Schedule Run:**
```bash
php artisan schedule:run
```

### **4. Check Logs:**
```bash
tail -f storage/logs/scheduler.log
# or
Get-Content storage/logs/scheduler.log -Tail 20 -Wait
```

---

## 📊 How It Works Now

```
Every Minute:
    ↓
Windows Task Scheduler triggers
    ↓
Runs: php artisan schedule:run
    ↓
Laravel checks: app/Console/Kernel.php
    ↓
Runs: launcher:cleanup-sessions
    ↓
Query: SELECT * FROM game_sessions 
       WHERE status='active' 
       AND last_heartbeat < NOW() - 60
    ↓
If found: UPDATE game_sessions SET status='closed'
    ↓
Log results
```

---

## ✅ Benefits

1. **Automatic Cleanup** ✅
   - Runs every minute
   - No manual intervention needed

2. **Prevents Zombie Sessions** ✅
   - Launcher crash/kill → Auto cleanup after 60s
   - No stuck "active" sessions

3. **Accurate Slot Management** ✅
   - Real-time availability
   - Fair resource allocation

4. **Self-Healing** ✅
   - System fixes itself
   - No admin overhead

---

## 🔍 Monitoring

### Check active sessions:
```bash
php check_sessions.php
```

### Check cleanup logs:
```sql
SELECT * FROM game_sessions 
WHERE status='closed' 
ORDER BY id DESC 
LIMIT 10;
```

### Test specific session:
```sql
-- Set old heartbeat (simulating dead launcher)
UPDATE game_sessions 
SET last_heartbeat = NOW() - INTERVAL 120 SECOND 
WHERE id = 87;

-- Wait 1 minute for scheduler
-- Then check:
SELECT status FROM game_sessions WHERE id = 87;
-- Should be 'closed'
```

---

## 🎯 Summary

**Before:**
- ❌ Sessions stuck forever if launcher killed
- ❌ Cleanup only on API requests
- ❌ Slots not freed automatically

**After:**
- ✅ Auto cleanup every 60 seconds
- ✅ Scheduler runs every minute
- ✅ Self-healing system
- ✅ Accurate session tracking

**Status:** 🟢 **PRODUCTION READY**
