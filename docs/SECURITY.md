# Security Audit & Hardening

Dokumentasi security posture aplikasi License Manager dan langkah-langkah mitigasi yang sudah diterapkan.

## Security Features

| Area | Implementasi |
|------|--------------|
| **API Authentication** | Header `X-API-Key` dengan `hash_equals()` (timing-safe) |
| **Admin Authentication** | Laravel Sanctum bearer token dengan expiration 24 jam |
| **Token Signing** | HMAC-SHA256 untuk JWT-like token verifikasi |
| **Webhook Signing** | HMAC-SHA256 dengan shared secret per license |
| **Session Security** | Encrypted by default, HTTP-only, SameSite=lax |
| **CSRF** | Web routes dilindungi CSRF middleware |
| **Headers** | CSP, X-Frame-Options DENY, HSTS, X-Content-Type-Options |
| **Rate Limiting** | Per-endpoint: ping 60/m, license 30/m, activation 10/m |
| **Race Condition** | `lockForUpdate()` di license activation |
| **SSRF** | Webhook URL divalidasi terhadap private IP range |
| **Token Replay** | Transfer token di-hash SHA256 & one-time use |
| **CORS** | Eksplisit via `config/cors.php` |

## Per-License Access Control

Admin bisa set domain/IP whitelist per license via metadata:

```json
{
  "allowed_domain": "*.example.com",
  "allowed_ip": "203.0.113.0/24"
}
```

Field `allowed_domain` mendukung wildcard (`*.example.com`). Field `allowed_ip` mendukung single IP atau CIDR notation.

Request yang tidak match akan ditolak dengan HTTP 403.

## Security Headers

Semua response menyertakan:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: DENY`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: geolocation=(), microphone=(), camera=()`
- `Content-Security-Policy: default-src 'self'; ...`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains` (production only)

## API Security Best Practices

1. **Rotate API key** secara berkala via Settings → Regenerate API Key
2. **Set `allowed_domain` & `allowed_ip`** di metadata license untuk high-security
3. **Gunakan HTTPS** untuk semua komunikasi
4. **Monitor `activation_logs`** untuk aktivitas mencurigakan (FAILED dengan IP berbeda)
5. **Set webhook URL** hanya ke domain yang terpercaya (SSRF protection aktif)

## Vulnerability History

### Fixed in v2.0 (2026-09)

- ✅ **Transfer token hash mismatch** — token disimpan plain, query juga plain (broken)
- ✅ **Race condition di activation** — check max_activations di luar transaction
- ✅ **SSRF via webhook URL** — admin bisa set URL ke internal IP
- ✅ **Transfer token replay** — token tidak di-clear setelah dipakai
- ✅ **IDOR pada API** — validasi ownership dengan domain/IP whitelist
- ✅ **Sanctum token tidak expired** — diset 24 jam
- ✅ **No CORS config** — dibuat `config/cors.php`
- ✅ **Mass assignment** — `webhook_secret` & `transfer_token` dipindah ke `$guarded`
- ✅ **No rate limit di /ping** — ditambah `throttle:60,1`
- ✅ **Session encryption off** — di-enable by default
- ✅ **Missing CSP header** — ditambahkan
- ✅ **TTL tidak divalidasi** — `max:168` untuk `ttl_hours`

## Reporting Security Issues

Jika menemukan vulnerability, hubungi admin via email (jangan post di public issue tracker).
