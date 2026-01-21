# Cloudflare Debug Testing Guide

## Setup Complete ✅

Sistem debugging Cloudflare telah disetup dengan komponen berikut:

### 1. **Helper Class**
- Location: `app/Helpers/CloudflareDebug.php`
- Functions:
  - `logRequest()` - Log request details
  - `logComparison()` - Compare proxy vs direct
  - `isViaCloudflare()` - Check if via CF
  - `getRealIp()` - Get client real IP
  - `getProtocol()` - Detect HTTP/HTTPS

### 2. **Middleware**
- Location: `app/Http/Middleware/CloudflareDebugger.php`
- Registered as: `cf.debug`
- Auto-attached to all `/RohanAuth/*` routes

### 3. **Log Files** (Auto-created)
- `storage/logs/cloudflare_debug.log` - Full request details
- `storage/logs/cf_comparison.log` - Quick comparison
- `storage/logs/cf_response.log` - Response status

## Testing Steps

### Phase 1: Direct Connection (DNS Only - Grey Cloud)

1. **Set Cloudflare DNS to "DNS Only"**
   - Go to Cloudflare dashboard
   - Find `auth.emulsis-realm.my.id`
   - Click orange cloud to make it grey
   
2. **Test with curl:**
   ```bash
   curl "http://auth.emulsis-realm.my.id/RohanAuth/ServerList5.asp"
   ```

3. **Check logs:**
   ```bash
   # View latest log entries
   tail -n 50 storage/logs/cloudflare_debug.log
   tail -n 20 storage/logs/cf_comparison.log
   ```

4. **Expected log output:**
   ```json
   {
     "cloudflare": {
       "cf_ray": "NOT_VIA_CLOUDFLARE",
       "cf_visitor": null,
       "cf_connecting_ip": null
     },
     "client": {
       "ip": "YOUR_REAL_IP"
     }
   }
   ```

### Phase 2: Proxied (Orange Cloud)

1. **Enable Cloudflare Proxy**
   - Set DNS back to "Proxied" (orange cloud)
   
2. **Test again:**
   ```bash
   curl "http://auth.emulsis-realm.my.id/RohanAuth/ServerList5.asp"
   ```

3. **Check logs:**
   ```bash
   tail -n 50 storage/logs/cloudflare_debug.log
   ```

4. **Expected log output:**
   ```json
   {
     "cloudflare": {
       "cf_ray": "8abc123def456-SIN",
       "cf_visitor": "{\"scheme\":\"http\"}",
       "cf_connecting_ip": "YOUR_REAL_IP"
     },
     "client": {
       "ip": "104.x.x.x"  // Cloudflare IP
     }
   }
   ```

### Phase 3: Game Client Test

1. **Run launcher & login**
2. **Check all 3 log files:**
   ```bash
   # Full details
   cat storage/logs/cloudflare_debug.log | findstr "Login3"
   
   # Comparison
   cat storage/logs/cf_comparison.log
   
   # Response status
   cat storage/logs/cf_response.log
   ```

## Comparison Analysis

### What to Look For:

**Direct (Working):**
- ✓ `cf_ray`: "NOT_VIA_CLOUDFLARE"
- ✓ `client.ip`: Your real IP
- ✓ `ssl.https`: "not_set" (pure HTTP)

**Proxied (Failing?):**
- ✓ `cf_ray`: Has Cloudflare Ray ID
- ✓ `cf_connecting_ip`: Your real IP (preserved)
- ✓ `client.ip`: Cloudflare edge IP
- ? `ssl.https`: Check if set
- ? `headers`: Check for missing/extra headers

### Common Issues:

1. **Protocol Mismatch**
   - CF visitor shows "https" but backend expects "http"
   - Solution: Set `$_SERVER['HTTPS']` based on CF-Visitor

2. **Missing Headers**
   - Game client sends custom headers
   - CF strips/modifies them
   - Solution: Add CF Page Rule to disable features

3. **IP Validation**
   - Backend validates IP
   - CF changes source IP
   - Solution: Use `CF-Connecting-IP` header

## Next Steps After Testing

1. **Save both log outputs** (direct & proxied)
2. **Compare differences** using diff tool
3. **Identify root cause** from comparison
4. **Apply fix** based on findings

## Quick Commands

```bash
# Clear logs before testing
rm storage/logs/cf*.log

# Watch logs in real-time
tail -f storage/logs/cloudflare_debug.log

# Count requests
grep "timestamp" storage/logs/cloudflare_debug.log | wc -l

# Extract only CF-Ray IDs
grep "cf_ray" storage/logs/cf_comparison.log
```
