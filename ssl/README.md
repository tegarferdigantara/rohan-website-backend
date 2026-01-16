# SSL Certificate Setup for Rohan Auth

## Certificate Information

The `laragon.crt` certificate is valid for the following domains:

```
DNS.1 = localhost
DNS.2 = emulsis-web.test
DNS.3 = *.emulsis-web.test
DNS.4 = patch.test
DNS.5 = *.patch.test
DNS.6 = RohanAuth.test
DNS.7 = *.RohanAuth.test
```

## Certificate Details

- **Issuer**: Laragon (Self-Signed)
- **Valid From**: Jan 16 2026
- **Valid Until**: Sep 13 2047
- **SHA1 Fingerprint**: `E4:65:B3:C3:7D:A6:40:70:2D:3F:78:67:46:39:6D:7B:06:C2:04:37`

## Files

| File | Description |
|------|-------------|
| `laragon.crt` | SSL Certificate (public) |
| `laragon.key` | Private Key (KEEP SECRET!) |
| `laragon.csr` | Certificate Signing Request |
| `cacert.pem` | CA Certificate Bundle |

## Setup Instructions

### For Laragon

1. Copy certificate files to Laragon SSL folder:
   ```powershell
   Copy-Item ".\ssl\laragon.crt" "C:\laragon\etc\ssl\laragon.crt" -Force
   Copy-Item ".\ssl\laragon.key" "C:\laragon\etc\ssl\laragon.key" -Force
   ```

2. Restart Laragon (Menu -> Apache -> Restart)

3. Access via HTTPS:
   - `https://emulsis-web.test/RohanAuth/Login3.asp`
   - `https://emulsis-web.test/RohanAuth/ServerList5.asp`

### For Production Server

1. Replace with proper SSL certificate from Let's Encrypt or other CA
2. Update Apache/Nginx configuration to use the certificate
3. Ensure the Rohan client is configured to trust the new certificate

## Important Notes

⚠️ **The Rohan client may have certificate pinning enabled.**

This means the client will ONLY trust this specific certificate. If you change the certificate, you may need to:
1. Update the client executable
2. Or ensure the new certificate has the same fingerprint (not possible with different keys)

## Testing SSL

```powershell
# Check certificate
openssl x509 -in ssl/laragon.crt -text -noout

# Verify key matches certificate
openssl x509 -noout -modulus -in ssl/laragon.crt | openssl md5
openssl rsa -noout -modulus -in ssl/laragon.key | openssl md5
# Both should output the same MD5 hash
```
