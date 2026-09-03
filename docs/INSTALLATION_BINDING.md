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

**Via API:**
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

**Via UI Admin Panel:**
1. Login ke License Manager
2. Buka **Licenses** → klik license
3. Klik tombol **"Generate Transfer Token"**
4. Set TTL (default 24 jam)
5. Klik Generate → modal menampilkan token → Copy

## Alur Pindah Server

1. **Admin copy folder app ke server baru**
2. **Client di server baru coba verify** → dapat 403 "bound to a different installation"
3. **Admin generate transfer token** (via UI atau API)
4. **Customer simpan token**, jalankan command `bind` di server baru
5. **Server baru ter-bound**, server lama otomatis `is_active = false` → terblokir

---

## Implementasi Lengkap di Sisi Client (PHP)

### Langkah 1: Generate `install_id` Persisten per Server

`install_id` harus **stabil per server** — tidak boleh berubah-ubah. Simpan di file lokal server.

```php
// src/License/InstallId.php
class InstallId
{
    private string $file;

    public function __construct(string $file = '/etc/myapp/install_id')
    {
        $this->file = $file;
    }

    public function get(): string
    {
        if (file_exists($this->file)) {
            return trim(file_get_contents($this->file));
        }

        $installId = bin2hex(random_bytes(16));

        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        file_put_contents($this->file, $installId);
        chmod($this->file, 0600);

        return $installId;
    }
}
```

### Langkah 2: Setup Environment Variables

```env
# .env di aplikasi client (eCatalog, plugin, dll)
LICENSE_SERVER_URL=https://license.gmteknologi.com
LICENSE_API_KEY=your_api_key_from_license_manager
LICENSE_KEY=LM-30EC-3613-E1F8
LICENSE_INSTALL_ID_FILE=/etc/myapp/install_id
```

### Langkah 3: Buat `LicenseChecker` yang Kirim `install_id`

```php
// src/License/LicenseChecker.php
class LicenseChecker
{
    private LicenseClient $client;
    private InstallId $installId;
    private string $licenseKey;
    private string $domain;

    public function __construct()
    {
        $this->client = new LicenseClient(
            $_ENV['LICENSE_SERVER_URL'],
            $_ENV['LICENSE_API_KEY']
        );
        $this->installId = new InstallId($_ENV['LICENSE_INSTALL_ID_FILE'] ?? '/etc/myapp/install_id');
        $this->licenseKey = $_ENV['LICENSE_KEY'];
        $this->domain = $_SERVER['HTTP_HOST'] ?? 'unknown';
    }

    /**
     * Verify license. Jika install_id belum di-bind, otomatis bind.
     * Jika install_id berbeda, return 403 + needs_transfer = true.
     */
    public function verify(): array
    {
        $deviceInfo = [
            'install_id' => $this->installId->get(),
            'hostname' => gethostname(),
            'php_version' => PHP_VERSION,
        ];

        $result = $this->client->verify(
            licenseKey: $this->licenseKey,
            domain: $this->domain,
            deviceInfo: $deviceInfo,
        );

        // Deteksi install_id mismatch
        if (($result['code'] ?? 0) === 403
            && str_contains($result['message'] ?? '', 'bound to a different installation')) {
            $result['needs_transfer'] = true;
        }

        return $result;
    }

    /**
     * Bind lisensi ke install_id saat ini. Butuh transfer token dari admin.
     */
    public function bindWithToken(string $transferToken): array
    {
        $installId = $this->installId->get();

        $result = $this->client->bind(
            licenseKey: $this->licenseKey,
            installId: $installId,
            transferToken: $transferToken,
            platform: 'hosting',
            fingerprint: $this->client->generateFingerprint($this->domain),
            domain: $this->domain,
            hostname: gethostname(),
            deviceInfo: [
                'install_id' => $installId,
                'hostname' => gethostname(),
                'php_version' => PHP_VERSION,
            ],
        );

        return $result;
    }
}
```

### Langkah 4: Tambah Method `bind` di `LicenseClient` SDK

Edit `sdk/php-panel/src/LicenseClient.php`, tambahkan method ini:

```php
public function bind(
    string $licenseKey,
    string $installId,
    string $transferToken,
    string $platform,
    string $fingerprint,
    ?string $domain = null,
    ?string $hostname = null,
    array $deviceInfo = []
): array {
    $payload = [
        'license_key' => $licenseKey,
        'install_id' => $installId,
        'transfer_token' => $transferToken,
        'platform' => $platform,
        'fingerprint' => $fingerprint,
        'domain' => $domain,
        'hostname' => $hostname,
        'device_info' => array_merge($deviceInfo, [
            'ip' => $_SERVER['SERVER_ADDR'] ?? null,
        ]),
    ];

    return $this->request('POST', '/api/v1/bind', $payload);
}
```

### Langkah 5: CLI Command untuk Customer

`bin/transfer-license.php`:

```php
#!/usr/bin/php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Load .env
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    foreach (parse_ini_file($envFile) as $k => $v) {
        $_ENV[$k] = $v;
    }
}

$token = $argv[1] ?? null;

if (!$token) {
    echo "Usage: php bin/transfer-license.php <TRANSFER_TOKEN>\n";
    echo "Dapatkan transfer token dari admin License Manager.\n";
    exit(1);
}

$checker = new LicenseChecker();
$installId = (new InstallId())->get();

echo "Installing license ke server ini...\n";
echo "Install ID: {$installId}\n\n";

$result = $checker->bindWithToken($token);

if (($result['status'] ?? '') === 'success') {
    echo "✓ Lisensi berhasil di-bind ke server ini.\n";
    echo "  License Key: {$result['data']['license_key']}\n";
    echo "  Install ID:  {$result['data']['install_id']}\n";
    echo "  Bound At:    {$result['data']['bound_at']}\n\n";
    echo "Server lama otomatis terblokir.\n";
    exit(0);
}

echo "✗ Gagal: {$result['message']}\n";
exit(1);
```

Cara pakai di server baru:
```bash
sudo php /var/www/myapp/bin/transfer-license.php "TOKEN_DARI_ADMIN"
```

### Langkah 6: UI / Tampilan Error di Aplikasi

**Contoh WordPress Admin Notice** (untuk plugin):

```php
add_action('admin_notices', function () {
    $checker = new LicenseChecker();
    $result = $checker->verify();

    if (!empty($result['needs_transfer'])) {
        $installId = (new InstallId())->get();
        ?>
        <div class="notice notice-error">
            <h3>⚠️ License Tidak Terikat ke Server Ini</h3>
            <p>Aplikasi ini sebelumnya di-install di server lain. Untuk mengaktifkan, minta transfer token ke admin License Manager.</p>
            <p><strong>Install ID server ini:</strong> <code><?= esc_html($installId) ?></code></p>
            <p>Setelah dapat token, jalankan command berikut di terminal:</p>
            <pre>php <?= WP_PLUGIN_DIR ?>/myapp/bin/transfer-license.php YOUR_TRANSFER_TOKEN</pre>
        </div>
        <?php
    }
});
```

### Langkah 7: Cron Auto-Check (Opsional)

```bash
# Cek status license setiap jam, kirim email jika perlu transfer
0 * * * * /usr/bin/php /var/www/myapp/bin/check-license.php >> /var/log/myapp-license.log 2>&1
```

```php
// bin/check-license.php
require_once __DIR__ . '/../vendor/autoload.php';

$checker = new LicenseChecker();
$result = $checker->verify();

if (!empty($result['needs_transfer'])) {
    mail(
        'admin@example.com',
        '[Action Required] License Transfer Needed',
        "Server install ID: " . (new InstallId())->get() . "\n\n" .
        "Minta transfer token ke admin License Manager untuk license: " . $_ENV['LICENSE_KEY']
    );
}
```

---

## FAQ

### Q: Bagaimana jika saya kehilangan transfer token?

A: Generate token baru via UI/API. Token lama otomatis hangus karena hash baru disimpan di database.

### Q: Apakah transfer token bisa dipakai berkali-kali?

A: Bisa, selama belum expired. Setiap bind baru akan `is_active = false` installation lama.

### Q: Berapa lama token valid?

A: Default 24 jam (`ttl_hours`). Bisa di-set 1-168 jam (7 hari) saat generate.

### Q: Bagaimana jika saya mau pindah lagi sebelum token expire?

A: Jalankan `bind` lagi dengan install_id baru. Installation sebelumnya otomatis nonaktif.

### Q: Apakah perlu update SDK di client?

A: Ya, SDK `LicenseClient` harus di-update untuk:
- Kirim `install_id` di `device_info` setiap `verify`/`activate`
- Tambahkan method `bind()`

### Q: Apakah fitur ini backward compatible?

A: Ya. License yang **tidak pernah kirim `install_id`** akan tetap bisa aktivasi/verify (binding skipped, tidak di-block). Hanya jika `install_id` dikirim, binding check aktif.

---

## Keamanan

- `install_id` disimpan di server client (file, database) — bukan dikirim ke client
- `transfer_token` plain-text hanya dikembalikan **1 kali** saat generate
- Server hanya simpan **hash SHA256** dari token di database
- Token expire dalam 24 jam (configurable via `ttl_hours`)
- Setiap bind event dicatat di `activation_logs` dengan `action = 'bind'`
- Endpoint `/api/v1/bind` dilindungi `api.key` middleware + rate limit 10/menit

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
| `transfer_token` | Hash SHA256 token untuk authorization rebind |
| `transfer_token_expires_at` | Token expiry |
| `bound_at` | Waktu binding |
| `last_verified_at` | Last verify dari installation ini |
| `is_active` | True jika binding saat ini |
