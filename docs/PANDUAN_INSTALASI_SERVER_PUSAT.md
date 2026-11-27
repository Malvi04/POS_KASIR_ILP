# 🖥️ PANDUAN INSTALASI & DEPLOYMENT SERVER PUSAT (CENTRAL SERVER)
## Sistem Kasir (Point of Sale) & Retail Management Minimarket Koperasi PT Indo Lampung Perkasa (ILP POS)

---

### 📌 Ringkasan Peran Server Pusat (Central Node)

Server Pusat (*Central Server*) adalah simpul pengendali utama sistem POS Koperasi PT ILP dengan konfigurasi `POS_NODE_ROLE=central`. Server ini bertindak sebagai:
1. **Master Database**: Pusat konsolidasi data inventori, transaksi multi-cabang, piutang anggota koperasi, dan akuntansi dasar.
2. **Sync Hub API Provider**: Menerima pengiriman transaksi batch dari seluruh PC kasir lokal dan mendistribusikan pembaruan master produk/harga/karyawan.
3. **Manager & Owner Web Portal**: Menyediakan antarmuka manajemen operasional, logistik gudang, pendaftaran anggota, dan dashboard eksekutif Owner.
4. **Real-time Event Server**: Menjalankan Laravel Reverb (WebSocket) untuk siaran notifikasi langsung saat kasir login, stok menipis, atau terjadi transaksi kasbon.

---

## 📑 DAFTAR ISI

1. [Spesifikasi Perangkat & Kebutuhan Sistem](#1-spesifikasi-perangkat--kebutuhan-sistem)
2. [Arsitektur Perangkat Lunak Server](#2-arsitektur-perangkat-lunak-server)
3. [Langkah-Langkah Instalasi (Ubuntu / Debian Linux)](#3-langkah-langkah-instalasi-ubuntu--debian-linux)
   - [Langkah 1: Pembaruan Sistem & Instalasi Paket Pendukung](#langkah-1-pembaruan-sistem--instalasi-paket-pendukung)
   - [Langkah 2: Instalasi PHP 8.3 & Ekstensi yang Dibutuhkan](#langkah-2-instalasi-php-83--ekstensi-yang-dibutuhkan)
   - [Langkah 3: Instalasi & Konfigurasi PostgreSQL 15+](#langkah-3-instalasi--konfigurasi-postgresql-15)
   - [Langkah 4: Unduh Repositori Kode Aplikasi](#langkah-4-unduh-repositori-kode-aplikasi)
   - [Langkah 5: Konfigurasi File Environment (.env)](#langkah-5-konfigurasi-file-environment-env)
   - [Langkah 6: Instalasi Dependensi PHP & Kompilasi Aset Frontend](#langkah-6-instalasi-dependensi-php--kompilasi-aset-frontend)
   - [Langkah 7: Eksekusi Migrasi Database & Seeder Awal](#langkah-7-eksekusi-migrasi-database--seeder-awal)
   - [Langkah 8: Konfigurasi Web Server Nginx & SSL HTTPS](#langkah-8-konfigurasi-web-server-nginx--ssl-https)
   - [Langkah 9: Konfigurasi Supervisor (Queue Worker & Reverb WebSocket)](#langkah-9-konfigurasi-supervisor-queue-worker--reverb-websocket)
   - [Langkah 10: Setup Cron Job Penjadwal Tugas Laravel](#langkah-10-setup-cron-job-penjadwal-tugas-laravel)
4. [Prosedur Pendaftaran Terminal Kasir Baru (pos:create-terminal)](#4-prosedur-pendaftaran-terminal-kasir-baru-poscreate-terminal)
5. [Pengujian & Verifikasi Kesiapan Server](#5-pengujian--verifikasi-kesiapan-server)
6. [Pemeliharaan, Backup Otomatis & Troubleshooting](#6-pemeliharaan-backup-otomatis--troubleshooting)

---

## 1. Spesifikasi Perangkat & Kebutuhan Sistem

| Komponen | Spesifikasi Minimum | Spesifikasi Rekomendasi (Production) |
|---|---|---|
| **Sistem Operasi** | Ubuntu Server 22.04 LTS / Debian 12 | Ubuntu Server 24.04 LTS (64-bit) |
| **Processor (CPU)** | 2 Cores vCPU | 4 Cores vCPU atau lebih tinggi |
| **RAM (Memory)** | 4 GB | 8 GB s/d 16 GB |
| **Penyimpanan (Disk)** | 40 GB SSD | 80 GB+ NVMe SSD |
| **Jaringan & IP** | IP Statis / Domain Publik (Minimal 20 Mbps) | IP Publik Statis, Domain Aktif + SSL (HTTPS) |
| **Port Firewall Wajib Buka** | 80 (HTTP), 443 (HTTPS), 22 (SSH) | 80, 443, 22, 8080 (Opsional: WebSocket Reverb) |

---

## 2. Arsitektur Perangkat Lunak Server

```mermaid
graph TD
    subgraph ClientNodes ["🌐 Node Luar / Klien"]
        PC1["PC Kasir 01 (PST)"] -->|HTTPS Bearer Token| NGINX
        PC2["PC Kasir 02 (PST)"] -->|HTTPS Bearer Token| NGINX
        PC3["PC Kasir Cabang Pabrik"] -->|HTTPS Bearer Token| NGINX
        Browser["Browser Manager / Owner"] -->|HTTPS| NGINX
        BrowserReverb["Browser (Live Notif)"] -->|WSS WebSocket| NGINX
    end

    subgraph ServerPusat ["🏢 Server Pusat (Linux Ubuntu)"]
        NGINX["Nginx Web Server (Reverse Proxy & SSL)"]
        
        NGINX -->|FastCGI / Port 9000| PHP["PHP 8.3 FPM (Laravel 13 Application)"]
        NGINX -->|Proxy Pass :8080| REVERB["Laravel Reverb (WebSocket Daemon)"]
        
        PHP --> DB[(PostgreSQL 15+ Database)]
        
        SUPERVISOR["Supervisor Process Manager"]
        SUPERVISOR --> WORKER["Queue Worker (artisan queue:work)"]
        SUPERVISOR --> REVERB
        
        CRON["Linux Crontab"] -->|Setiap Menit| ARTISAN_SCHED["artisan schedule:run"]
    end
```

---

## 3. Langkah-Langkah Instalasi (Ubuntu / Debian Linux)

### Langkah 1: Pembaruan Sistem & Instalasi Paket Pendukung

Jalankan perintah berikut via SSH terminal sebagai user `root` atau pengguna dengan hak akses `sudo`:

```bash
# Update repository index dan paket sistem
sudo apt update && sudo apt upgrade -y

# Pasang utilitas dasar, firewall, git, curl, unzip
sudo apt install -y software-properties-common curl wget git unzip zip ufw htop supervisor certbot python3-certbot-nginx
```

Aktifkan firewall UFW:
```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw --force enable
```

---

### Langkah 2: Instalasi PHP 8.3 & Ekstensi yang Dibutuhkan

Aplikasi POS ini menggunakan **PHP 8.3+**. Pasang repository PPA resmi Ondřej Surý:

```bash
# Tambahkan repository PHP
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Pasang PHP 8.3 CLI, FPM, dan seluruh ekstensi wajib
sudo apt install -y php8.3-fpm php8.3-cli php8.3-common php8.3-pgsql php8.3-sqlite3 \
php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip php8.3-bcmath php8.3-intl \
php8.3-readline php8.3-redis php8.3-gd

# Verifikasi versi PHP terpasang
php -v
```

Pasang **Composer 2.x** (Dependency Manager PHP):
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer --version
```

Pasang **Node.js 20.x & NPM**:
```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
node -v
npm -v
```

---

### Langkah 3: Instalasi & Konfigurasi PostgreSQL 15+

Pasang PostgreSQL:
```bash
sudo apt install -y postgresql postgresql-contrib

# Pastikan PostgreSQL berjalan dan autostart
sudo systemctl enable postgresql
sudo systemctl start postgresql
```

Buat database dan pengguna (*user*) untuk aplikasi POS:
```bash
# Masuk ke prompt postgres
sudo -u postgres psql
```

Ketikkan perintah SQL berikut (ganti `PasswordKuatDB123!` dengan password rahasia Anda):
```sql
CREATE DATABASE pos_kasir_ilp;
CREATE USER pos_user WITH ENCRYPTED PASSWORD 'PasswordKuatDB123!';
GRANT ALL PRIVILEGES ON DATABASE pos_kasir_ilp TO pos_user;
ALTER DATABASE pos_kasir_ilp OWNER TO pos_user;

-- Sambungkan ke database dan berikan hak akses schema public
\c pos_kasir_ilp
GRANT ALL ON SCHEMA public TO pos_user;
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO pos_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO pos_user;
\q
```

---

### Langkah 4: Unduh Repositori Kode Aplikasi

Direktori standar aplikasi web di Ubuntu adalah `/var/www/pos_kasir_ilp`:

```bash
# Clone repository
sudo git clone https://github.com/Malvi04/POS_KASIR_ILP.git /var/www/pos_kasir_ilp
cd /var/www/pos_kasir_ilp

# Berikan kepemilikan direktori sementara kepada user aktif
sudo chown -R $USER:www-data /var/www/pos_kasir_ilp
```

---

### Langkah 5: Konfigurasi File Environment (.env)

Salin template `.env.example` ke `.env`:
```bash
cp .env.example .env
nano .env
```

Pastikan variabel-variabel kunci berikut disetel dengan tepat:

```env
APP_NAME="POS Minimarket PT ILP"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ilppos.cloudlink.co.id

LOG_CHANNEL=daily
LOG_LEVEL=info

# Konfigurasi Database PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pos_kasir_ilp
DB_USERNAME=pos_user
DB_PASSWORD=PasswordKuatDB123!
DB_SSLMODE=disable

# Session & Cache
SESSION_DRIVER=database
SESSION_LIFETIME=120
CACHE_STORE=database
QUEUE_CONNECTION=database

# Broadcasting & WebSocket Reverb
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=100101
REVERB_APP_KEY=ilppos_reverb_key
REVERB_APP_SECRET=ilppos_reverb_secret
REVERB_HOST="0.0.0.0"
REVERB_PORT=8080
REVERB_SCHEME=http

# VITE Frontend Variables (Public)
VITE_APP_NAME="${APP_NAME}"
VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"
VITE_REVERB_HOST="ilppos.cloudlink.co.id"
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https

# ========================================================
# PENTING: Peran Server Pusat
# ========================================================
POS_NODE_ROLE=central
POS_BRANCH_CODE=PST
CENTRAL_API_URL=https://ilppos.cloudlink.co.id
```

Simpan file dengan menekan `Ctrl + O`, lalu `Enter`, dan keluar dengan `Ctrl + X`.

---

### Langkah 6: Instalasi Dependensi PHP & Kompilasi Aset Frontend

```bash
cd /var/www/pos_kasir_ilp

# Instalasi dependensi PHP tanpa paket pengujian developer
composer install --no-dev --optimize-autoloader

# Instalasi paket frontend NPM & kompilasi Tailwind CSS + Vite
npm ci
npm run build
```

---

### Langkah 7: Eksekusi Migrasi Database & Seeder Awal

```bash
# Buat application encryption key
php artisan key:generate --force

# Buat symbolic link storage
php artisan storage:link

# Jalankan seluruh migrasi database PostgreSQL
php artisan migrate --force

# (Opsional) Jalankan seeder akun dan data master default jika instalasi baru
php artisan db:seed --force

# Optimasi konfigurasi, routing, dan template Blade
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Atur izin akses (*permissions*) folder `storage` dan `bootstrap/cache`:
```bash
sudo chown -R www-data:www-data /var/www/pos_kasir_ilp/storage /var/www/pos_kasir_ilp/bootstrap/cache
sudo chmod -R 775 /var/www/pos_kasir_ilp/storage /var/www/pos_kasir_ilp/bootstrap/cache
```

---

### Langkah 8: Konfigurasi Web Server Nginx & SSL HTTPS

Buat file konfigurasi virtual host Nginx:
```bash
sudo nano /etc/nginx/sites-available/pos_kasir_ilp.conf
```

Tempelkan konfigurasi produksi berikut (ganti `ilppos.cloudlink.co.id` dengan domain Anda):

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name ilppos.cloudlink.co.id;

    root /var/www/pos_kasir_ilp/public;
    index index.php index.html;

    client_max_body_size 50M;

    # Gzip Compression
    gzip on;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Proxy WebSocket Laravel Reverb melalui /app
    location /app {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_cache_bypass $http_upgrade;
        proxy_read_timeout 60s;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

Aktifkan konfigurasi dan restart Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/pos_kasir_ilp.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

Pasang Sertifikat SSL Gratis (Let's Encrypt / Certbot):
```bash
sudo certbot --nginx -d ilppos.cloudlink.co.id
```

---

### Langkah 9: Konfigurasi Supervisor (Queue Worker & Reverb WebSocket)

Agar background queue worker dan Laravel Reverb berjalan terus-menerus dan otomatis menyala kembali jika crash atau server restart, gunakan **Supervisor**.

Buat file konfigurasi supervisor:
```bash
sudo nano /etc/supervisor/conf.d/pos_kasir_ilp.conf
```

Tempelkan isi berikut:

```ini
[program:pos-queue-worker]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php /var/www/pos_kasir_ilp/artisan queue:work database --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/pos_kasir_ilp/storage/logs/queue-worker.log
stopwaitsecs=3600

[program:pos-reverb]
command=/usr/bin/php /var/www/pos_kasir_ilp/artisan reverb:start --host="0.0.0.0" --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/pos_kasir_ilp/storage/logs/reverb.log
```

Muat dan jalankan service di Supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start all
sudo supervisorctl status
```

---

### Langkah 10: Setup Cron Job Penjadwal Tugas Laravel

Laravel Scheduler mengontrol backup data berkala, penutupan otomatis sesi usang, dan evaluasi berkala. Buka crontab www-data:

```bash
sudo crontab -u www-data -e
```

Tambahkan baris berikut di akhir file:
```cron
* * * * * cd /var/www/pos_kasir_ilp && php artisan schedule:run >> /dev/null 2>&1
```

---

## 4. Prosedur Pendaftaran Terminal Kasir Baru (pos:create-terminal)

Setiap mesin PC kasir fisik di toko harus didaftarkan terlebih dahulu di Server Pusat untuk mendapatkan **API Token Khusus Terminal**.

Jalankan perintah ini di terminal Server Pusat:

```bash
cd /var/www/pos_kasir_ilp
php artisan pos:create-terminal --branch=PST --code=KSR01 --name="Kasir 01 Toko Pusat"
```

> **Catatan Parameter:**
> - `--branch` : Kode cabang (misal: `PST` untuk Pusat, `CBG1` untuk Pabrik, `CBG2` untuk Kebun).
> - `--code` : Kode unik terminal kasir (misal: `KSR01`, `KSR02`, `POS-01`).
> - `--rotate` : (Opsional) Tambahkan opsi ini jika terminal sudah terdaftar tetapi tokennya hilang dan ingin di-generate ulang.

Output terminal akan mencetak tampilan seperti berikut:

```text
=== TERMINAL KASIR BERHASIL DIDAFTARKAN ===
+--------------------+------------------------------------------------+
| Atribut            | Nilai                                          |
+--------------------+------------------------------------------------+
| Terminal UUID      | 0192e21b-8521-7299-9764-f6eb2a1d9b32           |
| Cabang (Location)  | Toko Pusat (ID: 1, Code: PST)                  |
| Branch UUID        | 01a21406-26f0-73de-8278-bc3e25b607bd           |
| Kode Cabang Struk  | PST                                            |
| Kode Terminal      | KSR01                                          |
| Nama Terminal      | Kasir 01 Toko Pusat                            |
| Status Aktif       | AKTIF                                          |
+--------------------+------------------------------------------------+

PENTING: Salin Plain Token di bawah ini sekarang! 
Token asli TIDAK disimpan di database (hanya hash SHA-256 yang disimpan).

Plain API Token: pos_pst_ksr01_I34t0lLgyhNWI3BX1zIul3wGm9G2YEr7fuWRaP0xhrjI

# --- Salin konfigurasi berikut ke file .env pada PC Kasir Lokal ---
POS_NODE_ROLE=local
POS_BRANCH_UUID=01a21406-26f0-73de-8278-bc3e25b607bd
POS_BRANCH_CODE=PST
POS_TERMINAL_CODE=KSR01
CENTRAL_API_URL=https://ilppos.cloudlink.co.id
CENTRAL_API_TOKEN=pos_pst_ksr01_I34t0lLgyhNWI3BX1zIul3wGm9G2YEr7fuWRaP0xhrjI
```

> ⚠️ **PENTING**: Simpan teks konfigurasi di atas untuk diisikan pada file `.env` di PC kasir fisik yang bersangkutan.

---

## 5. Pengujian & Verifikasi Kesiapan Server

Lakukan pengecekan kesehatan server menggunakan curl:

```bash
# 1. Cek Uptime & Health DB
curl -k https://ilppos.cloudlink.co.id/api/health
# Output yang diharapkan: {"status":"healthy","database":"connected","timestamp":...}

# 2. Cek Latensi Ping (<10ms)
curl -k https://ilppos.cloudlink.co.id/api/ping
# Output yang diharapkan: {"status":"pong","t":1732684982.123}

# 3. Verifikasi Token Terminal Kasir
curl -k -H "Authorization: Bearer pos_pst_ksr01_I34t0lLgyhNWI3BX1zIul3wGm9G2YEr7fuWRaP0xhrjI" \
https://ilppos.cloudlink.co.id/api/v1/sync/categories
# Output yang diharapkan: Berhasil mengembalikan JSON array kategori produk
```

---

## 6. Pemeliharaan, Backup Otomatis & Troubleshooting

### Script Backup Database Harian Otomatis

Buat script backup di `/usr/local/bin/backup_pos_db.sh`:
```bash
sudo nano /usr/local/bin/backup_pos_db.sh
```

Isi dengan script berikut:
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/pos_db"
DATE=$(date +'%Y%m%d_%H%M%S')
mkdir -p $BACKUP_DIR

# Dump database PostgreSQL terkompresi
PGPASSWORD="PasswordKuatDB123!" pg_dump -U pos_user -h 127.0.0.1 pos_kasir_ilp | gzip > "$BACKUP_DIR/pos_db_$DATE.sql.gz"

# Hapus backup yang lebih tua dari 30 hari
find $BACKUP_DIR -type f -name "*.sql.gz" -mtime +30 -exec rm {} \;
```

Berikan izin eksekusi:
```bash
sudo chmod +x /usr/local/bin/backup_pos_db.sh
```

Daftarkan ke crontab root agar berjalan setiap jam 02:00 pagi:
```bash
sudo crontab -e
# Tambahkan:
0 2 * * * /usr/local/bin/backup_pos_db.sh > /dev/null 2>&1
```

### Panduan Troubleshooting Cepat Server Pusat:

1. **Error 502 Bad Gateway**:
   - Periksa status PHP-FPM: `sudo systemctl status php8.3-fpm`
   - Restart jika mati: `sudo systemctl restart php8.3-fpm`
2. **Database Connection Refused**:
   - Periksa status PostgreSQL: `sudo systemctl status postgresql`
   - Cek log PostgreSQL: `sudo tail -n 50 /var/log/postgresql/postgresql-15-main.log`
3. **Queue atau WebSocket Notifikasi Tidak Berjalan**:
   - Cek status supervisor: `sudo supervisorctl status`
   - Restart proses: `sudo supervisorctl restart all`
   - Cek log Reverb: `tail -n 50 /var/www/pos_kasir_ilp/storage/logs/reverb.log`
4. **Token Terminal Ditolak (401 Unauthorized)**:
   - Pastikan token di `.env` PC kasir sama persis dengan yang digenerate oleh `php artisan pos:create-terminal`.
   - Jika ragu, regenerasi token dengan perintah: `php artisan pos:create-terminal --branch=PST --code=KSR01 --rotate`
