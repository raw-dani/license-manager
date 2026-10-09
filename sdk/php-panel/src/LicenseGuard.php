<?php

namespace LicenseGuard;

/**
 * LicenseGuard - Client Library Penjaga & Pengunci Aplikasi
 * 
 * Fitur Utama:
 * 1. Menghasilkan Install ID unik berbasis hardware, root path aplikasi, dan environment server.
 * 2. Mengunci total aplikasi (Exit/Halt + Layar Lock Screen Interaktif) jika:
 *    - Belum memiliki lisensi
 *    - Lisensi kadaluarsa / disuspend
 *    - Aplikasi dipindahkan/disalin secara paksa ke server/domain/folder lain tanpa mencabut lisensi
 * 3. Menyediakan antarmuka aktivasi dan cabut (revoke) lisensi langsung dari layar terkunci.
 * 4. Mendukung offline grace period (cache token terenkripsi) agar aplikasi tetap jalan sementara saat server lisensi down.
 */
class LicenseGuard
{
    protected string $serverUrl;
    protected string $apiKey;
    protected string $storagePath;
    protected ?string $productSlug;
    protected int $gracePeriodHours;

    /**
     * @param string $serverUrl URL server license manager (contoh: https://license.domainanda.com)
     * @param string $apiKey API key yang diberikan untuk produk ini
     * @param string $storagePath Folder penyimpanan cache lisensi & signature instalasi (wajib writable)
     * @param string|null $productSlug Identitas produk (opsional)
     * @param int $gracePeriodHours Toleransi offline jika server lisensi tidak bisa dihubungi (default: 24 jam)
     */
    public function __construct(
        string $serverUrl,
        string $apiKey,
        string $storagePath,
        ?string $productSlug = null,
        int $gracePeriodHours = 24
    ) {
        $this->serverUrl = rtrim($serverUrl, '/');
        $this->apiKey = $apiKey;
        $this->storagePath = rtrim($storagePath, '/\\');
        $this->productSlug = $productSlug;
        $this->gracePeriodHours = $gracePeriodHours;

        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0755, true);
        }
    }

    /**
     * Jalankan proteksi penuh.
     * Jika lisensi valid, return true dan aplikasi lanjut normal.
     * Jika tidak valid/terkunci, otomatis tampilkan Lock Screen dan matikan eksekusi script.
     */
    public function protect(): bool
    {
        // Tangani form aktivasi / revoke langsung dari Lock Screen jika ada POST request
        $this->handleFormSubmission();

        $licenseFile = $this->storagePath . '/.app_license.json';
        if (!file_exists($licenseFile)) {
            $this->lockApplication('Aplikasi belum terdaftar. Silakan masukkan Lisensi Lisensi Resmi Anda.');
        }

        $data = json_decode(@file_get_contents($licenseFile), true);
        if (!$data || empty($data['license_key'])) {
            $this->lockApplication('Data lisensi lokal rusak atau tidak valid.');
        }

        $licenseKey = $data['license_key'];
        $currentInstallId = $this->getInstallId();

        // 1. Validasi integritas lokal: pastikan install_id file lisensi cocok dengan server/hardware saat ini
        if (!empty($data['install_id']) && $data['install_id'] !== $currentInstallId) {
            $this->lockApplication(
                'Aplikasi telah dipindahkan secara tidak sah ke server/folder baru!<br>' .
                'Silakan cabut lisensi dari server sebelumnya atau hubungi administrator untuk transfer lisensi.'
            );
        }

        // 2. Verifikasi berkala (jika sudah melewati interval verifikasi, misal 6 jam)
        $lastChecked = $data['last_checked'] ?? 0;
        $now = time();
        $checkInterval = 6 * 3600; // Cek ke server setiap 6 jam

        if (($now - $lastChecked) > $checkInterval) {
            $verifyResult = $this->verifyWithServer($licenseKey);

            if ($verifyResult['status'] === 'success') {
                $data['token'] = $verifyResult['data']['token'] ?? ($data['token'] ?? null);
                $data['expires_at'] = $verifyResult['data']['expires_at'] ?? null;
                $data['last_checked'] = $now;
                $data['install_id'] = $currentInstallId;
                @file_put_contents($licenseFile, json_encode($data, JSON_PRETTY_PRINT));
            } else {
                // Jika koneksi gagal (server down atau offline), cek apakah masih dalam masa grace period
                $isConnectionError = isset($verifyResult['is_offline']) && $verifyResult['is_offline'];
                if ($isConnectionError) {
                    $graceExpiry = $lastChecked + ($this->gracePeriodHours * 3600);
                    if ($now > $graceExpiry) {
                        $this->lockApplication(
                            'Gagal terhubung ke server lisensi dan masa tenggang offline (' . $this->gracePeriodHours . ' jam) telah habis.'
                        );
                    }
                    // Masih dalam masa tenggang, izinkan eksekusi lanjut
                } else {
                    // Penolakan tegas dari server (misal: lisensi expired, dibekukan, atau mismatch)
                    $errorMsg = $verifyResult['message'] ?? 'Lisensi tidak sah atau telah dinonaktifkan.';
                    $this->lockApplication($errorMsg);
                }
            }
        }

        return true;
    }

    /**
     * Hitung Install ID yang unik mengikat mesin & lokasi instalasi aplikasi
     */
    public function getInstallId(): string
    {
        $signatureFile = $this->storagePath . '/.install_signature';
        
        $machineFactors = [
            php_uname('n'),                      // Hostname
            $_SERVER['SERVER_ADDR'] ?? '127.0.0.1', // Server IP
            $_SERVER['DOCUMENT_ROOT'] ?? '',       // Web root
            dirname($this->storagePath),          // Root aplikasi
            PHP_OS,
        ];

        // Jika di Linux, coba sertakan /etc/machine-id jika ada
        if (file_exists('/etc/machine-id')) {
            $machineFactors[] = trim(@file_get_contents('/etc/machine-id'));
        }

        $raw = implode('||', $machineFactors);
        return hash('sha256', $raw);
    }

    /**
     * Generate fingerprint untuk platform hosting/server
     */
    public function getFingerprint(): string
    {
        $domain = $this->getDomain();
        $username = get_current_user();
        return hash('sha256', $domain . '|' . $username);
    }

    /**
     * Deteksi nama domain saat ini
     */
    public function getDomain(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? ($_SERVER['SERVER_NAME'] ?? 'localhost');
        return preg_replace('/:\d+$/', '', trim($host));
    }

    /**
     * Verifikasi lisensi ke License Manager Server
     */
    public function verifyWithServer(string $licenseKey): array
    {
        $payload = [
            'license_key' => $licenseKey,
            'fingerprint' => $this->getFingerprint(),
            'platform' => 'hosting',
            'domain' => $this->getDomain(),
            'device_info' => [
                'install_id' => $this->getInstallId(),
                'hostname' => php_uname('n'),
                'os' => PHP_OS,
                'php_version' => PHP_VERSION,
                'app_root' => dirname($this->storagePath),
            ],
        ];

        return $this->request('POST', '/api/v1/verify', $payload);
    }

    /**
     * Aktivasi lisensi baru
     */
    public function activateLicense(string $licenseKey): array
    {
        $payload = [
            'license_key' => trim($licenseKey),
            'fingerprint' => $this->getFingerprint(),
            'platform' => 'hosting',
            'domain' => $this->getDomain(),
            'device_info' => [
                'install_id' => $this->getInstallId(),
                'hostname' => php_uname('n'),
                'os' => PHP_OS,
                'php_version' => PHP_VERSION,
                'app_root' => dirname($this->storagePath),
            ],
        ];

        $res = $this->request('POST', '/api/v1/activate', $payload);

        if ($res['status'] === 'success') {
            $saveData = [
                'license_key' => trim($licenseKey),
                'token' => $res['data']['token'] ?? null,
                'expires_at' => $res['data']['expires_at'] ?? null,
                'install_id' => $this->getInstallId(),
                'domain' => $this->getDomain(),
                'last_checked' => time(),
            ];
            @file_put_contents($this->storagePath . '/.app_license.json', json_encode($saveData, JSON_PRETTY_PRINT));
        }

        return $res;
    }

    /**
     * Cabut / Deaktivasi lisensi secara sah agar bisa dipindahkan ke server baru
     */
    public function revokeLicense(): array
    {
        $licenseFile = $this->storagePath . '/.app_license.json';
        $licenseKey = '';
        if (file_exists($licenseFile)) {
            $data = json_decode(@file_get_contents($licenseFile), true);
            $licenseKey = $data['license_key'] ?? '';
        }

        if (empty($licenseKey)) {
            return ['status' => 'error', 'message' => 'Tidak ada lisensi aktif yang dapat dicabut.'];
        }

        $payload = [
            'license_key' => $licenseKey,
            'fingerprint' => $this->getFingerprint(),
            'platform' => 'hosting',
            'device_info' => [
                'install_id' => $this->getInstallId(),
            ],
        ];

        $res = $this->request('POST', '/api/v1/deactivate', $payload);

        // Hapus file lisensi lokal agar statusnya bersih
        @unlink($licenseFile);

        return $res;
    }

    /**
     * Menangani interaksi POST di Lock Screen
     */
    protected function handleFormSubmission(): void
    {
        if (isset($_POST['_guard_action'])) {
            $action = $_POST['_guard_action'];

            if ($action === 'activate' && !empty($_POST['license_key'])) {
                $res = $this->activateLicense($_POST['license_key']);
                if ($res['status'] === 'success') {
                    // Redirect refresh agar bersih dari POST
                    header('Location: ' . ($_SERVER['REQUEST_URI'] ?? '/'));
                    exit;
                } else {
                    $this->lockApplication('Aktivasi Gagal: ' . ($res['message'] ?? 'Kunci lisensi tidak valid.'));
                }
            }

            if ($action === 'revoke') {
                $res = $this->revokeLicense();
                $msg = ($res['status'] === 'success') 
                    ? 'Lisensi berhasil dicabut! Anda sekarang dapat menggunakannya di server lain.' 
                    : 'Gagal mencabut: ' . ($res['message'] ?? 'Terjadi kesalahan.');
                $this->lockApplication($msg);
            }
        }
    }

    /**
     * HTTP Request ke License Server
     */
    protected function request(string $method, string $endpoint, array $data = []): array
    {
        $url = $this->serverUrl . $endpoint;
        $headers = [
            'X-API-Key: ' . $this->apiKey,
            'X-Install-Id: ' . $this->getInstallId(),
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ch = curl_init();
        if ($method === 'GET') {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 12);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'status' => 'error',
                'is_offline' => true,
                'message' => 'Gagal menghubungi server lisensi: ' . $error,
            ];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return [
                'status' => 'error',
                'message' => 'Format respons dari server lisensi tidak valid (HTTP ' . $httpCode . ')',
            ];
        }

        return $decoded;
    }

    /**
     * Render Tampilan Lock Screen Modern & Matikan Eksekusi
     */
    protected function lockApplication(string $reason): void
    {
        if (ob_get_level()) {
            ob_clean();
        }

        http_response_code(403);

        $domain = htmlspecialchars($this->getDomain());
        $installIdShort = substr($this->getInstallId(), 0, 16) . '...';
        $licenseFile = $this->storagePath . '/.app_license.json';
        $hasLocalLicense = file_exists($licenseFile);

        echo <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Terkunci - Sistem Lisensi</title>
    <style>
        :root {
            --bg-color: #0b0f19;
            --card-bg: rgba(18, 24, 38, 0.95);
            --border-color: rgba(255, 255, 255, 0.08);
            --accent-danger: #ef4444;
            --accent-primary: #3b82f6;
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        body {
            background-color: var(--bg-color);
            background-image: radial-gradient(at 0% 0%, rgba(59, 130, 246, 0.15) 0px, transparent 50%),
                              radial-gradient(at 100% 100%, rgba(239, 68, 68, 0.12) 0px, transparent 50%);
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }
        .lock-container {
            width: 100%;
            max-width: 480px;
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(12px);
            text-align: center;
        }
        .icon-badge {
            width: 64px;
            height: 64px;
            background: rgba(239, 68, 68, 0.12);
            color: var(--accent-danger);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 28px;
        }
        h1 { font-size: 22px; font-weight: 700; margin-bottom: 8px; color: #fff; }
        .reason-box {
            background: rgba(239, 68, 68, 0.08);
            border-left: 4px solid var(--accent-danger);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 6px;
            font-size: 13px;
            margin: 18px 0;
            text-align: left;
            line-height: 1.5;
        }
        .info-grid {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 24px;
            font-size: 12px;
            text-align: left;
            display: grid;
            grid-template-columns: 100px 1fr;
            row-gap: 6px;
            color: var(--text-muted);
        }
        .info-grid span.val { color: var(--text-main); font-family: monospace; word-break: break-all; }
        form { margin-top: 16px; text-align: left; }
        label { display: block; font-size: 13px; color: var(--text-muted); margin-bottom: 6px; }
        input[type="text"] {
            width: 100%;
            padding: 12px 14px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            outline: none;
            margin-bottom: 14px;
            transition: border-color 0.2s;
        }
        input[type="text"]:focus { border-color: var(--accent-primary); }
        button.btn-primary {
            width: 100%;
            padding: 12px;
            background: var(--accent-primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: opacity 0.2s;
        }
        button.btn-primary:hover { opacity: 0.9; }
        .revoke-section {
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--border-color);
        }
        button.btn-danger {
            background: transparent;
            color: var(--accent-danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 8px 14px;
            font-size: 12px;
            border-radius: 6px;
            cursor: pointer;
        }
        button.btn-danger:hover { background: rgba(239, 68, 68, 0.1); }
    </style>
</head>
<body>
    <div class="lock-container">
        <div class="icon-badge">🔒</div>
        <h1>Akses Aplikasi Terkunci</h1>
        <p style="color: var(--text-muted); font-size: 13px;">Sistem mendeteksi lisensi belum terverifikasi atau tidak sah.</p>
        
        <div class="reason-box">{$reason}</div>

        <div class="info-grid">
            <span>Domain:</span>
            <span class="val">{$domain}</span>
            <span>Install ID:</span>
            <span class="val">{$installIdShort}</span>
        </div>

        <form method="POST">
            <input type="hidden" name="_guard_action" value="activate">
            <label for="license_key">Masukkan Kunci Lisensi:</label>
            <input type="text" id="license_key" name="license_key" placeholder="Contoh: PROD-XXXX-XXXX-XXXX" required autofocus>
            <button type="submit" class="btn-primary">Aktivasi Aplikasi</button>
        </form>

HTML;

        if ($hasLocalLicense) {
            echo <<<HTML
        <div class="revoke-section">
            <p style="font-size: 12px; color: var(--text-muted); margin-bottom: 10px;">Ingin memindahkan lisensi ke server lain?</p>
            <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin mencabut lisensi dari server ini?');">
                <input type="hidden" name="_guard_action" value="revoke">
                <button type="submit" class="btn-danger">Cabut Lisensi dari Server Ini</button>
            </form>
        </div>
HTML;
        }

        echo <<<HTML
    </div>
</body>
</html>
HTML;
        exit(1);
    }
}
