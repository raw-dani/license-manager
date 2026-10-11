@echo off
title License Manager Dev Mode
set PATH=C:\wamp64\bin\php\php8.3.14;%PATH%
echo ===================================================
echo Starting License Manager Dev Mode (Artisan + Vite)
echo http://127.0.0.1:8001
echo ===================================================
npx concurrently -c "#93c5fd,#fdba74" "php artisan serve --host=127.0.0.1 --port=8001" "npm run dev" --names=artisan,vite
pause
