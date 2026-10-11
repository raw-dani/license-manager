@echo off
title License Manager Server
set PATH=C:\wamp64\bin\php\php8.3.14;%PATH%
echo ===================================================
echo Starting License Manager on http://127.0.0.1:8001
echo Using PHP 8.3 (C:\wamp64\bin\php\php8.3.14)
echo Admin Login: admin@example.com / password
echo ===================================================
php artisan serve --host=127.0.0.1 --port=8001
pause
