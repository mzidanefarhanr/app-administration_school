@echo off
title Sekolario - Setup Database Lokal
color 0B

echo =====================================================================
echo         SEKOLARIO - PENGATURAN DATABASE LOKAL
echo =====================================================================
echo.

cd /d "%~dp0laravel-api"

echo [*] Menjalankan migrasi database ke basis data lokal...
php artisan migrate

echo.
echo =====================================================================
echo  PILIHAN: Apakah Anda ingin membuat / reset akun superadmin lokal?
echo =====================================================================
echo.
echo 1. Ya, buat akun Administrator baru
echo 2. Tidak, lewati langkah ini
echo.
set /p opt="Pilih [1/2]: "

if "%opt%"=="1" (
    echo.
    echo Menjalankan perintah pembuatan administrator:
    php artisan app:bootstrap-admin
)

echo.
echo [SELESAI] Konfigurasi database lokal selesai.
pause
