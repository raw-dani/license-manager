# Webhook Integration (Instant Suspend)

Dokumentasi ini menjelaskan cara mengintegrasikan License Manager dengan aplikasi eCatalog untuk suspend/reaktivasi instan via webhook.

## Arsitektur

```
┌─────────────────────┐         Webhook (HTTP POST)        ┌─────────────────────┐
│   License Manager   │ ──────────────────────────────────► │      eCatalog       │
│   (Admin Panel)     │                                     │   (Client App)      │
│                     │ ◄────────────────────────────────── │                     │
└─────────────────────┘         Response (JSON)             └─────────────────────┘
```

1. Admin klik "Suspend" di License Manager
2. License Manager update status ke `suspended`
3. License Manager kirim HTTP POST ke `webhook_url` eCatalog
4. eCatalog validasi signature, clear cache, set status suspended
5. Request verify berikutnya dari eCatalog langsung ditolak (instan!)

## Setup di License Manager

### 1. Migrasi Database

Jalankan migrasi untuk menambahkan kolom `webhook_url` dan `webhook_secret`:

```bash
php artisan migrate
```

### 2. Konfigurasi Webhook per License

Buka halaman **Licenses → Edit** dan isi:

| Field | Deskripsi |
|-------|-----------|
| `Webhook URL` | URL callback eCatalog, misal `https://ecatalog.example.com/api/license/callback` |
| `Webhook Secret` | Shared secret untuk validasi signature (min 32 karakter, random) |

### 3. API Endpoints

#### Suspend (Otomatis kirim webhook)

```bash
POST /api/v1/admin/licenses/{license_key}/suspend
Authorization: Bearer SANCTUM_TOKEN
```

Response:
```json
{
  "status": "success",
  "code": 200,
  "message": "License suspended successfully",
  "data": {
    "license_key": "SP-XXXX-XXXX-XXXX",
    "status": "suspended",
    "suspended_at": "2026-09-01 10:00:00",
    "webhook_sent": true
  }
}
```

#### Notify Manual (Kirim ulang webhook)

```bash
POST /api/v1/admin/licenses/{license_key}/notify
Authorization: Bearer SANCTUM_TOKEN
```

Body (opsional):
```json
{
  "event": "license.suspended"
}
```

#### Unsuspend (Otomatis kirim webhook reaktivasi)

```bash
POST /api/v1/admin/licenses/{license_key}/unsuspend
Authorization: Bearer SANCTUM_TOKEN
```

## Setup di eCatalog

### 1. Buat Shared Secret

Generate random secret (32+ karakter):

```bash
php artisan tinker --execute="echo bin2hex(random_bytes(32));"
```

Simpan di `.env` eCatalog:

```env
LICENSE_WEBHOOK_SECRET=your_generated_secret_here
```

### 2. Daftarkan Route

Tambahkan di `routes/api.php`:

```php
use App\Http\Controllers\Api\License\LicenseCallbackController;

Route::post('/api/license/callback', [LicenseCallbackController::class, 'handle']);
```

### 3. Buat Controller

Buat `app/Http/Controllers/Api/License/LicenseCallbackController.php`:

```php
<?php

namespace App\Http\Controllers\Api\License;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LicenseCallbackController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        // Validasi signature
        if (!$this->verifySignature($payload)) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 403);
        }

        $event = $payload['event'] ?? 'unknown';
        $licenseKey = $payload['license_key'] ?? null;
        $status = $payload['status'] ?? null;

        if (!$licenseKey) {
            return response()->json(['status' => 'error', 'message' => 'Missing license_key'], 422);
        }

        switch ($event) {
            case 'license.suspended':
                // Clear cache verifikasi
                Cache::forget('license_verify_' . md5($licenseKey));

                // Set cache status suspended (expire dalam 7 hari)
                Cache::put('license_status_' . md5($licenseKey), [
                    'status' => 'suspended',
                    'suspended_at' => $payload['suspended_at'] ?? now()->toDateTimeString(),
                ], now()->addDays(7));

                Log::info('License suspended via webhook', ['license_key' => $licenseKey]);
                break;

            case 'license.reactivated':
                Cache::forget('license_verify_' . md5($licenseKey));
                Cache::put('license_status_' . md5($licenseKey), [
                    'status' => 'active',
                ], now()->addDays(7));

                Log::info('License reactivated via webhook', ['license_key' => $licenseKey]);
                break;

            default:
                return response()->json(['status' => 'error', 'message' => 'Unknown event'], 422);
        }

        return response()->json(['status' => 'success', 'message' => 'Callback processed']);
    }

    private function verifySignature(array $payload): bool
    {
        if (empty($payload['signature'])) {
            return false;
        }

        $secret = config('license.webhook_secret');
        $providedSignature = $payload['signature'];

        $dataToVerify = $payload;
        unset($dataToVerify['signature']);

        $json = json_encode($dataToVerify, JSON_UNESCAPED_SLASHES);
        $expectedSignature = hash_hmac('sha256', $json, $secret);

        return hash_equals($expectedSignature, $providedSignature);
    }
}
```

### 4. Tambahkan Config

Tambahkan di `config/license.php` (buat jika belum ada):

```php
<?php

return [
    'webhook_secret' => env('LICENSE_WEBHOOK_SECRET', ''),
];
```

### 5. Gunakan Cache di Verifikasi

Di License Service eCatalog, cek cache sebelum melakukan verify ke License Manager:

```php
public function verify(string $licenseKey): array
{
    $cacheKey = 'license_status_' . md5($licenseKey);

    // Cek cache dulu (instan!)
    if (Cache::has($cacheKey)) {
        $cached = Cache::get($cacheKey);

        if ($cached['status'] === 'suspended') {
            return [
                'status' => 'error',
                'code' => 403,
                'message' => 'License is suspended',
            ];
        }
    }

    // Lanjutkan verify ke License Manager...
}
```

## Payload Webhook

### License Suspended

```json
{
  "event": "license.suspended",
  "license_key": "SP-XXXX-XXXX-XXXX",
  "status": "suspended",
  "suspended_at": "2026-09-01 10:00:00",
  "timestamp": "2026-09-01 10:00:00",
  "signature": "sha256_hmac_hex_digest"
}
```

### License Reactivated

```json
{
  "event": "license.reactivated",
  "license_key": "SP-XXXX-XXXX-XXXX",
  "status": "active",
  "timestamp": "2026-09-01 10:00:00",
  "signature": "sha256_hmac_hex_digest"
}
```

## Signature Validation

Signature di-generate dengan HMAC-SHA256 dari JSON payload (tanpa field `signature`):

```php
$json = json_encode($payload, JSON_UNESCAPED_SLASHES);
$signature = hash_hmac('sha256', $json, $secret);
```

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| `webhook_sent: false` di response | Cek `webhook_url` dan `webhook_secret` sudah diisi di license |
| eCatalog tidak menerima callback | Cek firewall, pastikan URL bisa diakses dari internet |
| Signature invalid | Pastikan secret sama di kedua sisi, cek encoding JSON |
| Cache tidak ter-clear | Cek driver cache di eCatalog (file/redis/memcached) |
