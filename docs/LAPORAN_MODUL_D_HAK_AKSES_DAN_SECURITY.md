# LAPORAN IMPLEMENTASI MODUL D: MANAJEMEN HAK AKSES PENGGUNA (USER ACCESS SECURITY)

**Aplikasi:** POS Minimarket Koperasi Multi-Cabang PT Indo Lampung Perkasa  
**Modul:** Modul D — Manajemen Hak Akses Pengguna (User Access Security)  
**Arsitektur:** RESTful API Backend (Laravel 11 + PostgreSQL) & Decoupled Reactive Frontend (Blade + Tailwind CSS + Alpine.js)  
**Tanggal Rilis:** 1 November 2026  
**Status Uji:** 100% Passed (29 Tests, 208 Assertions)

---

## 1. Executive Summary

Modul D (**Manajemen Hak Akses Pengguna / User Access Security**) telah selesai diimplementasikan secara menyeluruh dengan memisahkan logika backend dan tampilan antarmuka frontend, di mana seluruh komunikasi data berjalan melalui protokol **RESTful API** berbasis JSON. 

Sistem ini menerapkan **Role-Based Access Control (RBAC)** berstandar keamanan tinggi dengan pembagian hak akses (*privileges*) tegas antara **Kasir**, **Supervisor / Manager**, dan **Owner / Superadmin**, dilengkapi pencegahan fraud transaksi, otorisasi PIN 6-digit, koreksi stok harian, pengaturan perpajakan (PPN 11%/12%), serta konsolidasi laporan laba/rugi (P&L) 3 cabang secara real-time yang independen dari aktivitas shift kasir harian.

---

## 2. Matriks Hak Akses Sistem (Security & Privilege Matrix)

| Role Pengguna | Batas Hak Akses Sistem (Privileges) | Status Otorisasi |
| :--- | :--- | :--- |
| **Kasir** | <ul><li>Input modal awal shift kasir (*Opening Cash*) sebelum transaksi.</li><li>Transaksi penjualan reguler (Tunai, QRIS, Kasbon Anggota).</li><li>Fitur Hold Bill & Recall Bill transaksi antrean.</li><li>Cetak ulang struk faktur penjualan belanja.</li><li>Buat laporan penutupan kasir harian (*Shift Closing & Variance Audit*).</li><li><strong style="color:red">Tidak bisa Void transaksi/item tanpa persetujuan Supervisor/Manager.</strong></li></ul> | Operasional Terbatas (*Anti-Fraud Enforced*) |
| **Supervisor / Manager** | <ul><li>Seluruh fungsi dan hak akses kasir.</li><li>Otorisasi Void / Pembatalan transaksi & item keranjang (Validasi PIN 6-digit).</li><li>Input dan otorisasi Diskon Manual kasir (dengan batasan persentase/nominal).</li><li>Koreksi Stok Harian Cabang (Pencatatan barang rusak, kadaluwarsa, tumpah, selisih fisik).</li><li>Manajemen dan verifikasi Stock Opname Cabang (*Variance Reconciliation*).</li></ul> | Operasional Cabang & Manajerial |
| **Owner / Superadmin** | <ul><li><strong>Hak Akses Penuh (Full Control):</strong></li><li>Master Data Barang (Tambah, ubah, hapus produk & multi-satuan unit).</li><li>Pengaturan Harga Jual, Harga Beli (HPP), dan Markup grosir/eceran.</li><li>Manajemen Plafon Kredit Kasbon Anggota & Eksekusi Cutoff Payroll HRD.</li><li><strong>Konsolidasi Laporan 3 Cabang</strong> (Pusat, Cabang 1 - Mill, Cabang 2 - Kebun Barat).</li><li>Pengaturan Pajak (Tarif PPN 11%/12%, metode include/exclude, NPWP toko).</li><li>User Management (Kelola akun Kasir, SPV, Manager, Owner, reset PIN & password).</li><li><strong>Melihat laporan laba/rugi (P&L), performa penjualan seluruh cabang secara real-time</strong>, memantau mutasi stok, dan laporan piutang anggota <em>tanpa terhubung dengan aktivitas operasional kasir harian</em>.</li></ul> | Akses Tertinggi Eksekutif (*Full Control*) |

---

## 3. Spesifikasi Arsitektur Backend API Modul D

Seluruh endpoint backend dirancang menggunakan arsitektur RESTful JSON dengan optimasi query SQL pada PostgreSQL:

### A. Endpoint Manajemen Pengguna (`/api/users`)
- `GET /api/users`: Mengambil daftar pengguna dengan filter dinamis `role` (Kasir, Supervisor, Manager, Owner), `branch`, `status`, dan keyword search.
- `POST /api/users`: Mendaftarkan staf baru dengan validasi username unik, NIK terverifikasi, peran, cabang penugasan, PIN numerik 6 digit terenkripsi, dan password.
- `GET /api/users/{id}`: Mengambil rincian profil pengguna spesifik.
- `PUT /api/users/{id}`: Memperbarui data profil, cabang, hak akses, atau kata sandi.
- `DELETE /api/users/{id}`: Menghapus akun pengguna dengan proteksi keamanan (*safeguard*: tidak dapat menghapus satu-satunya akun Owner aktif sistem).
- `PUT /api/users/{id}/pin`: Memperbarui atau mereset PIN otorisasi 6 digit.
- `POST /api/users/{id}/toggle-status`: Mengaktifkan atau menonaktifkan akun staf secara instan.

### B. Endpoint Pengaturan Sistem & Perpajakan (`/api/settings`)
- `GET /api/settings`: Mengambil seluruh konfigurasi terpusat (Profil toko, PPN, dan aturan keamanan PIN).
- `PUT /api/settings/tax`: Memperbarui tarif PPN (contoh: 11% atau 12%), saklar status pajak aktif, dan metode perhitungan (*include/exclude*).
- `PUT /api/settings/store`: Memperbarui identitas cabang pusat, alamat, telepon, dan NPWP toko.
- `PUT /api/settings/security`: Mengonfigurasi kewajiban verifikasi PIN Supervisor untuk Void dan diskon manual.

### C. Endpoint Konsolidasi 3 Cabang & P&L Real-Time (`/api/reports/consolidated-branches`)
- `GET /api/reports/consolidated-branches`:
  - **Independensi Kasir:** Dapat diakses kapan saja oleh Owner tanpa memerlukan shift kasir terbuka atau tertutup.
  - **Financial Summary P&L:** Menghitung real-time *Gross Revenue*, Biaya Pokok Penjualan (*Total COGS / HPP*), Laba Kotor (*Gross Profit*), Total Diskon, Pajak PPN, dan Laba Bersih (*Net Profit*) beserta rasio margin keuntungan (%).
  - **Member Debt Overview:** Rekapitulasi total piutang anggota yang belum dipotong gaji di seluruh 3 cabang.
  - **Valuasi Aset Stok:** Valuasi total nilai aset barang berdasarkan harga modal (*at cost*) vs potensi omzet (*at retail*), beserta deteksi dini stok menipis dan barang kadaluwarsa.
  - **Matriks Komparasi 3 Cabang:** Perbandingan omzet, jumlah transaksi, laba kotor, piutang, dan status cabang antara Minimarket Pusat, Cabang 1 (Mill / Pabrik), dan Cabang 2 (Kebun Barat).
  - **Audit Mutasi Stok:** Riwayat penyesuaian stok rusak/kadaluwarsa oleh Supervisor.

### D. Endpoint Otorisasi Supervisor & Diskon Manual
- `POST /api/pos/void/authorize`: Validasi kredensial PIN Supervisor/Manager. **Secara otomatis menolak PIN milik Kasir** dengan pesan penolakan khusus (*"Akun Kasir tidak memiliki wewenang untuk Void"*).
- `POST /api/pos/void/item`: Mencatat log pembatalan item belanja keranjang aktif dengan otorisasi supervisor.
- `POST /api/pos/void/transaction`: Membatalkan transaksi tervalidasi dengan pemulihan stok dan saldo kredit kasbon.
- `POST /api/pos/discount/authorize`: Validasi PIN Supervisor untuk pemberian diskon manual kasir dengan batas toleransi persentase.
- `POST /api/inventory/stock-corrections`: Supervisor mencatat koreksi harian barang rusak/kadaluwarsa dengan kalkulasi otomatis ke stok fisik produk.

---

## 4. Implementasi Frontend & User Interface

Tiga tampilan antarmuka manajerial telah diperbarui dan dihubungkan secara reaktif:

1. `resources/views/manager/kelola-akun.blade.php`:
   - Menghubungkan seluruh proses CRUD pengguna ke endpoint `/api/users`.
   - Menampilkan kartu visual **Matriks Batas Hak Akses Sistem (Modul D Privileges)** di bagian atas halaman sebagai panduan operasional.
   - Mengintegrasikan penugasan kasir langsung dari basis data **Members** koperasi.
   - Badge warna role yang kontras: Kasir (*Sky*), Supervisor (*Amber*), Manager (*Indigo*), Owner (*Purple*).

2. `resources/views/manager/settings.blade.php`:
   - Penambahan Tab khusus: **Pengaturan Pajak (PPN)** berstatus Akses Owner.
   - Menyediakan form konfigurasi tarif PPN (11% / 12%), saklar pemungutan PPN pada POS, metode perhitungan (Include / Exclude), dan input NPWP Toko.
   - Seluruh pengaturan tersimpan persisten ke tabel `app_settings` via `/api/settings/tax`.

3. `resources/views/manager/reports.blade.php`:
   - Penambahan Tab navigasi eksekutif: **Konsolidasi 3 Cabang & P&L (Akses Owner)**.
   - Menyajikan 4 KPI Utama Real-time: Omzet Kotor, HPP, Laba Bersih (+ Margin %), dan Valuasi Aset Stok.
   - Menampilkan tabel komparasi kinerja 3 cabang (Pusat, Cabang 1, Cabang 2) serta tabel audit koreksi stok harian supervisor.
   - Data terhubung langsung ke API `/api/reports/consolidated-branches`.

---

## 5. Hasil Pengujian Fitur (Feature & Performance Tests)

Pengujian otomatis dilakukan menggunakan framework **Pest / PHPUnit** mencakup seluruh modul:

```
PASS  Tests\Feature\PosKasirApiTest (8 tests, 58 assertions)
PASS  Tests\Feature\MemberDebtModulBTest (8 tests, 56 assertions)
PASS  Tests\Feature\InventoryConsignmentModulCTest (6 tests, 47 assertions)
PASS  Tests\Feature\UserAccessSecurityModulDTest (7 tests, 47 assertions)
  ✓ cashier cannot authorize void with cashier pin
  ✓ supervisor can authorize void with supervisor pin
  ✓ supervisor can authorize manual discount
  ✓ supervisor can execute daily stock correction
  ✓ owner can manage users crud
  ✓ owner can configure tax settings
  ✓ owner can view consolidated 3 branch performance

Tests:    29 passed (208 assertions)
Duration: 8.73s
Vite:     ✓ built in 3.30s (public/build/manifest.json)
```

Semua 29 unit skenario pengujian lulus 100% tanpa ada kesalahan regresi.
