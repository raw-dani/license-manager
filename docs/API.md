# License Manager API Documentation

## Base URL

```
https://your-license-server.com/api/v1
```

## Authentication

Semua endpoint (kecuali `ping`) memerlukan API key yang dikirim via header:

```
X-API-Key: YOUR_API_KEY
```

API key dapat ditemukan di **Settings → API Key** pada admin panel.

## Endpoints

### 1. Ping (Health Check)

```
POST /api/v1/ping
```

**Response:**
```json
{
  "status": "success",
  "code": 200,
  "message": "pong",
  "data": {
    "server_time": "2026-08-10 10:00:00",
    "version": "1.0.0"
  }
}
```

---

### 2. Activate License

```
POST /api/v1/activate
```

**Request Body:**
```json
{
  "license_key": "SP-XXXX-XXXX-XXXX",
  "fingerprint": "hash_128_char",
  "platform": "desktop",
  "device_info": {
    "hostname": "my-pc",
    "os": "Windows",
    "os_version": "10.0.19045"
  },
  "domain": "example.com",
  "ip_address": "192.168.1.1"
}
```

**Parameter:**
| Field | Type | Required | Description |
|---|---|---|---|
| `license_key` | string | Yes | License key |
| `fingerprint` | string | Yes | Device fingerprint (max 128 chars) |
| `platform` | string | Yes | `desktop`, `hosting`, `server`, `android` |
| `device_info` | object | No | Additional device information |
| `domain` | string | No | Domain (for hosting platform) |
| `ip_address` | string | No | IP address (for server platform) |

**Response (Success):**
```json
{
  "status": "success",
  "code": 200,
  "message": "License activated successfully",
  "data": {
    "license_key": "SP-XXXX-XXXX-XXXX",
    "token": "eyJ...",
    "expires_in": 86400,
    "expires_at": "2026-08-11 10:00:00",
    "server_time": "2026-08-10 10:00:00"
  }
}
```

**Error Responses:**
- `404` - License not found
- `403` - License is suspended/expired/terminated
- `403` - Max activations reached
- `422` - Validation failed

---

### 3. Verify License

```
POST /api/v1/verify
```

**Request Body:** (sama dengan activate)

**Response:**
```json
{
  "status": "success",
  "code": 200,
  "message": "License verified",
  "data": {
    "license_key": "SP-XXXX-XXXX-XXXX",
    "token": "eyJ...",
    "expires_in": 86400,
    "expires_at": "2026-08-11 10:00:00",
    "server_time": "2026-08-10 10:00:00"
  }
}
```

---

### 4. Deactivate License

```
POST /api/v1/deactivate
```

**Request Body:**
```json
{
  "license_key": "SP-XXXX-XXXX-XXXX",
  "fingerprint": "hash_128_char",
  "platform": "desktop"
}
```

**Response:**
```json
{
  "status": "success",
  "code": 200,
  "message": "Deactivated"
}
```

---

### 5. Get License Status

```
GET /api/v1/license/{license_key}
```

**Response:**
```json
{
  "status": "success",
  "code": 200,
  "data": {
    "license_key": "SP-XXXX-XXXX-XXXX",
    "status": "active",
    "product": {
      "name": "My App",
      "platform": "desktop",
      "version": "1.0.0"
    },
    "max_activations": 1,
    "current_activations": 1,
    "expires_at": "2026-12-31 23:59:59",
    "activated_at": "2026-08-10 10:00:00",
    "last_verified_at": "2026-08-10 10:00:00",
    "server_time": "2026-08-10 10:00:00"
  }
}
```

---

### 6. Validate Device

```
POST /api/v1/validate
```

**Request Body:**
```json
{
  "license_key": "SP-XXXX-XXXX-XXXX",
  "fingerprint": "hash_128_char",
  "platform": "desktop"
}
```

**Response:**
```json
{
  "status": "success",
  "code": 200,
  "message": "Device validated",
  "data": {
    "license_key": "SP-XXXX-XXXX-XXXX",
    "valid": true,
    "server_time": "2026-08-10 10:00:00"
  }
}
```

---

## Fingerprint Generation

### Desktop (Python SDK)
```python
import hashlib

# Combine CPU ID, MAC address, disk serial, motherboard serial, hostname
raw = "|".join([cpu_id, mac_address, disk_serial, motherboard_serial, hostname])
fingerprint = hashlib.sha512(raw.encode()).hexdigest()  # 128 chars
```

### Hosting (PHP SDK)
```php
$fingerprint = hash('sha256', $domain . '|' . $username);  // 64 chars
```

### Server (Bash)
```bash
fingerprint=$(echo -n "${ip}|${hostname}|${mac}|${disk_serial}|${os_info}" | sha256sum | awk '{print $1}')
```

### Android (Pending)
```kotlin
// Android ID + package name
val fingerprint = sha256("$androidId|$packageName")
```

---

## Error Codes

| Code | Description |
|---|---|
| 400 | Bad request / missing parameters |
| 401 | Missing API key |
| 403 | Invalid API key / license not active / max activations |
| 404 | License not found |
| 422 | Validation failed |
| 500 | Server error / API key not configured |