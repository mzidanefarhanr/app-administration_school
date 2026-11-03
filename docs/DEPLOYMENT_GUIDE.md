# Panduan Lengkap Deployment & Instalasi Produksi
## Production Deployment Guide — Versi 1.0.0 (Dual-Mode: Local LAN & Cloud VPS)

Dokumen ini merupakan panduan teknis langkah-demi-langkah bagi administrator dan staf IT sekolah untuk memasang, mengonfigurasi, dan menjalankan **Sistem Administrasi Sekolah** di lingkungan operasional nyata.

Panduan ini mencakup dua skenario penerapan (**Dual-Mode**):
- **Bagian A:** Pemasangan di Jaringan Lokal Sekolah (*On-Premise Local LAN / Intranet*) — Bebas biaya hosting, data berada 100% di dalam sekolah.
- **Bagian B:** Pemasangan di Server Online Publik (*Cloud VPS / Web Hosting*) — Dapat diakses dari mana saja melalui internet ber-HTTPS.
- **Bagian C:** Langkah Inisialisasi Bersama (Konfigurasi Basis Data, Bootstrap Superadmin, dan Optimasi Cache).
- **Bagian D:** Pre-Use Smoke Test Checklist Pasca-Instalasi ($\le 10$ Detik).

---

## BAGIAN A: Pemasangan di Jaringan Lokal Sekolah (On-Premise Local LAN)

Skenario ini ideal bagi sekolah yang mengutamakan keamanan fisik data, tidak memiliki anggaran bulanan untuk cloud/domain, atau ingin sistem tetap dapat digunakan secara lancar saat koneksi internet sekolah sedang bermasalah.

### A.1. Persiapan Komputer Server Sekolah
1. **Spesifikasi Rekomendasi:**
   - PC Desktop / Laptop Server dengan prosesor minimal Intel Core i3 / AMD Ryzen 3.
   - RAM minimal 4 GB (disarankan 8 GB).
   - Media penyimpanan SSD dengan ruang kosong minimal 20 GB.
   - Terhubung ke router / switch jaringan Wi-Fi sekolah melalui kabel LAN (disarankan) atau Wi-Fi stabil.
2. **Pengaturan IP Statis Server Lokal:**
   - Atur IP statis pada adapter jaringan komputer server sekolah (misal: `192.168.1.100` dengan subnet mask `255.255.255.0` dan gateway router sekolah).
   - Dengan IP statis, seluruh guru dan staf dapat mengakses server melalui alamat `http://192.168.1.100`.
   - *(Opsional)* Jika router sekolah mendukung DNS lokal, Anda dapat memetakan nama domain lokal seperti `http://administrasi.sekolah.local` ke IP server tersebut.

### A.2. Pemasangan Menggunakan XAMPP (Windows Server / Windows 10/11)
1. **Instalasi Prasyarat:**
   - Pasang XAMPP for Windows dengan versi PHP minimal **8.2**.
   - Buka file `C:\xampp\php\php.ini`, pastikan ekstensi berikut aktif (tanpa tanda titik koma `;` di depannya):
     ```ini
     extension=pdo_mysql
     extension=zip
     extension=fileinfo
     extension=gd
     extension=bcmath
     extension=mbstring
     extension=openssl
     extension=curl
     ```
   - Restart Apache dan MySQL dari XAMPP Control Panel.
2. **Penempatan Berkas Proyek:**
   - Letakkan repositori proyek pada direktori web (contoh: `D:\xampp-htdocs\app-administration_school`).
3. **Konfigurasi Apache VirtualHost:**
   - Buka file `C:\xampp\apache\conf\extra\httpd-vhosts.conf`, tambahkan konfigurasi berikut:
     ```apache
     <VirtualHost *:80>
         ServerName administrasi.sekolah.local
         DocumentRoot "D:/xampp-htdocs/app-administration_school/laravel-api/public"

         <Directory "D:/xampp-htdocs/app-administration_school/laravel-api/public">
             Options -Indexes +FollowSymLinks
             AllowOverride All
             Require all granted
         </Directory>

         # Sajikan berkas statis frontend SPA jika diakses langsung
         Alias /app "D:/xampp-htdocs/app-administration_school/vue-app/dist"
         <Directory "D:/xampp-htdocs/app-administration_school/vue-app/dist">
             Options -Indexes +FollowSymLinks
             AllowOverride All
             Require all granted
             FallbackResource /app/index.html
         </Directory>
     </VirtualHost>
     ```
   - Restart layanan Apache.
4. **Penjadwalan Backup Otomatis di Windows (Task Scheduler):**
   - Buka Windows Task Scheduler (`taskschd.msc`).
   - Buat task baru bernama `Sekolah_Artisan_Schedule`.
   - Atur Trigger: Setiap hari, ulangi setiap 1 menit (*repeat every 1 minute*).
   - Atur Action: Jalankan program `C:\xampp\php\php.exe` dengan argumen `artisan schedule:run` di direktori `D:\xampp-htdocs\app-administration_school\laravel-api`.
   - Ini akan otomatis menjalankan pencadangan data setiap pukul 02:00 WIB dan rotasi retensi pukul 02:30 WIB.

---

## BAGIAN B: Pemasangan di Cloud VPS Online (Ubuntu 22.04 LTS / Debian 12)

Skenario ini digunakan jika sekolah menginginkan pimpinan dan guru dapat mengakses sistem dari rumah atau luar kota dengan nama domain resmi ber-HTTPS.

### B.1. Persiapan Server VPS & Domain
1. Siapkan VPS Linux (Ubuntu 22.04 LTS) dengan minimal 1 vCPU, 2 GB RAM, 25 GB SSD.
2. Arahkan *DNS A Record* domain sekolah (misal: `administrasi.sekolah.sch.id`) ke alamat IP Publik VPS.

### B.2. Instalasi Paket Perangkat Lunak
Jalankan perintah berikut di terminal SSH server:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mariadb-server certbot python3-certbot-nginx supervisor unzip curl git

# Pasang PHP 8.2 dan ekstensi wajib:
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-zip php8.2-fileinfo php8.2-gd \
                    php8.2-bcmath php8.2-mbstring php8.2-xml php8.2-curl php8.2-cli
```

### B.3. Konfigurasi Nginx & SSL HTTPS
Buat berkas konfigurasi Nginx pada `/etc/nginx/sites-available/administrasi-sekolah`:
```nginx
server {
    server_name administrasi.sekolah.sch.id;
    root /var/www/app-administration_school/vue-app/dist;
    index index.html;

    # Penanganan rute Frontend SPA (Vue Router HTML5 History Mode)
    location / {
        try_files $uri $uri/ /index.html;
    }

    # Penanganan rute Backend API Laravel
    location /api {
        root /var/www/app-administration_school/laravel-api/public;
        try_files $uri $uri/ /index.php?$query_string;

        location ~ \.php$ {
            include snippets/fastcgi-php.conf;
            fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
            fastcgi_param SCRIPT_FILENAME /var/www/app-administration_school/laravel-api/public/index.php;
            include fastcgi_params;
        }
    }

    # Blokir akses terhadap berkas tersembunyi & sensitif
    location ~ /\.(env|git|htaccess) {
        deny all;
        return 404;
    }

    # Blokir akses langsung ke storage privat
    location ^~ /storage/app/attachments {
        deny all;
        return 403;
    }
}
```
Aktifkan situs dan pasang sertifikat SSL gratis Let's Encrypt:
```bash
sudo ln -s /etc/nginx/sites-available/administrasi-sekolah /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d administrasi.sekolah.sch.id
```

### B.4. Konfigurasi Background Queue Worker (Supervisor)
Buat file konfigurasi Supervisor pada `/etc/supervisor/conf.d/school-worker.conf`:
```ini
[program:school-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/app-administration_school/laravel-api/artisan queue:work database --sleep=3 --tries=3 --backoff=5,25,125 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/app-administration_school/laravel-api/storage/logs/worker.log
```
Aktifkan pekerja antrean:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
```

### B.5. Konfigurasi Cron Scheduler Linux
Buka crontab pengguna `www-data`:
```bash
sudo crontab -e -u www-data
```
Tambahkan baris berikut:
```cron
* * * * * cd /var/www/app-administration_school/laravel-api && php artisan schedule:run >> /dev/null 2>&1
```

---

## BAGIAN C: Langkah Inisialisasi Bersama (Database, Admin, dan Cache)

Langkah-langkah berikut wajib dijalankan pada kedua skenario pemasangan (LAN maupun VPS):

### C.1. Kloning Repositori & Penataan Izin Direktori
```bash
cd /var/www/app-administration_school  # atau direktori htdocs lokal
git fetch --all --tags
git checkout tags/v1.0.0

# Pasang dependensi PHP tanpa paket pengembangan:
cd laravel-api
composer install --no-dev --optimize-autoloader --no-interaction

# Penataan izin direktori (Linux Server):
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### C.2. Penyiapan Berkas Lingkungan (`.env`)
Salin template konfigurasi produksi:
```bash
cp .env.production.example .env
php artisan key:generate
```
Buka file `.env`, lalu sesuaikan variabel penting berikut:
- `APP_URL`: Alamat IP lokal (misal `http://192.168.1.100`) atau domain online (misal `https://administrasi.sekolah.sch.id`).
- `DB_DATABASE`: Nama database produksi (default: `backdata_administrations`).
- `DB_USERNAME` dan `DB_PASSWORD`: Kredensial pengguna database server Anda.
- `BACKUP_ENCRYPTION_KEY`: Kunci rahasia minimal 12 karakter untuk enkripsi arsip backup ZIP AES-256. Simpan kunci ini di pengelola kata sandi aman staf IT.
- `MYSQLDUMP_PATH` dan `MYSQL_PATH`: Sesuaikan dengan path binary MySQL server Anda.

### C.3. Eksekusi Migrasi Basis Data & Seeder Peran
Jalankan migrasi skema tabel lengkap ke database produksi:
```bash
php artisan migrate --force
```
Jalankan seeder peran dasar dan hak akses sistem:
```bash
php artisan db:seed --class=Database\\Seeders\\SystemRoleSeeder --force
```

### C.4. Inisialisasi Akun Superadministrator Perdana
Bangkitkan akun administrator sistem utama secara aman melalui antarmuka baris perintah:
```bash
php artisan app:bootstrap-admin
```
*Panduan Pengisian:*
- **Full name:** Masukkan nama lengkap administrator (contoh: `Administrator Sistem`).
- **Username:** Masukkan username login (contoh: `admin.sekolah`).
- **Email:** Masukkan email resmi staf IT sekolah.
- **NIK:** Masukkan 16 digit NIK administrator.
- **Password:** Masukkan kata sandi yang kuat (minimal 8 karakter, kombinasi huruf besar, kecil, angka, dan simbol). Kata sandi diminta melalui *secret prompt* (karakter tidak tampak di layar terminal).

### C.5. Optimasi Cache Produksi
Jalankan kompilasi cache untuk memaksimalkan kecepatan respon API:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

---

## BAGIAN D: Pre-Use Smoke Test Checklist Pasca-Instalasi ($\le 10$ Detik)

Sebelum menyerahkan sistem kepada pengguna sekolah, lakukan pengujian cepat berikut:

1. **Uji Konektivitas Database & Akun Admin:**
   ```bash
   php artisan tinker --execute="echo 'DB OK. Total Pengguna: '.App\Models\User::count().PHP_EOL;"
   ```
   *Ekspektasi:* Muncul pesan jumlah pengguna (minimal 1 akun administrator).
2. **Uji Status Pemantauan Backup:**
   ```bash
   php artisan tinker --execute="echo json_encode(app(App\Services\BackupMonitoringService::class)->getHealthStatus()).PHP_EOL;"
   ```
   *Ekspektasi:* Mengembalikan payload JSON tanpa galat.
3. **Uji Akses Halaman Login Antarmuka SPA:**
   - Buka peramban di laptop lain, ketik alamat server (`http://192.168.1.100` atau `https://administrasi.sekolah.sch.id`).
   - *Ekspektasi:* Halaman login Sistem Administrasi Sekolah muncul dengan bersih, tema modern, dan tanpa pesan galat di konsol peramban (`F12`).
4. **Uji Login Akun Superadmin:**
   - Masukkan username dan kata sandi yang dibuat pada tahap C.4.
   - *Ekspektasi:* Berhasil masuk ke Beranda / Dashboard Sistem Administrasi Sekolah dengan hak akses penuh 10 menu navigasi.
5. **Uji Pencadangan Perdana:**
   ```bash
   php artisan app:backup-run
   ```
   *Ekspektasi:* Muncul pesan `Backup successfully created!` dan berkas ZIP terenkripsi tercipta di `storage/app/backups/`.
