# Integrasi License Manager ke Aplikasi PHP

Panduan ini menjelaskan cara mengintegrasikan License Manager ke aplikasi PHP yang sudah ada, misalnya aplikasi web, plugin, atau panel hosting.

## Konsep Dasar

Aplikasi PHP Anda akan berkomunikasi dengan License Manager melalui **API v1** (`/api/v1`). Setiap permintaan harus membawa:
- **Header `X-API-Key`**: untuk autentikasi
- **Data lisensi**: `license_key`, `fingerprint`, `platform`, dll.

Respons dari API berisi:
- `status`: `success` atau `error`
- `code`: HTTP status code
- `message`: pesan deskriptif
- `data`: data lisensi dan token verifikasi

---

## Langkah 1: Persiapan di License Manager

### 1.1 Dapatkan API Key

1. Login ke License Manager (`/admin/settings`)
2. Salin **API Key** yang ditampilkan di halaman Settings
3. Simpan API key ini di aplikasi PHP Anda (disarankan menggunakan environment variable)

### 1.2 Buat License untuk Produk Anda

1. Di License Manager, buka **Products** → **Add Product**
2. Isi:
   - **Name**: nama produk/aplikasi Anda
   - **Platform**: pilih sesuai tipe (desktop/hosting/server/android)
   - **Version**: versi aplikasi
   - **Status**: Active
3. Klik **Create**

### 1.3 Buat License Key untuk Customer

1. Buka **Licenses** → **Create License**
2. Pilih produk yang baru dibuat
3. Isi **Customer Name**, **Customer Email** (untuk notifikasi)
4. Atur **Max Activations** sesuai kebutuhan (misal 1 untuk single domain)
5. Atur **Expires At** jika ada masa kadaluarsa
6. Klik **Create License**

### 1.4 Catat License Key

Salin **License Key** yang dihasilkan. Key ini akan digunakan di aplikasi PHP Anda.

---

## Langkah 2: Integrasi ke Aplikasi PHP

### 2.1 Instalasi SDK (Opsional)

Jika aplikasi PHP Anda menggunakan Composer, Anda bisa menginstall SDK yang tersedia:

```bash
composer require license-manager/php-sdk
```

Atau gunakan file `LicenseClient.php` yang sudah disediakan di `sdk/php-panel/src/`.

### 2.2 Konfigurasi

Simpan konfigurasi License Manager di environment variable atau config file:

```php
// config/license.php
return [
    'server_url' => env('LICENSE_SERVER_URL', 'https://license-manager.example.com'),
    'api_key' => env('LICENSE_API_KEY', ''),
    'license_key' => env('APP_LICENSE_KEY', ''),
];
```

**.env:**
```env
LICENSE_SERVER_URL=https://license-manager.example.com
LICENSE_API_KEY=your-api-key-here
APP_LICENSE_KEY=SP-XXXX-XXXX-XXXX
```

### 2.3 Implementasi Integrasi

Contoh implementasi lengkap untuk aplikasi PHP:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\LicenseClient;

class LicenseChecker
{
    private LicenseClient $client;
    private string $licenseKey;
    
    public function __construct()
    {
        $this->client = new LicenseClient(
            serverUrl: $_ENV['LICENSE_SERVER_URL'],
            apiKey: $_ENV['LICENSE_API_KEY']
        );
        $this->licenseKey = $_ENV['APP_LICENSE_KEY'];
    }
    
    /**
     * Cek status lisensi secara periodik (verify).
     * Panggil ini setiap kali aplikasi dijalankan atau sesuai TTL.
     */
    public function verify(): array
    {
        $fingerprint = $this->generateFingerprint();
        
        $result = $this->client->verify(
            licenseKey: $this->licenseKey,
            domain: $_SERVER['HTTP_HOST'] ?? 'unknown',
            username: $_ENV['APP_USERNAME'] ?? null
        );
        
        return $result;
    }
    
    /**
     * Aktifkan lisensi untuk pertama kali.
     */
    public function activate(): array
    {
        $fingerprint = $this->generateFingerprint();
        
        $result = $this->client->activate(
            licenseKey: $this->licenseKey,
            domain: $_SERVER['HTTP_HOST'] ?? 'unknown',
            username: $_ENV['APP_USERNAME'] ?? null
        );
        
        if ($result['status'] === 'success') {
            // Simpan token untuk verifikasi berikutnya
            $this->client->setToken($result['data']['token']);
        }
        
        return $result;
    }
    
    /**
     * Generate fingerprint unik untuk identifikasi instalasi.
     * Untuk hosting: domain + cPanel username
     * Untuk desktop: kombinasi hardware info
     */
    private function generateFingerprint(): string
    {
        $domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
        $username = $_ENV['APP_USERNAME'] ?? '';
        
        return hash('sha256', $domain . '|' . $username);
    }
    
    /**
     * Deaktivasi lisensi (misal saat uninstall).
     */
    public function deactivate(): array
    {
        return $this->client->deactivate(
            licenseKey: $this->licenseKey,
            domain: $_SERVER['HTTP_HOST'] ?? 'unknown',
            username: $_ENV['APP_USERNAME'] ?? null
        );
    }
}
```

### 2.4 Implementasi Manual (tanpa SDK)

Jika Anda tidak ingin menggunakan SDK, implementasi manual sebagai berikut:

```php
<?php

class LicenseChecker
{
    private string $serverUrl;
    private string $apiKey;
    private string $licenseKey;
    
    public function __construct()
    {
        $this->serverUrl = rtrim($_ENV['LICENSE_SERVER_URL'], '/');
        $this->apiKey = $_ENV['LICENSE_API_KEY'];
        $this->licenseKey = $_ENV['APP_LICENSE_KEY'];
    }
    
    public function verify(string $fingerprint, string $domain, ?string $username = null): array
    {
        $url = $this->serverUrl . '/api/v1/verify';
        
        $data = [
            'license_key' => $this->licenseKey,
            'fingerprint' => $fingerprint,
            'platform' => 'hosting',
            'domain' => $domain,
            'device_info' => [
                'username' => $username,
                'php_version' => phpversion(),
            ],
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return json_decode($response, true) ?: [
            'status' => 'error',
            'code' => $httpCode,
            'message' => 'Invalid response from server',
        ];
    }
    
    private function generateFingerprint(string $domain, ?string $username): string
    {
        return hash('sha256', $domain . '|' . ($username ?? ''));
    }
}
```

---

## Langkah 3: Integrasi ke Aplikasi

### 3.1 WordPress Plugin

Contoh integrasi ke WordPress plugin:

```php
<?php
/*
Plugin Name: My App License Check
Description: License verification for My App
Version: 1.0
*/

class MyAppLicenseCheck
{
    private $licenseChecker;
    private $optionName = 'myapp_license_status';
    
    public function __construct()
    {
        $this->licenseChecker = new LicenseChecker();
        
        // Cek lisensi saat admin dashboard dibuka
        add_action('admin_init', [$this, 'checkLicense']);
        
        // Cek lisensi saat plugin dijalankan
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }
    
    public function checkLicense(): void
    {
        $status = get_option($this->optionName, []);
        
        // Cek setiap 24 jam atau jika belum pernah dicek
        if (empty($status['last_check']) || 
            time() - $status['last_check'] > DAY_IN_SECONDS) {
            
            $result = $this->licenseChecker->verify();
            
            if ($result['status'] === 'success') {
                update_option($this->optionName, [
                    'status' => 'valid',
                    'last_check' => time(),
                    'expires_at' => $result['data']['expires_at'] ?? null,
                ]);
            } else {
                update_option($this->optionName, [
                    'status' => 'invalid',
                    'last_check' => time(),
                    'message' => $result['message'],
                ]);
            }
        }
    }
    
    public function activate(): void
    {
        $result = $this->licenseChecker->activate();
        
        if ($result['status'] !== 'success') {
            wp_die('License activation failed: ' . $result['message']);
        }
    }
    
    public function deactivate(): void
    {
        $this->licenseChecker->deactivate();
    }
    
    public function isLicenseValid(): bool
    {
        $status = get_option($this->optionName, []);
        return $status['status'] === 'valid';
    }
}

new MyAppLicenseCheck();
```

### 3.2 Framework PHP Lain (Laravel, CodeIgniter, dll)

#### Laravel

```php
// app/Services/LicenseService.php
namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;

class LicenseService
{
    public function verify(): array
    {
        $response = Http::withHeaders([
            'X-API-Key' => config('license.api_key'),
        ])->post(config('license.server_url') . '/api/v1/verify', [
            'license_key' => config('license.license_key'),
            'fingerprint' => $this->generateFingerprint(),
            'platform' => 'desktop',
            'device_info' => $this->getDeviceInfo(),
        ]);
        
        return $response->json();
    }
    
    private function generateFingerprint(): string
    {
        // Implementasi fingerprint sesuai platform
        return hash('sha256', gethostname() . '|' . php_uname('m'));
    }
    
    private function getDeviceInfo(): array
    {
        return [
            'php_version' => phpversion(),
            'os' => php_uname('s'),
            'hostname' => gethostname(),
        ];
    }
}
```

#### CodeIgniter 4

```php
// app/Libraries/LicenseClient.php
namespace App\Libraries;

use CodeIgniter\HTTP\CURLRequest;

class LicenseClient
{
    private string $serverUrl;
    private string $apiKey;
    private string $licenseKey;
    
    public function __construct()
    {
        $this->serverUrl = rtrim(getenv('LICENSE_SERVER_URL'), '/');
        $this->apiKey = getenv('LICENSE_API_KEY');
        $this->licenseKey = getenv('APP_LICENSE_KEY');
    }
    
    public function verify(string $fingerprint): array
    {
        $client = \Config\Services::curlrequest();
        
        $response = $client->post($this->serverUrl . '/api/v1/verify', [
            'headers' => [
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'license_key' => $this->licenseKey,
                'fingerprint' => $fingerprint,
                'platform' => 'desktop',
                'device_info' => [
                    'php_version' => phpversion(),
                ],
            ],
            'timeout' => 10,
        ]);
        
        return json_decode($response->getBody(), true);
    }
}
```

---

## Langkah 4: Penanganan Token Verifikasi

Setelah aktivasi atau verifikasi berhasil, API mengembalikan **token**. Token ini digunakan untuk verifikasi berikutnya agar tidak perlu kirim API key berulang kali.

```php
// Simpan token di session/cache/database
$token = $result['data']['token'];
$expiresAt = $result['data']['expires_at'];

// Contoh simpan di session
$_SESSION['license_token'] = $token;
$_SESSION['license_token_expires'] = strtotime($expiresAt);

// Verifikasi token sebelum expire
if (time() < $_SESSION['license_token_expires']) {
    // Token masih valid, bisa verifikasi dengan token
    $headers = [
        'X-API-Key: ' . $this->apiKey,
        'X-Authorization: ' . $_SESSION['license_token'],
    ];
}
```

**Catatan:** Token berakhir sesuai `verify_ttl_hours` yang diatur di License Manager (default: 24 jam). Setelah expire, lakukan verifikasi ulang.

---

## Langkah 5: Error Handling

Bersihkan error yang mungkin terjadi:

```php
$result = $this->licenseChecker->verify();

switch ($result['code']) {
    case 200:
        // Lisensi valid
        break;
    case 403:
        // Lisensi suspended/expired/max activations reached
        $this->disableApplication();
        break;
    case 404:
        // Lisensi tidak ditemukan
        $this->disableApplication();
        break;
    case 422:
        // Validasi gagal (fingerprint invalid, dll)
        $this->logError($result['errors']);
        break;
    case 429:
        // Rate limit exceeded
        sleep(5); // Tunggu sebelum coba lagi
        break;
    default:
        // Server error atau koneksi gagal
        // Tentukan policy: allow offline grace period atau block
        $this->handleServerError($result);
        break;
}
```

---

## Langkah 6: Scheduler (Cron)

Untuk verifikasi periodik, setup cron job di aplikasi PHP Anda:

```bash
# Verifikasi lisensi setiap 6 jam
0 */6 * * * /usr/bin/php /path/to/your/app/verify-license.php >> /var/log/license-verify.log 2>&1
```

`verify-license.php`:
```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$checker = new LicenseChecker();
$result = $checker->verify();

if ($result['status'] !== 'success') {
    // Log error atau notifikasi admin
    error_log('License verification failed: ' . $result['message']);
    
    // Opsional: kirim notifikasi ke admin
    mail(
        getenv('ADMIN_EMAIL'),
        'License Verification Failed',
        'License check failed with message: ' . $result['message']
    );
}
```

---

## Platform Spesifik

### Hosting (cPanel)

```php
$fingerprint = hash('sha256', $domain . '|' . $cpanelUser);
$result = $client->activate($licenseKey, $domain, $cpanelUser);
```

### Desktop

```php
$cpuId = $this->getCpuId();
$mac = $this->getMacAddress();
$diskSerial = $this->getDiskSerial();
$fingerprint = hash('sha256', $cpuId . '|' . $mac . '|' . $diskSerial);
```

### Server (VPS)

```php
$ip = gethostbyname(gethostname());
$hostname = gethostname();
$mac = $this->getMacAddress();
$fingerprint = hash('sha256', $ip . '|' . $hostname . '|' . $mac);
```

### Android

```php
$androidId = $_ENV['ANDROID_ID'];
$packageName = $_ENV['APP_PACKAGE_NAME'];
$fingerprint = hash('sha256', $androidId . '|' . $packageName);
```

---

## Troubleshooting

| Masalah | Solusi |
|---------|--------|
| `403 Invalid API key` | Pastikan API key di aplikasi PHP sama dengan yang di License Manager |
| `404 License not found` | Pastikan license_key benar dan aktif di License Manager |
| `403 Max activations reached` | Hapus aktivasi lama di License Manager atau beli lisensi dengan jumlah aktivasi lebih banyak |
| `Connection timeout` | Cek firewall, pastikan URL License Manager bisa diakses |
| `Token expired` | Lakukan verifikasi ulang untuk mendapatkan token baru |
| `SSL certificate error` | Set `CURLOPT_SSL_VERIFYPEER` sesuai kebutuhan, atau gunakan HTTPS yang valid |

---

## Keamanan

1. **Jangan hardcode API key** di source code. Gunakan environment variable.
2. **Gunakan HTTPS** untuk semua komunikasi dengan License Manager.
3. **Simpan token** di tempat yang aman (session dengan encryption, atau database terenkripsi).
4. **Implementasi grace period** untuk handle koneksi server yang gagal.
5. **Rate limit** di sisi server sudah aktif, tapi tetap implementasi retry logic dengan exponential backoff di aplikasi Anda.

---

## Referensi

- `docs/API.md` - Dokumentasi API lengkap
- `sdk/php-panel/src/LicenseClient.php` - SDK PHP hosting panel
- `app/Services/License/TokenService.php` - Token generation/verification logic
