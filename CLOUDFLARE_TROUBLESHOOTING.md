# Cloudflare Proxy Troubleshooting Summary

## ✅ **What's Working Now:**

1. **TrustCloudflare Middleware** ✅
   - Host header normalization: `emulsis-realm.my.id` → `auth.emulsis-realm.my.id`
   - Real IP preserved via `CF-Connecting-IP`
   - HTTPS detection via `CF-Visitor`

2. **CloudflareDebug Logging** ✅
   - Request details captured
   - Headers logged correctly
   - CF-Ray present

3. **Latest Test (19:39:04)** ✅
   - CF-Ray: `9c1946827975fe09-SIN`
   - Host: `auth.emulsis-realm.my.id` (after middleware fix)
   - Real IP: `86.48.10.61`
   - All data preserved

---

## ❌ **Issue: Login Still Fails**

### Timeline Analysis:

**Direct (Grey Cloud) - Works:**
```
19:33:17 - SendCode ✅
19:33:19 - Login3 ✅
19:33:20 - ServerList5 ✅ (Called = Login Success!)
```

**Proxied (Orange Cloud) - Fails:**
```
19:39:01 - SendCode ✅
19:39:04 - Login3 ❌
(No ServerList call) ❌ (Not called = Login Failed!)
```

---

## 🔍 **Possible Root Causes:**

### 1. **DNS Configuration Issue**
**Hypothesis:** `auth.emulsis-realm.my.id` subdomain not configured in Cloudflare

**Check:**
- Go to Cloudflare DNS settings
- Look for `auth` A record
- Make sure it points to `129.212.226.244`
- Make sure it's set to **Proxied (Orange Cloud)**

**Current observation:**
- Client connects to: `http://emulsis-realm.my.id` (root domain)
- But backend expects: `http://auth.emulsis-realm.my.id` (subdomain)
- Middleware fixes this AFTER request arrives
- But client might not follow redirects

### 2. **Client Configuration**
**Hypothesis:** Game client still configured with root domain

**Check in Launcher:**
```csharp
// AppConfig.cs or LauncherConfiguration.cs
AuthServer = "http://auth.emulsis-realm.my.id"  // Should use SUBDOMAIN
// NOT:
AuthServer = "http://emulsis-realm.my.id"       // Wrong!
```

### 3. **Response Size Mismatch**
**From logs:**
- Direct Login: `response_size: 57` (session token)
- Proxied Login: `response_size: 57` (same size!)

This means response IS being sent. But client might:
- Reject it due to content encoding (`gzip` vs `gzip, deflate`)
- Timeout waiting for data
- Close connection prematurely

### 4. **Accept-Encoding Header**
**Difference:**
- Direct: `gzip, deflate`
- Proxied: `gzip` only

Cloudflare strips `deflate`. Backend might compress differently.

### 5. **TCP Connection Issue**
**Hypothesis:** Cloudflare closes connection before client receives full response

Game client uses HTTP/1.0 or HTTP/1.1 with `Keep-Alive`. Cloudflare might:
- Close connection after response
- Use different TCP window size
- Fragment packets differently

---

## 🛠️ **Debugging Steps:**

### Step 1: Verify DNS
```bash
# Check DNS resolution
nslookup auth.emulsis-realm.my.id
# Should return: 104.x.x.x or 162.x.x.x (Cloudflare IPs)

nslookup emulsis-realm.my.id
# Should return: 104.x.x.x or 162.x.x.x (Cloudflare IPs)
```

### Step 2: Update Launcher Config
**Check these files:**
1. `D:\Codes\C#\RohanLauncher\RohanLauncher\Config\AppConfig.cs`
2. `Rohan.ini` (if exists)

**Should be:**
```csharp
AuthServer = "http://auth.emulsis-realm.my.id"
```

**NOT:**
```csharp
AuthServer = "http://emulsis-realm.my.id"
```

### Step 3: Add Response Logging
**Need to capture actual response body from proxied vs direct**

Add to `Login3.php` (line 83, before echo):
```php
if($ret == 0){
    debug_log('login3', 'Login Success Response', [
        'data' => $data,
        'via_cf' => isset($_SERVER['HTTP_CF_RAY']),
        'cf_ray' => $_SERVER['HTTP_CF_RAY'] ?? 'N/A'
    ]);
    echo $data;
```

### Step 4: Disable Gzip Compression
**Test if compression is the issue**

Add to `Login3.php` (very top, line 2):
```php
<?php
header('Content-Encoding: identity'); // Disable gzip
error_reporting(0);
// ... rest of code
```

### Step 5: Cloudflare Page Rule
**Create Page Rule to disable optimizations**

URL pattern: `auth.emulsis-realm.my.id/RohanAuth/Login3.asp*`

Settings:
- Disable Performance
- Disable Apps
- Disable Rocket Loader
- Browser Integrity Check: OFF

### Step 6: Check SQL Server Response
**Verify stored procedure returns same data**

Add to `Login3.php` (line 67):
```php
debug_log('login3', 'SQL Result FULL', [
    'user_id' => $user_id,
    'sess_id' => $sess_id,  // Full, not trimmed
    'sess_id_length' => strlen($sess_id),
    'run_ver' => $run_ver,
    'grade' => $grade,
    'ret' => $ret,
    'cf_ray' => $_SERVER['HTTP_CF_RAY'] ?? 'DIRECT'
]);
```

---

## 📊 **Expected Behavior:**

### Working Login Flow:
1. Client → SendCode → Server returns: `1`
2. Client → Login3 → Server returns: `{session}|{id}|{version}|{grade}|0`
3. Client → ServerList5 → Server returns server list
4. Client connects to game server

### Current Proxied Flow:
1. Client → SendCode → Server returns: `1` ✅
2. Client → Login3 → Server returns: `???` ❓
3. Client DOES NOT call ServerList5 ❌

**Conclusion:** Client receives response but **rejects it** or **considers it invalid**.

---

## 🎯 **Next Actions:**

1. **Verify subdomain DNS** in Cloudflare
2. **Update launcher config** to use `auth.emulsis-realm.my.id`
3. **Add response body logging** to see exact data sent
4. **Test with gzip disabled**
5. **Create Cloudflare Page Rule** to disable optimizations
6. **Compare SQL query results** between direct and proxied

---

**Once you complete checks above, share:**
1. DNS settings screenshot
2. Launcher config (`AuthServer` value)
3. New debug logs with response body
