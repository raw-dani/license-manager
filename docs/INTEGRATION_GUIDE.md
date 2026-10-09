# Panduan Integrasi Library LicenseGuard (Proteksi & Penguncian Aplikasi)

Library `LicenseGuard` dirancang khusus untuk ditanam ke dalam semua aplikasi PHP/Laravel/Native buatan Anda agar:
1. **Otomatis Terkunci**: Menampilkan layar aktivasi berdesain modern jika belum memiliki lisensi sah.
2. **Anti Pindah Paksa**: Menolak berjalan jika folder aplikasi disalin/dipindahkan ke hosting, server, atau path lain tanpa melalui proses pencabutan lisensi (*revoke*).
3. **Mendukung Offline Grace Period**: Jika server lisensi Anda sedang maintenance atau mati sesaat, aplikasi pembeli tidak langsung mati mendadak (default: toleransi 24 jam).

---

## 1. Persiapan File Library

Cukup salin file [`LicenseGuard.php`](file:///d:/PWA/Aplikasi%20lisensi/license-manager/sdk/php-panel/src/LicenseGuard.php) ke dalam folder aplikasi Anda, misalnya ke `app/Support/LicenseGuard.php` atau `includes/LicenseGuard.php`.

---

## 2. Cara Menanam di Aplikasi Laravel

### Langkah 1: Buat Middleware
Jalankan di aplikasi yang ingin Anda kunci:
```bash
php artisan make:middleware EnsureValidLicense
```

Edit middleware tersebut (misal di `app/Http/Middleware/EnsureValidLicense.php`):
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use LicenseGuard\LicenseGuard;

class EnsureValidLicense
{
    public function handle(Request $request, Closure $next)
    {
        $guard = new LicenseGuard(
            serverUrl: config('license.server_url', 'https://lisensi.domainanda.com'),
            apiKey: config('license.api_key', 'API_KEY_PRODUK_ANDA'),
            storagePath: storage_path('license'),
            gracePeriodHours: 24
        );

        // Jika lisensi tidak valid / dipindahkan paksa, eksekusi otomatis mati di sini
        // dan menampilkan Lock Screen interaktif.
        $guard->protect();

        return $next($request);
    }
}
```

### Langkah 2: Daftarkan ke Kernel / Bootstrap
- **Laravel 11**: Tambahkan di `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->append(\App\Http\Middleware\EnsureValidLicense::class);
})
```
- **Laravel 10 ke bawah**: Tambahkan ke `$middleware` global di `app/Http/Kernel.php`.

---

## 3. Cara Menanam di Aplikasi PHP Native / CodeIgniter

Cukup panggil di baris paling atas file index utama Anda (misal `public/index.php` atau `init.php`):

```php
<?php
require_once __DIR__ . '/includes/LicenseGuard.php';

$guard = new \LicenseGuard\LicenseGuard(
    serverUrl: 'https://lisensi.domainanda.com',
    apiKey: 'API_KEY_PRODUK_ANDA',
    storagePath: __DIR__ . '/storage/license',
    gracePeriodHours: 24
);

// Jalankan kunci lisensi
$guard->protect();

// Kode aplikasi asli Anda berjalan di bawah ini:
// ...
```

---

## 4. Mekanisme Kerja Saat Aplikasi Dipindahkan

1. **Aplikasi Baru Dipasang**:
   - Pengunjung akan langsung melihat layar **"Akses Aplikasi Terkunci"**.
   - Ada input form untuk memasukkan kunci lisensi (*Contoh: PROD-XXXX-XXXX*).
   - Saat disubmit, library mendaftarkan `install_id` unik dari server tersebut ke Server License Manager Anda.
2. **Aplikasi Dipindahkan Secara Paksa (Copy Paste Folder / Database)**:
   - Karena lokasi folder, hostname, atau IP server berubah, `install_id` yang dihasilkan library akan otomatis berbeda.
   - Library mendeteksi ketidaksesuaian dan langsung mengunci aplikasi dengan pesan:
     > *"Aplikasi telah dipindahkan secara tidak sah ke server/folder baru!"*
3. **Cara Sah Memindahkan Lisensi**:
   - Di layar terkunci server lama, pemilik cukup menekan tombol **"Cabut Lisensi dari Server Ini"**.
   - Lisensi akan berstatus bebas kembali di License Manager server, dan siap diaktifkan di server baru.

---

## 5. Tips Keamanan Tambahan (Proteksi Kode Sumber)

Agar pembeli tidak bisa sekadar membuka file PHP dan menghapus pemanggilan `$guard->protect()`:
1. **Obfuscate Entrypoint & Library**:
   - Gunakan alat enkripsi kode seperti **IonCube Encoder**, **SourceGuardian**, atau obfuscator seperti **Yakpro-Po** untuk file `index.php` dan `LicenseGuard.php`.
2. **Kaitkan ke Fungsi Kunci**:
   - Selain di middleware, panggil juga pengecekan di controller penting (misal saat login, checkout, atau generate laporan):
   ```php
   $guard->protect();
   ```
   sehingga jika middleware di-bypass, fungsi inti tetap tidak bisa berjalan.
