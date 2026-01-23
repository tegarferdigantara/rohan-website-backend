# IP Whitelisting untuk Game Server (Cross-Platform)

## 📋 Arsitektur

Karena **Web Server** berada di **Linux** dan **Game Server** berada di **Windows**, kita menggunakan pendekatan **Database Polling**:

```
┌─────────────────────────────────┐     ┌─────────────────────────────────┐
│       LINUX (Web Server)        │     │     WINDOWS (Game Server)       │
│   ─────────────────────────     │     │   ───────────────────────────   │
│   • Laravel Backend             │     │   • Game Server (Port 22100)    │
│   • Cloudflare Proxy            │     │   • Windows Firewall            │
│   • Auth API                    │     │   • firewall_sync.ps1           │
│                                 │     │                                 │
│   ┌─────────────────────┐       │     │                                 │
│   │  Launcher API       │       │     │                                 │
│   │  /request-launch    │──────┐│     │                                 │
│   └─────────────────────┘      ││     │                                 │
│              │                 ││     │                                 │
│              ▼                 ││     │                                 │
│   ┌─────────────────────┐      ││     │   ┌─────────────────────┐       │
│   │  game_sessions      │◀─────┘│     │   │  firewall_sync.ps1  │       │
│   │  (RohanManage DB)   │───────│─────│──▶│  (Poll setiap 5s)   │       │
│   └─────────────────────┘       │     │   └──────────┬──────────┘       │
│                                 │     │              │                  │
└─────────────────────────────────┘     │              ▼                  │
                                        │   ┌─────────────────────┐       │
                                        │   │  Windows Firewall   │       │
                                        │   │  Rohan_Allow_*      │       │
                                        │   └─────────────────────┘       │
                                        └─────────────────────────────────┘
```

## 🔄 Flow

1. **Player** login melalui **Rohan Launcher**
2. Launcher memanggil `/api/launcher/request-launch`
3. Laravel menyimpan **IP address** ke tabel `game_sessions` dengan status `active`
4. **PowerShell script** (`firewall_sync.ps1`) di Windows **poll database** setiap 5 detik
5. Script **menambahkan firewall rule** untuk IP yang aktif
6. Player bisa konek ke game server (port 22100)
7. Ketika session **expired/closed**, script **menghapus firewall rule**

---

## 📦 File Structure

```
scripts/
└── windows/
    ├── firewall_sync.ps1         # Main sync script (berjalan terus)
    └── install_firewall_sync.ps1 # Installer untuk scheduled task
```

---

## 🚀 Instalasi di Windows Game Server

### Step 1: Copy Scripts

Copy folder `scripts/windows/` ke game server:

```
C:\RohanServer\
├── firewall_sync.ps1
└── install_firewall_sync.ps1
```

### Step 2: Edit Konfigurasi

Edit `C:\RohanServer\firewall_sync.ps1`:

```powershell
$Config = @{
    # Database Connection - SESUAIKAN INI
    SqlServer = "192.168.1.100"        # IP SQL Server (dari Linux bisa akses)
    SqlDatabase = "RohanManage"
    SqlUsername = "sa"
    SqlPassword = "YourSecurePassword"  # Ganti dengan password asli
    
    # Firewall Settings
    GamePort = 22100
    RulePrefix = "Rohan_Allow_"
    
    # Polling Settings
    PollIntervalSeconds = 5
}
```

### Step 3: Install sebagai Service

Jalankan PowerShell sebagai **Administrator**:

```powershell
cd C:\RohanServer
.\install_firewall_sync.ps1
```

### Step 4: Verifikasi

```powershell
# Cek task status
Get-ScheduledTask -TaskName "RohanFirewallSync"

# Cek log
Get-Content C:\RohanServer\firewall_sync.log -Tail 20

# Cek firewall rules
Get-NetFirewallRule -DisplayName "Rohan_*"
```

---

## 📊 Tabel `game_sessions`

Script membaca dari tabel ini:

```sql
SELECT DISTINCT ip_address 
FROM game_sessions 
WHERE status = 'active' 
AND last_heartbeat > DATEADD(SECOND, -120, GETDATE())
```

| Column | Type | Description |
|--------|------|-------------|
| `ip_address` | VARCHAR(45) | IP address player |
| `status` | ENUM | 'active' atau 'closed' |
| `last_heartbeat` | TIMESTAMP | Waktu heartbeat terakhir |

---

## 🔧 Troubleshooting

### Script tidak bisa konek database

```powershell
# Test koneksi manual
$conn = New-Object System.Data.SqlClient.SqlConnection
$conn.ConnectionString = "Server=192.168.1.100;Database=RohanManage;User Id=sa;Password=xxx;TrustServerCertificate=True;"
$conn.Open()
$conn.State  # Harus "Open"
$conn.Close()
```

**Solusi:**
1. Pastikan SQL Server mengizinkan remote connections
2. Firewall SQL Server (port 1433) terbuka
3. User `sa` enabled dan password benar

### Firewall rules tidak terbuat

```powershell
# Cek permission
whoami  # Harus SYSTEM atau Administrator

# Test manual
New-NetFirewallRule -DisplayName "Test_Rule" -Direction Inbound -Protocol TCP -LocalPort 22100 -RemoteAddress "1.2.3.4" -Action Allow
```

### Session tidak expired

Pastikan Laravel scheduler berjalan untuk cleanup:

```bash
# Di Linux
crontab -e
# Tambahkan:
* * * * * cd /var/www/emulsis-web && php artisan schedule:run >> /dev/null 2>&1
```

---

## 📈 Monitoring

### Cek IP yang aktif di database

```sql
SELECT ip_address, status, last_heartbeat, 
       DATEDIFF(SECOND, last_heartbeat, GETDATE()) as seconds_ago
FROM game_sessions
WHERE status = 'active'
ORDER BY last_heartbeat DESC
```

### Cek firewall rules aktif

```powershell
Get-NetFirewallRule -DisplayName "Rohan_Allow_*" | 
    Select-Object DisplayName, Enabled, Direction |
    Format-Table -AutoSize
```

### Real-time log

```powershell
Get-Content C:\RohanServer\firewall_sync.log -Wait -Tail 20
```

---

## 🛡️ Keamanan

1. **Default BLOCK**: Port 22100 di-block by default
2. **Whitelist Only**: Hanya IP dengan session aktif yang diizinkan
3. **Auto-Expire**: IP dihapus setelah session timeout (120 detik tanpa heartbeat)
4. **Database Auth**: Koneksi database menggunakan authentication

---

## ⚙️ Konfigurasi Lanjutan

### Ubah Timeout Session

Edit di Laravel `.env`:

```env
SESSION_TIMEOUT_SECONDS=60
```

Atau via database `server_settings`:

```sql
INSERT INTO server_settings (key, value) VALUES ('session_timeout_seconds', '90');
```

### Ubah Poll Interval

Edit `firewall_sync.ps1`:

```powershell
PollIntervalSeconds = 3  # Lebih cepat, tapi lebih banyak query
```

### Multiple Game Ports

Edit `firewall_sync.ps1`:

```powershell
$Config = @{
    GamePort = @(22100, 22101, 22102)  # Array of ports
    ...
}
```

---

**Status: PRODUCTION READY** 🚀
