@echo off
title Sekolario - Peluncur Aplikasi Lokal
color 0A

echo =====================================================================
echo           SEKOLARIO - SISTEM ADMINISTRASI SEKOLAH
echo                    Peluncur Aplikasi Lokal
echo =====================================================================
echo.

REM Berpindah ke direktori root aplikasi
cd /d "%~dp0"

REM 1. Pengecekan berkas konfigurasi Backend (.env)
if not exist "laravel-api\.env" (
    echo [!] Berkas laravel-api\.env tidak ditemukan.
    echo [*] Menyalin dari laravel-api\.env.example...
    copy "laravel-api\.env.example" "laravel-api\.env" >nul
    echo [*] Membuat application key...
    cd laravel-api
    call php artisan key:generate
    cd ..
    echo [OK] Berkas laravel-api\.env berhasil disiapkan.
    echo.
)

REM 2. Pengecekan berkas konfigurasi Frontend (.env)
if not exist "vue-app\.env" (
    echo [*] Menyiapkan berkas vue-app\.env...
    if exist "vue-app\.env.example" (
        copy "vue-app\.env.example" "vue-app\.env" >nul
    ) else (
        echo VITE_API_BASE_URL=http://127.0.0.1:8000 > "vue-app\.env"
    )
    echo [OK] Berkas vue-app\.env berhasil disiapkan.
    echo.
)

REM 3. Pengecekan MySQL Port 3306
echo [*] Memeriksa koneksi database MySQL lokal (port 3306)...
netstat -ano | findstr /R ":3306 " >nul
if %ERRORLEVEL% neq 0 (
    echo [PERINGATAN] Port 3306 tidak terdeteksi aktif!
    echo Pastikan Apache/MySQL di XAMPP Control Panel sudah dalam status 'Start'.
    echo.
    pause
) else (
    echo [OK] MySQL terdeteksi aktif pada port 3306.
    echo.
)

REM 4. Menjalankan Backend Laravel API (Port 8000)
echo [*] Menyalakan Backend API Laravel pada port 8000...
start "Sekolario Backend API" cmd /k "title Sekolario Backend API && color 0B && cd /d "%~dp0laravel-api" && echo ====================================================== && echo          SEKOLARIO BACKEND API (LARAVEL 11) && echo   URL: http://127.0.0.1:8000 && echo   Tekan Ctrl+C pada jendela ini untuk mematikan backend && echo ====================================================== && php artisan serve --host=127.0.0.1 --port=8000"

REM 5. Menjalankan Frontend Vue SPA (Port 4000)
echo [*] Menyalakan Frontend Vue 3 SPA pada port 4000...
start "Sekolario Frontend SPA" cmd /k "title Sekolario Frontend SPA && color 0E && cd /d "%~dp0vue-app" && echo ====================================================== && echo          SEKOLARIO FRONTEND SPA (VUE 3 + VITE) && echo   URL: http://localhost:4000 && echo   Tekan Ctrl+C pada jendela ini untuk mematikan frontend && echo ====================================================== && npm run dev"

echo.
echo =====================================================================
echo  Server Backend dan Frontend sedang dimulai...
echo  Browser Anda akan terbuka secara otomatis ke http://localhost:4000
echo =====================================================================
echo.

REM Jeda beberapa detik agar service siap menerima koneksi
timeout /t 4 /nobreak >nul

REM Membuka aplikasi di browser default
start http://localhost:4000

echo [INFO] Aplikasi telah terbuka di browser.
echo Jangan tutup jendela terminal Backend dan Frontend selama menggunakan aplikasi.
echo.
pause
