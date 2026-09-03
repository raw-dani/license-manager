# Installation Binding (License Transfer Lock)

Dokumentasi ini menjelaskan fitur **installation binding** yang mengikat lisensi ke satu server. Jika aplikasi di-copy ke server lain, server lama otomatis terblokir kecuali admin generate transfer token.

## Konsep

| Status | Deskripsi |
|--------|-----------|
| **Bound** | Lisensi terikat ke `install_id` server tertentu |
| **Mismatch** | Request dari `install_id` berbeda → ditolak (403) |
| **Transfer** | Admin generate token → server baru boleh bind |

## API Endpoints

### 1. Verify (otomatis check binding)

```
POST /api/v1/verify
```

Tambahkan `install_id` di `device_info` atau header `X-Install-Id`:

```json
{
  "license_key": "SP-XXXX-XXXX-XXXX",
  "fingerprint": "abc123...",
  "platform": "hosting",
  "device_info": {
    "install_id": "unique-server-id-123",
    "hostname": "server.example.com"
  }
}
```

Atau:
```
X-Install-Id: unique-server-id-123
```

**Flow:**
- Pertama kali verify → server otomatis bound ke `install_id`
- Verify berikutnya dari `install_id` sama → OK
- Verify dari `install_id` berbeda → **403 Forbidden**

### 2. Bind (rebind ke server baru)

```
POST /api/v1/bind
```

**Request Body:**
```json
{
  "license_key": "SP-XXXX-XXXX-XXXX",
  "install_id": "new-server-id",
  "transfer_token": "TOKEN_DARI_ADMIN",
  "platform": "hosting",
  "fingerprint": "abc123...",
  "domain": "example.com",
  "hostname": "new-server",
  "device_info": {
    "ip": "1.2.3.4",
    "os": "Ubuntu 22.04"
  }
}
```

**Response (Success):**
```json
{
  "status": "success",
  "code": 200,
  "message": "License bound to this installation",
  "data": {
    "license_key": "SP-XXXX-XXXX-XXXX",
    "install_id": "new-server-id",
    "bound_at": "2026-09-03 10:00:00"
  }
}
```

**Response (Error - perlu transfer token):**
```json
{
  "status": "error",
  "code": 403,
  "message": "Transfer token required to bind to a new server. Use POST /license/transfer-token to request one from admin."
}
```

### 3. Generate Transfer Token (Admin only)

```
POST /api/v1/admin/licenses/{license_key}/transfer-token
Authorization: Bearer SANCTUM_TOKEN
Content-Type: application/json
```

**Request Body (opsional):**
```json
{
  "ttl_hours": 24
}
```

**Response (Success):**
```json
{
  "status": "success",
  "code": 200,
  "message": "Transfer token generated",
  "data": {
    "license_key": "SP-XXXX-XXXX-XXXX",
    "transfer_token": "abc123...random64char",
    "expires_in_hours": 24,
    "expires_at": "2026-09-04 10:00:00"
  }
}
```

## Implementasi di Client (eCatalog/dll)

### Generate install_id yang stabil per server

```php
class LicenseChecker
{
    private function getInstallId(): string
    {
        // Pakai file persisten - simpan install_id di server
        $file = '/etc/myapp/install_id';
        
        if (file_exists($file)) {
            return trim(file_get_contents($file));
        }
        
        $installId = bin2hex(random_bytes(16));
        file_put_contents($file, $installId);
        chmod($file, 0600);
        
        return $installId;
    }
    
    public function verify(string $licenseKey): array
    {
        $result = $this->client->verify(
            licenseKey: $licenseKey,
            domain: $_SERVER['HTTP_HOST'],
            username: 'panel-user',
            deviceInfo: [
                'install_id' => $this->getInstallId(),
                'hostname' => gethostname(),
            ]
        );
        
        if ($result['code'] === 403 && str_contains($result['message'], 'bound to a different installation')) {
            // Server terblokir - minta admin generate transfer token
            $this->notifyAdminTransferNeeded($licenseKey);
        }
        
        return $result;
    }
}
```

### Alur Pindah Server

1. **Admin copy folder app ke server baru**
2. **Client di server baru coba verify** → dapat 403 "bound to a different installation"
3. **Admin di License Manager** generate transfer token:
   ```bash
   curl -X POST https://license-server.com/api/v1/admin/licenses/SP-XXX/transfer-token \
     -H "Authorization: Bearer ADMIN_TOKEN"
   ```
4. **Client di server baru** panggil `/api/v1/bind` dengan token
5. **Server baru ter-bound**, server lama otomatis `is_active = false` di remote → terblokir

## Keamanan

- `install_id` disimpan di server client (file, database) — bukan dikirim ke client
- `transfer_token` hanya bisa digunakan **1 kali** (opsional, bisa di-reuse sampai expired)
- Token expire dalam 24 jam (configurable via `ttl_hours`)
- Setiap bind event dicatat di `activation_logs`

## Database

Tabel `license_installations` menyimpan:

| Field | Deskripsi |
|-------|-----------|
| `license_id` | Foreign key ke licenses |
| `install_id` | Unique ID per server (dari client) |
| `fingerprint` | Device fingerprint |
| `platform` | desktop/hosting/server/android |
| `domain` | Domain (jika hosting) |
| `ip_address` | IP server |
| `hostname` | Hostname server |
| `server_info` | JSON info server tambahan |
| `transfer_token` | Token hash untuk authorization rebind |
| `transfer_token_expires_at` | Token expiry |
| `bound_at` | Waktu binding |
| `last_verified_at` | Last verify dari installation ini |
| `is_active` | True jika binding saat ini |
