# Laporan Penyesuaian Database dengan ERD Resmi
**Minimarket Koperasi Multi-Cabang PT Indo Lampung Perkasa**  
*Tanggal: 02 November 2026*

---

## 1. Ringkasan Penyesuaian

Berdasarkan berkas Entity Relationship Diagram (ERD) Mermaid (`.mmd`) dari folder `c:\Users\ASUS\Downloads\ERD`, skema database PostgreSQL aplikasi **POS_KASIR_ILP** telah disesuaikan dan diperluas secara menyeluruh.

Penyesuaian ini menyempurnakan struktur master data multi-cabang, hierarki produk & satuan, sistem pengadaan/pembelian, transfer barang antar-cabang/DC, siklus retur lengkap, pembukuan akuntansi (Chart of Accounts & Jurnal), hingga kupon BBM.

---

## 2. Tabel & Model yang Dibuat & Diselaraskan

### A. Master Data & Multi-Lokasi Cabang (`ERD_Master_Data_dan_Hak_Akses.mmd`)
1. **`locations`** & **`user_location_access`**:
   - Model: [`Location.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/Location.php)
   - Kolom: `id`, `code`, `name`, `type` (*STORE, WAREHOUSE, FACTORY, PLANTATION*), `is_active`.
   - Seeded:
     - `LOC-01`: Cabang 1 - Mill / Pabrik
     - `LOC-02`: Cabang 2 - Kebun Barat
     - `LOC-03`: Cabang 3 - Kebun Timur
     - `LOC-DC`: DC Cikarang (Pusat Logistik)
2. **`product_categories`** & **`units`**:
   - Model: [`ProductCategory.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/ProductCategory.php) & [`Unit.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/Unit.php)
   - Seeded Kategori: *Kebutuhan Pokok, Makanan, Minuman, Snack, Pembersih, Kupon BBM*.
   - Seeded Satuan: *Pieces (Pcs), Kardus (Dus), Botol, Pack, Pouch, Tetrapack, Karton, Kaleng*.
3. **`product_barcodes`**:
   - Mendukung multi-barcode per produk untuk scanner kasir.

### B. Pengadaan & Penerimaan Barang (`ERD_Pembelian_dan_Penerimaan_Barang.mmd`)
1. **`purchases`** & **`purchase_items`**:
   - Model: [`Purchase.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/Purchase.php) & [`PurchaseItem.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/PurchaseItem.php)
   - Mencatat nomor PO, supplier, lokasi tujuan, tanggal pembelian, dan status.
2. **`goods_receipts`** & **`goods_receipt_items`**:
   - Model: [`GoodsReceipt.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/GoodsReceipt.php) & [`GoodsReceiptItem.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/GoodsReceiptItem.php)
   - Pencatatan surat jalan (*delivery note*), nama driver, nomor plat kendaraan, kuantitas dikirim vs diterima fisik, serta status kondisi (*BAIK / RUSAK / KEMASAN_RUSAK*).

### C. Permintaan & Mutasi Transfer Barang (`ERD_permintaan_dan_Transfer_Barang.mmd`)
1. **`stock_requests`** & **`stock_request_items`**:
   - Model: [`StockRequest.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/StockRequest.php) & [`StockRequestItem.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/StockRequestItem.php)
   - Permintaan penambahan stok dari toko cabang ke DC pusat.
2. **`stock_transfers`**, **`stock_transfer_items`**, & **`transfer_discrepancies`**:
   - Model: [`StockTransfer.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/StockTransfer.php) & [`StockTransferItem.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/StockTransferItem.php)
   - Pelacakan mutasi barang dalam perjalanan (*IN_TRANSIT*) dan pencatatan selisih barang fisik.

### D. Siklus Retur Barang (`ERD_Retur_Barang.mmd`)
1. **`sales_returns`** & **`sales_return_items`**:
   - Model: [`SalesReturn.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/SalesReturn.php) & [`SalesReturnItem.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/SalesReturnItem.php)
   - Pengembalian belanja dari pelanggan/anggota di meja kasir.
2. **`store_return_headers`** & **`store_return_items`**:
   - Model: [`StoreReturnHeader.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/StoreReturnHeader.php) & [`StoreReturnItem.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/StoreReturnItem.php)
   - Retur barang cacat/bocor/kedaluwarsa dari toko cabang kembali ke gudang DC pusat.

### E. Akuntansi & Keuangan Terpadu (`ERD_Keuangan_dan_Settlement_Konsinyasi.mmd`)
1. **`chart_of_accounts`** & **`journal_entries`** & **`journal_entry_lines`**:
   - Model: [`ChartOfAccount.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/ChartOfAccount.php), [`JournalEntry.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/JournalEntry.php), [`JournalEntryLine.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/JournalEntryLine.php)
   - Standar bagan akun akuntansi (Kasir, Bank/QRIS, Piutang Anggota, Persediaan, Hutang Konsinyasi, Pendapatan Penjualan, Komisi UMKM, HPP).
2. **`cash_handover_headers`**:
   - Model: [`CashHandoverHeader.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/CashHandoverHeader.php)
   - Bukti serah terima fisik uang kas penutupan shift kasir ke brankas/supervisor.

### F. Kupon BBM & Voucher (`ERD_Kupon_BBM.mmd`)
1. **`coupon_units`** & **`coupon_redemptions`**:
   - Model: [`CouponUnit.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/CouponUnit.php) & [`CouponRedemption.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/app/Models/CouponRedemption.php)
   - Pengelolaan serial number kupon BBM dan riwayat penukaran di kasir.

### G. Operasional & Urutan Dokumen (`ERD_Operasional_Toko_dan_Audit.mmd`)
1. **`store_operations`** & **`document_sequences`**:
   - Kontrol status operasional buka/tutup toko per lokasi cabang serta generator nomor urut faktur transaksi dan surat jalan.

---

## 3. Hasil Migrasi & Verifikasi Test Suite

- **File Migrasi Baru:**
  [`database/migrations/2026_11_02_000001_create_erd_alignment_tables.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/database/migrations/2026_11_02_000001_create_erd_alignment_tables.php)
- **File Test Baru:**
  [`tests/Feature/ErdAlignmentDatabaseTest.php`](file:///c:/Users/ASUS/Desktop/POS_KASIR_ILP/tests/Feature/ErdAlignmentDatabaseTest.php)
- **Hasil Test:**
  ```bash
  Tests:    43 passed (323 assertions)
  Duration: 8.63s
  Result:   100% GREEN
  ```
