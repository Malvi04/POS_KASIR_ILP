# 🛒 POS Minimarket ILP (Point of Sale & Retail Management System)

[![Laravel](https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white)](https://laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3.x-38B2AC?style=for-the-badge&logo=tailwind-css&logoColor=white)](https://tailwindcss.com)
[![Alpine.js](https://img.shields.io/badge/Alpine.js-3.x-8BC0D0?style=for-the-badge&logo=alpinedotjs&logoColor=white)](https://alpinejs.dev)
[![SQLite](https://img.shields.io/badge/SQLite-003B57?style=for-the-badge&logo=sqlite&logoColor=white)](https://sqlite.org)
[![Status](https://img.shields.io/badge/Status-Production--Ready%20UI-success?style=for-the-badge)](#)

Sistem Kasir (Point of Sale) dan Manajemen Retail Koperasi Minimarket ILP berbasis web modern yang dirancang untuk kecepatan transaksi kasir di lini depan serta kemudahan kontrol operasional, inventori, dan kemitraan UMKM di lini manajerial.

---

## 📑 Daftar Isi
- [Ringkasan Pembaruan Terkini](#-ringkasan-pembaruan-terkini)
- [Fitur Utama Sistem](#-fitur-utama-sistem)
  - [1. Modul Kasir (Cashier POS Frontline)](#1-modul-kasir-cashier-pos-frontline)
  - [2. Modul Manajer (Manager & Store Operations)](#2-modul-manajer-manager--store-operations)
  - [3. Otorisasi Owner (Strategic PIN Pad)](#3-otorisasi-owner-strategic-pin-pad)
- [Peta Rute & Navigasi (Route Map)](#-peta-rute--navigasi-route-map)
- [Kredensial Akun Pengujian (Demo Accounts)](#-kredensial-akun-pengujian-demo-accounts)
- [Panduan Instalasi & Menjalankan](#-panduan-instalasi--menjalankan)
- [Struktur Direktori Proyek](#-struktur-direktori-proyek)
- [Teknologi yang Digunakan](#-teknologi-yang-digunakan)

---

## 🚀 Ringkasan Pembaruan Terkini

Update sistem mencakup penyempurnaan menyeluruh pada alur operasional kasir, pemisahan hak akses, manajemen sesi, dan modul manajerial:

### 1. Sistem Sesi Kasir & Handover Laci Kas (Cash Handover Wizard)
- **Wizard Tutup Sesi & Serah Terima Terpadu**: Alur 3-langkah verifikasi penutupan sesi kasir:
  1. *Ringkasan Sistem*: Komparasi otomatis modal awal, penjualan tunai bersih, non-tunai (QRIS, Kartu Debit/Kredit, Tempo Anggota), dan arus kas laci (*cash in/out*).
  2. *Kalkulator Fisik Laci (Denomination Calculator)*: Penghitungan otomatis nominal fisik laci per pecahan lembar (Rp 100.000, Rp 50.000, Rp 20.000, Rp 10.000, Rp 5.000, Rp 2.000, Rp 1.000) dan koin (Rp 1.000, Rp 500, Rp 200, Rp 100).
  3. *Analisis Selisih & Alokasi Modal Lanjutan*: Otomatisasi status selisih kas (*Sesuai*, *Selisih Kurang*, atau *Selisih Lebih*), form catatan review manajer, dan pembagian saldo fisik menjadi modal kasir shift berikutnya (*Handover*) serta setoran brankas (*Drop*).
- **Resumption Sesi Kasir Aktif**: Kasir yang belum menutup sesi dapat langsung melanjutkan transaksi tanpa penginputan ulang modal buka.
- **Pencatatan Arus Kas Laci (*Cash Movement*)**: Menu cepat pencatatan kas masuk (*Cash In*) dan kas keluar operasional (*Cash Out / Petty Cash*) dengan alasan transaksi langsung di laci kasir.

### 2. Mode Offline-First Kasir
- **Indikator Status Jaringan Real-Time**: Status `Online` vs `Offline` dengan toggle pengujian.
- **Antrean Sinkronisasi (*Pending Sync Queue*)**: Transaksi penjualan offline tersimpan di penyimpanan lokal (*LocalStorage*) dan disinkronkan saat terhubung kembali.

### 3. Pemisahan Hak Akses & Navigasi Rute (Cashier vs Manager Routing)
- **Isolasi Rute Kasir**: Rute khusus kasir di bawah prefix `/kasir/*` (`/kasir/sesi`, `/kasir/inventory`, `/kasir/members`, `/kasir/stock-opname`, `/kasir/retur`, `/kasir/reports`, `/kasir/settings`).
- **Sidebar & Header Mandiri**: Komponen `<x-cashier-sidebar>` dan `<x-cashier-header>` yang disederhanakan khusus transaksi kasir, terpisah dari navigation bar manajer.

### 4. Modul Kemitraan UMKM (UMKM Partners)
- Pengelolaan mitra produk lokal UMKM dengan sistem konsinyasi (*titip jual*), persentase bagi hasil koperasi & mitra, batas jatuh tempo pembayaran, dan pencatatan retur produk konsinyasi.

### 5. Modul Promosi & Kupon Diskon
- Konfigurasi promo diskon persentase, nominal tetap, *Buy 1 Get 1*, batas minimum belanja, kuota kupon, dan integrasi kode voucher ke keranjang belanja kasir.

---

## 🌟 Fitur Utama Sistem

### 1. Modul Kasir (Cashier POS Frontline)
* **Pilihan Terminal Fleksibel**: Mendukung pemilihan hingga 12 terminal kasir (`Kasir 01` s/d `Kasir 12`) dengan status visual (`TERSEDIA`, `PERLU SERAH TERIMA`, `SEDANG DIGUNAKAN`).
* **Transaksi Kasir Super Cepat**:
  * Input barcode scanner atau pencarian nama / SKU produk instan.
  * Filter kategori produk cepat (Makanan, Minuman, Sembako, Kebutuhan Rumah Tangga, UMKM).
  * Hold & Resume transaksi (*Simpan Transaksi Sementara* saat pelanggan mengambil barang tambahan).
  * Kalkulasi diskon item, diskon kupon, dan PPN 11% otomatis.
* **Multi-Metode Pembayaran**:
  * Tunai (dengan kalkulator uang kembalian dan tombol nominal pecahan cepat).
  * QRIS Dinamis & Statis dengan status konfirmasi otomatis.
  * Kartu Debit / Kredit (EDC).
  * Piutang Belanja Anggota Koperasi (validasi kuota/limit kredit belanja).
  * Pembayaran parsial (*Split Payment*).
* **Cetak Struk Thermal**: Format cetak struk siap pakai untuk printer thermal 58mm dan 80mm.
* **Katalog & Cek Stok Kasir**: Pengecekan lokasi rak, ketersediaan sisa stok, dan harga produk.
* **Direktori Member Kasir**: Input cepat data member via nomor telepon / NIK untuk poin belanja dan harga khusus anggota.
* **Retur Kasir**: Pengajuan retur barang pelanggan dengan alasan cacat produk, kadaluarsa, atau salah beli, disertai cetak bukti nota retur.

### 2. Modul Manajer (Manager & Store Operations)
* **Dashboard Analitik**:
  * Ringkasan KPI penjualan harian, mingguan, dan bulanan.
  * Grafik omzet penjualan, laba kotor, dan jumlah transaksi.
  * Daftar produk terlaris (*Top 5 Best Seller*) dan produk menipis (*Low Stock Alert*).
* **Manajemen Inventori Penuh**:
  * Tambah, edit, dan hapus data barang lengkap (SKU, Barcode, Kategori, Satuan, HPP, Harga Umum, Harga Member, Stok Minimum).
  * Cetak label barcode rak.
* **Stock Opname Manager**:
  * Penjadwalan audit fisik barang.
  * Perhitungan deviasi selisih stok fisik vs stok sistem (*loss/shrinkage*).
  * Otorisasi dan persetujuan penyesuaian (*adjustment*) stok.
* **Pengelolaan Member & Simpan Pinjam**:
  * Pendaftaran member koperasi baru, penetapan plafon piutang kredit belanja, histori transaksi, dan poin reward.
* **Kemitraan Mitra UMKM**:
  * Database supplier & mitra UMKM lokal.
  * Perhitungan bagi hasil konsinyasi dan histori pembayaran mitra.
* **Laporan Finansial Komprehensif**:
  * Laporan rekapitulasi penjualan kasir per shift.
  * Laporan laba rugi kotor per kategori produk.
  * Ekspor laporan ke format Excel / CSV / PDF.
* **Kelola Akun & Karyawan**:
  * Manajemen data kasir, supervisor, dan manajer.
  * Pengaturan NIK, PIN kasir, dan reset kata sandi akun.
* **Absensi Karyawan**:
  * Pencatatan kehadiran dan shift tugas kasir.
* **Pengaturan Toko**:
  * Konfigurasi identitas minimarket, NPWP, alamat, tarif PPN, header/footer struk belanja, dan integrasi hardware (printer, laci uang otomatis, barcode scanner).

### 3. Otorisasi Owner (Strategic PIN Pad)
* Akses cepat untuk pimpinan / pemilik toko menggunakan 6-digit numeric PIN pad terenkripsi.
* Akses ringkasan eksekutif strategis dan audit operasional toko.

---

## 🗺️ Peta Rute & Navigasi (Route Map)

### Halaman Akses & Autentikasi
| URL | Peran | Deskripsi |
|---|---|---|
| `/` | Semua | Halaman selamat datang & pemilih peran (Kasir / Manager / Owner) |
| `/login-kasir` | Kasir | Shortcut login akun kasir & PIN |
| `/login-manager` | Manager | Shortcut login kredensial manajer |
| `/select-shift` | Kasir | Seleksi 12 terminal kasir, petugas, modal laci, & buka sesi |

### Modul Kasir (Cashier Frontline)
| URL | Nama Halaman | Deskripsi |
|---|---|---|
| `/pos` | Terminal Transaksi POS | Layar utama kasir untuk kasir melayani transaksi belanja |
| `/kasir/sesi` | Sesi & Laci Kasir | Status sesi kasir, mutasi kas, handover wizard, & offline sync |
| `/kasir/inventory` | Cek Stok & Katalog Kasir | Pencarian produk, cek rak, dan harga jual |
| `/kasir/members` | Data Anggota Kasir | Verifikasi member koperasi dan kuota kredit belanja |
| `/kasir/stock-opname` | Opname Kasir | Audit stok fisik harian rak toko |
| `/kasir/retur` | Retur Kasir | Pengajuan & cetak nota retur barang pelanggan |
| `/kasir/reports` | Laporan Kasir | Ringkasan rekapitulasi transaksi shift berjalan |
| `/kasir/settings` | Pengaturan Terminal | Kalibrasi printer struk thermal, cash drawer, & scanner |
| `/cetak-struk` | Preview Struk | Layar cetak nota struk kasir |
| `/proses-kredit` | Proses Kredit | Validasi pembayaran kredit/tempo anggota |

### Modul Manajer (Manager & Operations)
| URL | Nama Halaman | Deskripsi |
|---|---|---|
| `/dashboard` | Dashboard Utama | Ringkasan KPI omzet, laba kotor, dan analitik toko |
| `/inventory` | Manajemen Inventori | Pengelolaan master data produk, SKU, HPP, & supplier |
| `/stock-opname` | Stock Opname Manager | Validasi dan approval penyesuaian selisih stok |
| `/members` | Manajemen Member | Kelola anggota koperasi dan limit kredit belanja |
| `/member-detail` | Rincian Member | Profil anggota, riwayat transaksi, dan poin loyalitas |
| `/promosi` | Promosi & Diskon | Pengaturan kampanye diskon belanja toko |
| `/kupon` | Kupon Belanja | Manajemen voucher & kode diskon |
| `/retur` | Manajemen Retur | Verifikasi pengembalian produk & klaim supplier |
| `/umkm-partners` | Mitra UMKM | Pengelolaan konsinyasi produk lokal & bagi hasil |
| `/reports` | Laporan Penjualan | Laporan finansial mendalam dan ekspor data |
| `/kelola-akun` | Kelola Akun Petugas | Pengaturan akun kasir, role, dan hak akses |
| `/absensi` | Monitoring Absensi | Log kehadiran dan shift kerja kasir |
| `/settings` | Pengaturan Toko | Pengaturan profil toko, pajak PPN, dan backup sistem |

---

## 👥 Kredensial Akun Pengujian (Demo Accounts)

Untuk mempermudah pengujian dan demonstrasi sistem, gunakan kredensial bawaan berikut:

### 1. Akun Petugas Kasir (Pilih Petugas di Layar Login)
| Nama Petugas | NIK | Username | PIN Bawaan | Role |
|---|---|---|---|---|
| **Budi Kurniawan** | `202409821002` | `budi_kasir01` | `123456` | Kasir Utama |
| **Andi Pratama** | `202409821001` | `andi_kasir` | `123456` | Kasir Operasional |
| **Citra Dewi** | `202409821003` | `citra_kasir` | `123456` | Kasir Operasional |
| **Dedi Santoso** | `202409821004` | `dedi_kasir` | `123456` | Kasir Operasional |

### 2. Akun Manajer
* **Username**: `andi_manager`
* **Kata Sandi**: `password`

### 3. Akun Pemilik Toko (Owner)
* **PIN Otorisasi 6-Digit**: `123456`

---

## 💻 Panduan Instalasi & Menjalankan

### Prasyarat Sistem
- **PHP**: Versi `>= 8.2` (dengan ekstensi `pdo_sqlite`, `mbstring`, `openssl`, `curl`)
- **Composer**: Versi `>= 2.0`
- **Node.js**: Versi `>= 18.x` & **NPM**: Versi `>= 9.x`

### Langkah-langkah Instalasi

1. **Clone Repositori**:
   ```bash
   git clone https://github.com/Malvi04/POS_KASIR_ILP.git
   cd POS_KASIR_ILP
   ```

2. **Instalasi Dependensi PHP**:
   ```bash
   composer install
   ```

3. **Instalasi Dependensi Node.js**:
   ```bash
   npm install
   ```

4. **Konfigurasi Environment**:
   Salin file konfigurasi `.env`:
   ```bash
   cp .env.example .env
   ```
   Pastikan pengaturan database menggunakan SQLite:
   ```env
   DB_CONNECTION=sqlite
   DB_DATABASE=pos_kasir_ilp
   ```

5. **Generate Application Key**:
   ```bash
   php artisan key:generate
   ```

6. **Migrasi Database**:
   ```bash
   php artisan migrate
   ```

7. **Kompilasi Aset Frontend (Tailwind & Vite)**:
   ```bash
   npm run build
   # Atau untuk mode hot-reload selama pengembangan:
   # npm run dev
   ```

8. **Jalankan Server Lokal**:
   ```bash
   php artisan serve
   ```
   Aplikasi dapat diakses melalui browser di: `http://127.0.0.1:8000`

---

## 📁 Struktur Direktori Proyek

```text
POS_KASIR_ILP/
├── app/                        # Kode inti aplikasi Laravel (Controllers, Models, Providers)
├── database/                   # Migrasi, factory, dan seeder database
│   └── migrations/             # Skema tabel database
├── public/                     # Aset publik terkompilasi (CSS, JS, Fonts, Images)
├── resources/
│   ├── css/                    # Stylesheet aplikasi (Tailwind CSS)
│   ├── js/                     # Skrip JavaScript & Vite bundle
│   └── views/                  # Template Blade & Antarmuka Aplikasi
│       ├── components/         # Komponen reusable (cashier-sidebar, manager-sidebar, cashier-header)
│       ├── kasir/
│       │   └── partials/       # Modal penutupan sesi kasir (_closing-handover-modal, _opening-modal, dsb)
│       ├── layouts/            # Layout utama aplikasi (app.blade.php) & state Alpine.js
│       ├── pos.blade.php       # Antarmuka kasir utama
│       ├── select-shift.blade.php # Seleksi terminal kasir & modal awal
│       ├── cashier-session.blade.php # Layar manajemen sesi kasir
│       ├── dashboard.blade.php # Dashboard manajer
│       ├── inventory.blade.php # Manajemen inventori manajer
│       ├── umkm-partners.blade.php # Kemitraan UMKM & Konsinyasi
│       └── welcome.blade.php   # Portal login multi-role
├── routes/
│   └── web.php                 # Definisi seluruh rute aplikasi
├── .env.example                # Templat konfigurasi environment
├── package.json                # Dependensi frontend & script Vite
├── composer.json               # Dependensi framework Laravel & library PHP
└── README.md                   # Dokumentasi resmi proyek
```

---

## 🛠️ Teknologi yang Digunakan

* **Backend Framework**: [Laravel 11](https://laravel.com/)
* **CSS Framework**: [Tailwind CSS 3](https://tailwindcss.com/)
* **Reactivity & Client State**: [Alpine.js 3](https://alpinejs.dev/)
* **Icons & Typografi**: [Google Material Symbols Rounded](https://fonts.google.com/icons) & [Instrument Sans Font](https://fonts.google.com/specimen/Instrument+Sans)
* **Database**: [SQLite 3](https://www.sqlite.org/)
* **Build Tooling**: [Vite 8](https://vitejs.dev/)

---

## 📄 Lisensi

Proyek ini dikembangkan untuk Minimarket ILP. Seluruh hak cipta dilindungi undang-undang.
