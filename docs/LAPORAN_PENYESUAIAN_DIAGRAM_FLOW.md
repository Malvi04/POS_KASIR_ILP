# Laporan Penyelarasan & Pembaruan Diagram Alur (Activity & Sequence Diagrams)
**Sistem POS Minimarket Koperasi Multi-Cabang PT Indo Lampung Perkasa**  
*Tanggal: 08 November 2026*

---

## 1. Ringkasan Eksekutif

Diagram alur sistem pada direktori:
- `c:\Users\ASUS\Downloads\Activity Diagram`
- `c:\Users\ASUS\Downloads\Sequence Diagram`

Telah diselaraskan dan disempurnakan secara menyeluruh agar mencerminkan **alur operasional riil (100% Flow Alignment)** yang diimplementasikan pada aplikasi **POS Minimarket Koperasi PT Indo Lampung Perkasa (ILP POS)**.

Sebelumnya, diagram alur hanya tersedia beberapa dan belum mencakup fitur-fitur penting seperti:
1. **Wizard Serah Terima Kas 3-Langkah (Denomination Calculator, Status Selisih Kas, Alokasi Handover/Drop)**.
2. **Transaksi Penjualan & Multi-Payment (QRIS, Kartu Debit/EDC, Kredit/Kasbon Anggota Koperasi Potong Gaji PT ILP, Split Payment, Auto-Discount Expired H-30 & H-14, Otorisasi Diskon Manual & Void PIN SPV)**.
3. **Mode Offline-First Kasir & Sinkronisasi Idempoten ke PostgreSQL**.
4. **Stock Opname Lengkap (Snapshot Sesi, Hitung Fisik Rak, Perhitungan Varians Otomatis, Rekonsiliasi, dan Approval Manager)**.
5. **Retur Barang & Pengembalian Dana (Refund Transaksi Pelanggan di Kasir & Retur Toko ke DC/Supplier)**.
6. **Penerimaan Barang Masuk (Goods Receipt / Transfer Masuk dari DC Cikarang)**.

---

## 2. Pemetaan & Penyelarasan Pasangan Diagram (Activity & Sequence)

| No | Modul Operasional | Activity Diagram (`.mmd`) | Sequence Diagram (`.mmd`) | Status Penyelarasan |
|---|---|---|---|---|
| **1** | **Buka Tutup Sesi & Serah Terima Kas** | `Activity Diagram Buka Tutup Serah Terima Kasir.mmd` | `Sequence_Diagram_Buka_Tutup_Sesi_dan_Serah_Terima_Kas(1).mmd` | **Diselaraskan 100%**: Mencakup validasi NIK/PIN kasir, status terminal (`AVAILABLE`, `OCCUPIED`, `HANDOVER_PENDING`), kalkulator pecahan uang (denominasi), arus kas laci (*petty cash in/out*), serta Wizard Tutup Sesi 3-Langkah. |
| **2** | **Transaksi Penjualan & Multi-Payment** | `Activity Transaksi Penjualan dan Pembayaran.mmd` | `Sequence_Diagram_Transaksi_Penjualan_dan_Pembayaran.mmd` | **Dibuat Baru & Lengkap**: Scan barcode, diskon otomatis expired (H-30 10%, H-14 25%), kupon/voucher BBM, otorisasi PIN SPV untuk Void & Diskon Manual, Hold/Recall bill, multi-payment (Tunai, QRIS, Debit, Kasbon Anggota, Campuran), pemotongan stok multi-satuan, dan cetak struk thermal 58mm/80mm. |
| **3** | **Mode Offline-First & Sinkronisasi** | `Activity_Diagram_Sinkronisasi_Offline_First(2).mmd` | `Sequence_Diagram_POS_Offline_dan_Sinkronisasi(1).mmd` | **Diselaraskan 100%**: Deteksi jaringan online/offline, penyimpanan antrean transaksi lokal (IndexedDB/LocalStorage) status `PENDING`, cetak struk offline, background auto-sync FIFO, validasi idempotensi server pusat, dan perubahan status menjadi `SYNCED`. |
| **4** | **Stok Opname Persediaan Toko** | `Activity Diagram Stok Opname.mmd` | `Sequence_Diagram_Stok_Opname.mmd` | **Dibuat Baru & Lengkap**: Pembuatan sesi opname & snapshot stok sistem (`SOP-YYYYMMDD-XXXX`), kasir scan & input hitungan fisik di rak, perhitungan varians otomatis (*Sesuai, Kurang, Lebih*), peninjauan manager, opsi hitung ulang (*Recount*), dan approval penyesuaian stok (*Stock Adjustment*). |
| **5** | **Retur Barang & Pengembalian Dana** | `Activity Diagram Retur Barang dan Refund Kasir.mmd` | `Sequence_Diagram_Retur_Barang_dan_Refund.mmd` | **Dibuat Baru & Lengkap**: Refund belanja pelanggan di meja kasir dengan otorisasi PIN SPV, pengembalian tunai/reversal kasbon anggota, serta pengajuan retur barang rusak/bocor/kadaluarsa dari toko ke Gudang DC/Supplier. |
| **6** | **Penerimaan Barang Masuk (Goods Receipt)** | `Activity Diagram Penerimaan Barang Masuk Goods Receipt.mmd` | `Sequence_Diagram_Penerimaan_Barang_Masuk_Goods_Receipt.mmd` | **Dibuat Baru & Lengkap**: Pemeriksaan Surat Jalan pengiriman dari DC Cikarang, verifikasi fisik kuantitas (BAIK / RUSAK / CACAT), penambahan stok otomatis ke database toko, isolasi retur barang rusak, dan cetak Berita Acara Penerimaan. |

---

## 3. Rincian Alur Teknis & Integrasi Sistem

### 1. Buka Tutup Sesi & Serah Terima Kas
- **Rute UI**: `/select-shift`, `/kasir/sesi`
- **Controller & Endpoint**:
  - `ShiftController.php` & `KasirShiftController.php`
  - `GET /api/shift/current`
  - `POST /api/shift/open`
  - `POST /api/shift/close`
  - `POST /api/shift/cash-movement`
- **Poin Kunci Alur**:
  - Pengecekan 4 kondisi terminal: Sesi Kasir Sendiri (*Resumption*), Sesi Kasir Lain (*Tolak*), Operan Pending (*Terima Handover*), atau Kosong (*Modal Awal Baru*).
  - 3-Step Wizard Tutup Sesi:
    1. *Blind Count*: Kasir menghitung uang fisik di laci tanpa melihat angka sistem melalui kalkulator pecahan lembar & koin.
    2. *Rekonsiliasi Varians*: Sistem membandingkan `Expected Cash` vs `Actual Cash`, menetapkan status `SESUAI`, `SELISIH KURANG`, atau `SELISIH LEBIH`.
    3. *Alokasi Serah Terima*: Pilihan menyerahkan laci ke Kasir Pengganti (*Handover*) atau disetorkan ke brankas/supervisor (*Drop Safe*).

### 2. Transaksi Penjualan & Multi-Payment
- **Rute UI**: `/pos`, `/proses-kredit`, `/cetak-struk`
- **Controller & Endpoint**:
  - `TransactionController.php`, `KasirPosController.php`, `ProductController.php`, `DiscountAuthorizationController.php`, `VoidController.php`, `HeldBillController.php`
  - `POST /api/pos/checkout`
  - `GET /api/pos/products/barcode/{barcode}`
  - `GET /api/pos/members/validate/{nik}`
  - `POST /api/pos/void/authorize`
  - `POST /api/pos/hold` & `POST /api/pos/recall/{id}`
- **Poin Kunci Alur**:
  - Peringatan expired otomatis: Diskon H-30 (10%) dan H-14 (25%).
  - 5 Metode Pembayaran didukung penuh: Tunai, QRIS, Kartu Debit/EDC, Kasbon Anggota (Potong Gaji dengan validasi sisa plafon), dan Campuran (Split).
  - Anti-Fraud: Pembatalan item (*Void*) dan diskon manual kasir mewajibkan PIN 6-digit Supervisor.

### 3. Mode Offline-First Kasir
- **Rute UI**: Terintegrasi di `/pos` dan `/kasir/sesi`
- **Penyimpanan Lokal**: IndexedDB / LocalStorage
- **Poin Kunci Alur**:
  - Saat offline, transaksi diselesaikan secara instan dengan status `PENDING`, struk belanja tetap dicetak langsung.
  - Saat koneksi online kembali, background auto-sync mengirimkan transaksi secara berurutan (*FIFO*).
  - Server melakukan validasi idempotensi menggunakan UUID transaksi agar tidak terjadi duplikasi pembukuan dan mutasi stok.

### 4. Stock Opname Persediaan
- **Rute UI**: `/kasir/stock-opname` (Kasir), `/stock-opname` (Manager)
- **Controller & Endpoint**:
  - `StockOpnameController.php`, `KasirStockOpnameController.php`, `ManagerStockOpnameController.php`
  - `POST /api/inventory/stock-opname` (Create & Snapshot)
  - `POST /api/inventory/stock-opname/{id}/verify-item` (Kasir Scan & Count)
  - `POST /api/inventory/stock-opname/{id}/complete` (Manager Approval & Stock Adjustment)
- **Poin Kunci Alur**:
  - Snapshot stok sistem dikunci saat sesi dibuat (`status: DRAFT`).
  - Kasir scan barcode produk di rak toko & input kuantitas fisik.
  - Sistem menghitung varians deviasi: `Variance = Physical Stock - System Stock`, serta nilai rupiah HPP.
  - Manager meninjau, memiliki opsi meminta hitung ulang (*Recount*), dan melakukan persetujuan penyesuaian stok (*Stock Adjustment*).

### 5. Retur Barang & Refund Kasir
- **Rute UI**: `/kasir/transaksi/refund` (Kasir), `/kasir/retur` & `/retur` (Store Returns)
- **Controller & Endpoint**:
  - `KasirRefundController.php`, `VoidController.php`, `ManagerReturnController.php`
  - `POST /api/pos/void/transaction`
  - `POST /api/returns`
  - `PUT /api/returns/{id}/status`
- **Poin Kunci Alur**:
  - Customer Refund: Mencari nomor faktur belanja, memilih barang cacat/rusak/expired, verifikasi PIN Supervisor, mengembalikan uang tunai / memulihkan sisa plafon kasbon anggota.
  - Store Return: Mengumpulkan barang rusak toko, membuat dokumen retur (toko ke DC atau toko ke supplier), pengecekan fisik oleh sopir ekspedisi, dan approval manager.

### 6. Penerimaan Barang Masuk (Goods Receipt)
- **Rute UI**: `/kasir/produk/masuk`
- **Controller & Endpoint**:
  - `KasirGoodsInController.php`, `GoodsReceiptController.php`
  - `GET /api/goods-receipts`
  - `POST /api/goods-receipts/{id}/verify`
- **Poin Kunci Alur**:
  - Pemeriksaan fisik kiriman barang dari Gudang DC Cikarang / Supplier berdasarkan Surat Jalan.
  - Pengelompokan kondisi barang: `BAIK`, `RUSAK`, `CACAT`.
  - Penambahan kuantitas barang baik ke master stok otomatis dan pembuatan draf retur untuk barang rusak.

---

## 4. Lokasi Berkas

Seluruh berkas diagram telah diperbarui dan disalin ke dua lokasi:
1. **Folder Unduhan Pengguna**:
   - `c:\Users\ASUS\Downloads\Activity Diagram\`
   - `c:\Users\ASUS\Downloads\Sequence Diagram\`
2. **Dokumentasi Internal Proyek**:
   - `c:\Users\ASUS\Desktop\POS_KASIR_ILP\docs\diagrams\activity\`
   - `c:\Users\ASUS\Desktop\POS_KASIR_ILP\docs\diagrams\sequence\`
