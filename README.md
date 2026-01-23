# Emulsis Web - Rohan Authentication Backend

Backend API Laravel untuk sistem autentikasi **Rohan Online Private Server**, yang melayani RohanClient.exe dan Rohan Launcher.

## 📋 Daftar Isi

- [Teknologi](#-teknologi)
- [Persyaratan Sistem](#-persyaratan-sistem)
- [Instalasi](#-instalasi)
- [Konfigurasi](#-konfigurasi)
- [Struktur Proyek](#-struktur-proyek)
- [API Endpoints](#-api-endpoints)
- [Fitur Utama](#-fitur-utama)
- [Scheduler & Commands](#-scheduler--commands)
- [Deployment](#-deployment)
- [Dokumentasi Tambahan](#-dokumentasi-tambahan)

---

## 🛠 Teknologi

| Teknologi | Versi |
|-----------|-------|
| PHP | ^8.1 |
| Laravel | ^10.10 |
| Laravel Sanctum | ^3.3 |
| Laravel Breeze | ^1.29 |
| SQL Server | 2019+ |
| GuzzleHTTP | ^7.2 |

---

## 📦 Persyaratan Sistem

- **PHP 8.1+** dengan ekstensi berikut:
  - `sqlsrv` (SQL Server driver)
  - `pdo_sqlsrv`
  - `openssl`
  - `mbstring`
  - `json`
- **Composer** untuk dependency management
- **SQL Server** dengan database Rohan:
  - `RohanUser` - Data akun pengguna
  - `RohanGame` - Data game
  - `RohanMall` - Data item mall
  - `RohanManage` - Data manajemen

---

## 🚀 Instalasi

### 1. Clone Repository

```bash
git clone <repository-url>
cd emulsis-web
```

### 2. Install Dependencies

```bash
composer install
```

### 3. Setup Environment

```bash
# Copy example environment
cp .env.example .env

# Generate application key
php artisan key:generate
```

### 4. Konfigurasi Database

Edit file `.env` dan sesuaikan konfigurasi database:

```env
DB_CONNECTION=sqlsrv
DB_HOST='127.0.0.1'
DB_PORT=1433
DB_USERNAME=sa
DB_PASSWORD='your_password'

DB_DATABASE_USER=RohanUser
DB_DATABASE_GAME=RohanGame
DB_DATABASE_MALL=RohanMall
DB_DATABASE_MANAGE=RohanManage
```

### 5. Jalankan Migrasi

```bash
php artisan migrate
```

### 6. Generate API Key untuk Launcher

```bash
php generate_api_key.php
```

---

## ⚙ Konfigurasi

### Environment Variables

| Variable | Deskripsi | Default |
|----------|-----------|---------|
| `APP_NAME` | Nama aplikasi | - |
| `APP_ENV` | Environment (local/production) | local |
| `APP_DEBUG` | Mode debug | true |
| `APP_URL` | URL aplikasi | http://localhost:8000 |
| `GAME_SERVER_NAME` | Nama game server | Testing |
| `GAME_SERVER_IP` | IP game server | 127.0.0.1 |
| `GAME_SERVER_PORT` | Port game server | 22100 |
| `GAME_SERVER_DESCRIPTION` | Deskripsi server | Lorem Ipsum |

---

## 📁 Struktur Proyek

```
emulsis-web/
├── app/
│   ├── Console/
│   │   └── Commands/
│   │       ├── CleanupExpiredSessions.php    # Artisan command cleanup session
│   │       └── CleanupGameServerWhitelist.php
│   ├── Helpers/
│   │   └── CloudflareDebug.php               # Helper debugging Cloudflare
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/
│   │   │   │   └── LauncherController.php    # API untuk Rohan Launcher
│   │   │   ├── Auth/                         # Controller autentikasi web
│   │   │   └── Rohan/
│   │   │       └── RohanAuthController.php   # Legacy ASP endpoints
│   │   └── Middleware/
│   │       ├── CloudflareDebugger.php         # Debug Cloudflare requests
│   │       ├── DisableCloudflareCompression.php
│   │       ├── TrustCloudflare.php            # Trust Cloudflare proxy
│   │       └── VerifyLauncherApiKey.php       # Verifikasi API key launcher
│   ├── Models/
│   │   ├── Game.php                          # Model game data
│   │   ├── Launcher/
│   │   │   ├── GameSession.php               # Model session game
│   │   │   ├── IpRule.php                    # Model IP whitelist/blacklist
│   │   │   └── ServerSetting.php             # Model pengaturan server
│   │   └── User.php
│   └── Utils/
│       └── RohanLogger.php                   # Utility logging
├── config/                                    # Konfigurasi Laravel
├── database/
│   └── migrations/                           # Database migrations
├── routes/
│   ├── api.php                               # Route API (Launcher)
│   ├── auth.php                              # Route autentikasi
│   └── web.php                               # Route web (RohanAuth legacy)
├── scripts/
│   └── windows/
│       ├── firewall_sync.ps1                 # Firewall sync (Windows)
│       └── install_firewall_sync.ps1         # Installer
├── ssl/                                       # SSL certificates
├── storage/
│   └── logs/                                 # Log files
├── tests/                                     # Unit & feature tests
├── .env.example                              # Contoh environment
├── generate_api_key.php                      # Script generate API key
├── check_sessions.php                        # Script cek session aktif
└── run-scheduler.bat                         # Batch file untuk scheduler
```

---

## 🔌 API Endpoints

### Legacy RohanAuth (untuk RohanClient.exe)

Endpoints ini kompatibel dengan game client asli Rohan.

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET/POST | `/RohanAuth/Login3.asp` | Login dan autentikasi |
| GET/POST | `/RohanAuth/ServerList5.asp` | Daftar server game |
| GET/POST | `/RohanAuth/loginremove.asp` | Disconnect/logout paksa |
| GET/POST | `/RohanAuth/sendcode7.asp` | Kirim kode verifikasi |
| GET/POST | `/RohanAuth/DownFlag2.asp` | Flag download client |

#### Contoh Request Login

```
GET /RohanAuth/Login3.asp?nation=TN&id=username&passwd=password&ver=1.0&pcode=1
```

#### Response Login (Success)

```
{session_id}|{user_id}|{run_ver}|{grade}|0
```

#### Response Login (Error Codes)

| Code | Deskripsi |
|------|-----------|
| -1 | Akun tidak terdaftar |
| -2 | Password salah |
| -10 | Sudah login |
| -30 | Versi tidak valid |
| -1000 | Maintenance mode |

---

### Launcher API (untuk Rohan Launcher)

API modern dengan autentikasi API key untuk Rohan Launcher.

**Base URL:** `/api/launcher/`

**Header Required:**
```
X-Launcher-Api-Key: {your_api_key}
```

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | `/request-launch` | Minta izin launch game |
| POST | `/heartbeat` | Keep session alive |
| POST | `/close-session` | Tutup session game |
| GET | `/status` | Cek status server |

#### Request Launch

```http
POST /api/launcher/request-launch
Content-Type: application/json
X-Launcher-Api-Key: your_api_key

{
    "hwid": "hardware_id",
    "client_hash": "hash_of_client"
}
```

#### Response (Success)

```json
{
    "success": true,
    "session_id": "random_64_char_string",
    "active_sessions": 1,
    "max_allowed": 4,
    "heartbeat_interval": 30
}
```

#### Response (Max Clients)

```json
{
    "success": false,
    "error": "Maximum clients reached (4/4)",
    "code": "MAX_CLIENTS_REACHED",
    "active_sessions": 4,
    "max_allowed": 4
}
```

---

## ✨ Fitur Utama

### 1. Multi-Client Limiter

Membatasi jumlah game client per IP address:
- Default: 4 client per IP
- Dapat dikustomisasi per IP via whitelist
- Session timeout otomatis jika tidak ada heartbeat

### 2. IP Whitelist/Blacklist

```php
// Whitelist dengan custom limit
IpRule::create([
    'ip_address' => '192.168.1.100',
    'type' => 'whitelist',
    'max_clients' => 8,
    'reason' => 'VIP User'
]);

// Blacklist
IpRule::create([
    'ip_address' => '10.0.0.50',
    'type' => 'blacklist',
    'reason' => 'Suspicious activity'
]);
```

### 3. Server Settings

Pengaturan server dinamis via database:

| Key | Deskripsi | Default |
|-----|-----------|---------|
| `maintenance_mode` | Mode maintenance (0/1) | 0 |
| `max_clients_per_ip` | Max client per IP | 4 |
| `session_timeout_seconds` | Timeout session (detik) | 60 |
| `server_list` | Data server list | - |
| `down_flag` | Flag download | ROHAN\|1\|1\|ROHAN\|DEFAULT |

### 4. Cloudflare Integration

Backend sudah terintegrasi dengan Cloudflare:
- Trust Cloudflare proxy headers
- Real IP detection via `CF-Connecting-IP`
- Disable compression untuk legacy client
- Debug middleware untuk troubleshooting

### 5. Comprehensive Logging

Logging terstruktur untuk debugging:
- Request/Response logging
- Performance timing
- Error tracking dengan stack trace

---

## ⏰ Scheduler & Commands

### Artisan Commands

```bash
# Cleanup session yang expired
php artisan launcher:cleanup-sessions

# Cleanup whitelist game server
php artisan launcher:cleanup-whitelist
```

### Setup Scheduler

#### Windows (Task Scheduler)

1. Jalankan `run-scheduler.bat` atau buat Task Scheduler:

```batch
@echo off
cd /d "D:\path\to\emulsis-web"
php artisan schedule:run
```

2. Schedule task untuk berjalan setiap menit

#### Linux (Cron)

```bash
* * * * * cd /path/to/emulsis-web && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🚀 Deployment

### Development

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### Production (Laragon/Windows)

1. Point document root ke folder `public/`
2. Setup virtual host
3. Aktifkan SSL certificate
4. Jalankan scheduler via Task Scheduler

### Production dengan Cloudflare

Lihat dokumentasi tambahan:
- [CLOUDFLARE_IMPLEMENTATION.md](./CLOUDFLARE_IMPLEMENTATION.md)
- [CLOUDFLARE_DEBUG_GUIDE.md](./CLOUDFLARE_DEBUG_GUIDE.md)
- [CLOUDFLARE_TROUBLESHOOTING.md](./CLOUDFLARE_TROUBLESHOOTING.md)

---

## 📚 Dokumentasi Tambahan

| File | Deskripsi |
|------|-----------|
| [AISERVER_SPLIT_PLAN.md](./AISERVER_SPLIT_PLAN.md) | Rencana split AIServer ke server terpisah |
| [CLOUDFLARE_IMPLEMENTATION.md](./CLOUDFLARE_IMPLEMENTATION.md) | Panduan integrasi Cloudflare |
| [CLOUDFLARE_DEBUG_GUIDE.md](./CLOUDFLARE_DEBUG_GUIDE.md) | Debug masalah Cloudflare |
| [CLOUDFLARE_TROUBLESHOOTING.md](./CLOUDFLARE_TROUBLESHOOTING.md) | Troubleshooting Cloudflare |
| [GAME_SERVER_PROTECTION.md](./GAME_SERVER_PROTECTION.md) | Proteksi game server dari DDoS |
| [IP_WHITELIST_SETUP.md](./IP_WHITELIST_SETUP.md) | Setup IP Whitelist (Linux + Windows) |
| [LOGOUT_DETECTION.md](./LOGOUT_DETECTION.md) | Deteksi logout player |
| [SCHEDULER_SETUP.md](./SCHEDULER_SETUP.md) | Setup scheduler Windows/Linux |

---

## 🔧 Troubleshooting

### Error: SQL Server Connection Failed

```
Pastikan:
1. SQL Server service berjalan
2. TCP/IP enabled di SQL Server Configuration
3. Port 1433 tidak diblokir firewall
4. Credentials di .env benar
```

### Error: Login Returns -1

```
Cek:
1. Parameter request lengkap (nation, id, passwd, pcode)
2. Stored procedure ROHAN4_Login tersedia
3. Database RohanUser accessible
```

### Error: Launcher API 401 Unauthorized

```
Pastikan:
1. Header X-Launcher-Api-Key dikirim
2. API key valid dan tersimpan di database
3. Middleware launcher.api terdaftar
```

---

## 📄 License

Proyek ini bersifat private untuk penggunaan internal Emulsis Realm.

---

*Dokumentasi terakhir diperbarui: 23 Januari 2026*
