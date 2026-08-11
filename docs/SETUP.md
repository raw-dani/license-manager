# Setup & Deployment

## Prasyarat

- PHP 8.2+
- Composer 2
- Node.js 18+
- MySQL 8 / MariaDB 10.6+ (atau SQLite untuk development)
- Web Server (Apache/Nginx) atau Laravel Server

## Instalasi (Development)

```bash
# Clone repositori dan masuk ke direktori
cd license-manager

# Install dependencies
composer install
npm install

# Konfigurasi environment
cp .env.example .env
php artisan key:generate

# Konfigurasi database di .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=license_manager
# DB_USERNAME=root
# DB_PASSWORD=

# Jalankan migrasi database
php artisan migrate

# Seed data awal (roles, permissions, settings, admin user)
php artisan db:seed

# Build frontend
npm run build

# Jalankan server development
php artisan serve

# Sekarang jalankan Vite untuk hot reload (di terminal terpisah)
npm run dev
```

## Akun Default

Setelah seeder dijalankan:

| Field | Value |
|---|---|
| Email | `admin@example.com` |
| Password | `password` |

> **PENTING:** Segera ganti password default setelah login pertama!

## Konfigurasi Database

Edit file `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=license_manager
DB_USERNAME=root
DB_PASSWORD=yourpassword
```

## Scheduler (Cron)

Tambahkan ke crontab server Anda:

```bash
* * * * * cd /path-to-license-manager && php artisan schedule:run >> /dev/null 2>&1
```

Ini menjalankan secara otomatis:
- `license:auto-expire` - menandai lisensi kedaluwarsa
- `license:reminder` - peringatan kedaluwarsa
- `activation:purge-stale` - membersihkan aktivasi yang tidak aktif

## Deployment (Production)

### Nginx

```nginx
server {
    listen 80;
    server_name license.example.com;
    root /var/www/license-manager/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

### Apache

```apache
<VirtualHost *:80>
    ServerName license.example.com
    DocumentRoot /var/www/license-manager/public

    <Directory /var/www/license-manager/public>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

### Optimasi Production

```bash
# Cache routes, config, dan views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Optimasi Composer (hapus dev dependencies)
composer install --optimize-autoloader --no-dev
```

## Struktur Direktori

```
license-manager/
├── app/
│   ├── Http/Controllers/
│   │   ├── Admin/          # Admin panel controllers
│   │   └── Api/V1/         # Public API controllers
│   ├── Models/             # Eloquent models
│   ├── Services/
│   │   ├── License/        # License business logic
│   │   ├── Platform/       # Fingerprint validation
│   │   └── WHMCS/          # WHMCS billing bridge
│   └── Console/Commands/   # Scheduler commands
├── resources/js/Pages/     # React pages
├── sdk/
│   ├── python/             # Python desktop SDK
│   ├── php-panel/          # cPanel/SyberPanel PHP SDK
│   └── server/             # Server bash SDK
├── docs/                   # Documentation
└── tests/                  # Tests
```

## Verifikasi Instalasi

1. **Test ping API:**
   ```bash
   curl -X POST https://your-server.com/api/v1/ping
   ```

2. **Test activate API:**
   ```bash
   curl -X POST https://your-server.com/api/v1/activate \
     -H "X-API-Key: YOUR_API_KEY" \
     -H "Content-Type: application/json" \
     -d '{
       "license_key": "SP-XXXX-XXXX-XXXX",
       "fingerprint": "your_fingerprint_hash",
       "platform": "desktop"
     }'
   ```

3. **Akses admin panel:**
   Buka `https://your-server.com/admin` dan login dengan akun default.

## Troubleshooting

### Error: "No application encryption key"
```bash
php artisan key:generate
```

### Error: "Class not found"
```bash
composer dump-autoload
```

### Error: "Permission denied" pada storage
```bash
chmod -R 775 storage bootstrap/cache
```

### Frontend tidak muncul
```bash
npm run build
```
