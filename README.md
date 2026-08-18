# License Manager

Aplikasi manajemen lisensi berbasis web yang dibangun dengan **Laravel 11** dan **React (Inertia.js)**. Aplikasi ini dirancang untuk mengelola lisensi aplikasi yang berjalan di **desktop**, **hosting**, **server**, dan **Android** dengan dukungan multi-platform fingerprint dan aktivasi.

## Fitur Utama

- Admin panel dengan React (Inertia.js)
- CRUD Products & Licenses
- Aktivasi/verifikasi lisensi multi-platform (desktop, hosting, server, android)
- Token-based verification (JWT-like token)
- Activity logs & scheduler (auto-expire, reminder, purge stale)
- WHMCS billing bridge (opsional, stub)
- API key protection + public API v1
- SDK untuk PHP (hosting panel), Bash (server), dan Python (desktop)
- Dokumentasi API & setup
- Testing (PHPUnit)

## Struktur Proyek

```
license-manager/
├── app/
│   ├── Http/Controllers/
│   │   ├── Admin/          # Admin panel controllers
│   │   ├── Api/V1/         # Public API controllers
│   │   └── Auth/           # Login/logout controllers
│   ├── Models/             # Eloquent models
│   ├── Services/
│   │   ├── License/        # License business logic
│   │   ├── Platform/       # Fingerprint validation
│   │   └── WHMCS/          # WHMCS billing bridge
│   └── Console/Commands/   # Scheduler commands
├── resources/js/Pages/     # React pages (Inertia)
├── sdk/
│   ├── python/             # Python desktop SDK
│   ├── php-panel/          # cPanel/SyberPanel PHP SDK
│   └── server/             # Server bash SDK
├── docs/                   # Documentation
└── tests/                  # PHPUnit tests
```

## Prasyarat

- PHP 8.2+
- Composer 2
- Node.js 18+
- MySQL 8 / MariaDB 10.6+ (atau SQLite untuk development)
- Web Server (Apache/Nginx) atau Laravel Server

## Instalasi (Development)

```bash
cd license-manager

composer install
npm install

cp .env.example .env
php artisan key:generate

# Konfigurasi database di .env
php artisan migrate
php artisan db:seed

npm run build
php artisan serve

php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan optimize:clear
```

> Catatan: Untuk development dengan hot reload, jalankan `npm run dev` di terminal terpisah.

## Akun Default

Setelah seeder dijalankan:

| Field | Value |
|---|---|
| Email | `admin@example.com` |
| Password | `password` |

Segera ganti password default setelah login pertama.

## API Endpoints

Base URL: `/api/v1`

| Method | Endpoint | Deskripsi |
|---|---|---|
| POST | `/ping` | Health check (public) |
| POST | `/activate` | Aktivasi lisensi |
| POST | `/verify` | Verifikasi lisensi |
| POST | `/deactivate` | Deaktivasi lisensi |
| GET | `/license/{key}` | Detail lisensi |
| POST | `/validate` | Validasi fingerprint |

Semua endpoint kecuali `/ping` memerlukan header `X-API-Key`.

## Fingerprint Generation

- **Desktop (Python):** CPU ID + MAC + disk serial + motherboard serial + hostname
- **Hosting (PHP):** Domain + cPanel username
- **Server (Bash):** IP + hostname + MAC + disk serial + OS info
- **Android:** Android ID + package name

## Scheduler (Cron)

```bash
* * * * * cd /path-to-license-manager && php artisan schedule:run >> /dev/null 2>&1
```

Commands:
- `license:auto-expire` - menandai lisensi kedaluwarsa
- `license:reminder` - peringatan kedaluwarsa
- `activation:purge-stale` - membersihkan aktivasi yang tidak aktif

## Testing

```bash
php artisan test
```

## Deployment

Lihat dokumentasi lengkap di `docs/SETUP.md`.

### Upgrade Aplikasi Live

Untuk menambahkan fitur baru (misalnya remote suspend/unsuspend) ke aplikasi yang sudah berjalan di produksi:

1. **Pull kode terbaru** ke server produksi (Git / SFTP).
2. **Jalankan migrasi database:**
   ```bash
   php artisan migrate --force
   ```
3. **Clear cache Laravel:**
   ```bash
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   php artisan optimize:clear
   ```
4. **Rebuild frontend assets (jika ada perubahan UI):**
   ```bash
   npm install
   npm run build
   ```
5. **Restart queue worker (jika menggunakan queue):**
   ```bash
   php artisan queue:restart
   ```
6. **Verifikasi fitur:**
   - Pastikan admin panel menampilkan field baru (misalnya `Suspended At`).
   - Test endpoint baru via API / cURL.
   - Pastikan aplikasi client mulai merespon sesuai perubahan.

> Catatan: Jika kamu menggunakan zero-downtime deployment (Laravel Forge / Envoyer), pastikan migrasi dijalankan di dalam script deployment.

## Dokumentasi

- `docs/API.md` - Dokumentasi API
- `docs/SETUP.md` - Panduan instalasi dan deployment
- `docs/INTEGRASI_WHMCS.md` - Integrasi WHMCS billing bridge

## Kontribusi

Pull request dan issue sangat diterima.

## License

MIT