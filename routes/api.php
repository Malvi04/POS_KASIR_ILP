<?php

use App\Http\Controllers\Api\Kasir\CouponController;
use App\Http\Controllers\Api\Kasir\DiscountAuthorizationController;
use App\Http\Controllers\Api\Kasir\GoodsReceiptController;
use App\Http\Controllers\Api\Kasir\HeldBillController;
use App\Http\Controllers\Api\Kasir\ShiftController;
use App\Http\Controllers\Api\Kasir\TransactionController;
use App\Http\Controllers\Api\Kasir\VoidController;
use App\Http\Controllers\Api\Manager\AccountingReportController;
use App\Http\Controllers\Api\Manager\AttendanceController;
use App\Http\Controllers\Api\Manager\ConsignmentController;
use App\Http\Controllers\Api\Manager\ConsolidatedReportController;
use App\Http\Controllers\Api\Manager\ManagerDashboardController;
use App\Http\Controllers\Api\Manager\MemberDebtReportController;
use App\Http\Controllers\Api\Manager\MemberManagementController;
use App\Http\Controllers\Api\Manager\PromotionController;
use App\Http\Controllers\Api\Manager\ReturnController;
use App\Http\Controllers\Api\Manager\SettingsController;
use App\Http\Controllers\Api\Manager\StockCorrectionController;
use App\Http\Controllers\Api\Manager\StockOpnameController;
use App\Http\Controllers\Api\Manager\UserManagementController;
use App\Http\Controllers\Api\Owner\AuditLogController;
use App\Http\Controllers\Api\Owner\BranchController;
use App\Http\Controllers\Api\Owner\OwnerDashboardController;
use App\Http\Controllers\Api\Owner\OwnerProfileController;
use App\Http\Controllers\Api\Shared\AuthController;
use App\Http\Controllers\Api\Shared\MemberController;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Http\Controllers\Api\Shared\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Modul A: Front-End POS & Transaksi Kasir
|--------------------------------------------------------------------------
| Highly optimized & low-latency REST API endpoints for POS operations.
| Connected to PostgreSQL database with high-performance indexing.
*/

// Health Check
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'database' => 'postgresql',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Authentication (Kasir, Manager, Owner)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
});

// 1. Shift & Modal Kasir (Opening Cash & Audit Closing)
Route::prefix('shift')->group(function () {
    Route::get('/current', [ShiftController::class, 'current']);
    Route::get('/active-shifts', [ShiftController::class, 'activeShifts']);
    Route::post('/open', [ShiftController::class, 'open']);
    Route::post('/close', [ShiftController::class, 'close']);
    Route::post('/cash-movement', [ShiftController::class, 'cashMovement']);
});

// 2. POS Products & Fast Search (Auto-Discount Barang Expired)
Route::prefix('pos/products')->group(function () {
    Route::get('/search', [ProductController::class, 'search']);
    Route::get('/barcode/{barcode}', [ProductController::class, 'lookupBarcode']);
    Route::get('/categories', [ProductController::class, 'categories']);
});

// 3. Member Validation & Credit Kasbon Potong Gaji
Route::prefix('pos/members')->group(function () {
    Route::get('/search', [MemberController::class, 'search']);
    Route::get('/validate/{nik}', [MemberController::class, 'validateNik']);
});

// 4. Pending Transaksi (Hold Bill / Recall)
Route::prefix('pos')->group(function () {
    Route::get('/held-bills', [HeldBillController::class, 'index']);
    Route::post('/hold', [HeldBillController::class, 'hold']);
    Route::post('/recall/{id}', [HeldBillController::class, 'recall']);
    Route::delete('/held-bills/{id}', [HeldBillController::class, 'cancel']);
});

// 5. Checkout & Multi-Type Payment
Route::prefix('pos')->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::post('/checkout', [TransactionController::class, 'checkout']);
    Route::post('/sync-offline', [TransactionController::class, 'syncOffline']);
    Route::get('/transactions/{idOrInvoice}', [TransactionController::class, 'show']);
});

// 6. Otorisasi Void / Pembatalan Transaction (Anti-Fraud Control)
Route::prefix('pos/void')->group(function () {
    Route::post('/authorize', [VoidController::class, 'authorizeSupervisor']);
    Route::post('/item', [VoidController::class, 'voidItem']);
    Route::post('/transaction', [VoidController::class, 'voidTransaction']);
    Route::get('/logs', [VoidController::class, 'logs']);
});

/*
|--------------------------------------------------------------------------
| Modul B: Anggota Koperasi & Piutang (CRM & Payroll Debt)
|--------------------------------------------------------------------------
| 1. Database Anggota Multi-Cabang (Transparent 3 Branches Access)
| 2. Plafon Kredit Kasbon & Real-time Deduction
| 3. Laporan Piutang Anggota & Export CSV / Excel HRD PT Indo Lampung Perkasa
*/

// Master Data Anggota Multi-Cabang
Route::prefix('members')->group(function () {
    Route::get('/', [MemberManagementController::class, 'index']);
    Route::post('/', [MemberManagementController::class, 'store']);
    Route::post('/bulk-limit', [MemberManagementController::class, 'bulkUpdateLimit']);
    Route::get('/{id}', [MemberManagementController::class, 'show']);
    Route::put('/{id}', [MemberManagementController::class, 'update']);
    Route::delete('/{id}', [MemberManagementController::class, 'destroy']);
    Route::put('/{id}/credit-limit', [MemberManagementController::class, 'updateCreditLimit']);
    Route::post('/{id}/settlement', [MemberManagementController::class, 'recordSettlement']);
    Route::get('/{id}/debt-history', [MemberManagementController::class, 'debtHistory']);
});

// Laporan Piutang & Kasbon Anggota (Payroll Debt PT ILP)
Route::prefix('reports/member-debts')->group(function () {
    Route::get('/', [MemberDebtReportController::class, 'index']);
    Route::get('/export-csv', [MemberDebtReportController::class, 'exportCsv']);
    Route::get('/export-excel', [MemberDebtReportController::class, 'exportExcel']);
    Route::post('/payroll-cutoff', [MemberDebtReportController::class, 'processPayrollCutoff']);
});

/*
|--------------------------------------------------------------------------
| Modul C: Inventaris, Stok & Konsinyasi UMKM
|--------------------------------------------------------------------------
| 1. Manajemen Konsinyasi (Barang Titipan UMKM Lokal & Bagi Hasil)
| 2. Multi-Satuan Barang (Unit Conversion e.g. Dus -> Pcs)
| 3. Peringatan Stok Minimum (Stock Threshold Alert)
| 4. Pelacakan Expired Date & Alarm System (Kuning H-30..H-15, Merah <= H-14)
| 5. Stock Opname & Variance Report
*/

// Master Inventaris & Produk (Multi-Satuan & Stok)
Route::prefix('products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::post('/', [ProductController::class, 'store']);
    Route::put('/{id}', [ProductController::class, 'update']);
    Route::delete('/{id}', [ProductController::class, 'destroy']);
    Route::post('/{id}/units', [ProductController::class, 'addUnit']);
    Route::delete('/{id}/units/{unitId}', [ProductController::class, 'removeUnit']);
});

// Konsinyasi UMKM (Mitra & Bagi Hasil)
Route::prefix('consignment')->group(function () {
    Route::get('/partners', [ConsignmentController::class, 'indexPartners']);
    Route::post('/partners', [ConsignmentController::class, 'storePartner']);
    Route::put('/partners/{id}', [ConsignmentController::class, 'updatePartner']);
    Route::delete('/partners/{id}', [ConsignmentController::class, 'destroyPartner']);
    Route::get('/report', [ConsignmentController::class, 'report']);
    Route::post('/settlement', [ConsignmentController::class, 'createSettlement']);
});

// Stock Opname
Route::prefix('inventory/stock-opname')->group(function () {
    Route::get('/', [StockOpnameController::class, 'index']);
    Route::post('/', [StockOpnameController::class, 'store']);
    Route::get('/{id}', [StockOpnameController::class, 'show']);
    Route::post('/{id}/verify-item', [StockOpnameController::class, 'verifyItem']);
    Route::post('/{id}/complete', [StockOpnameController::class, 'complete']);
    Route::get('/{id}/export-csv', [StockOpnameController::class, 'exportCsv']);
    Route::get('/{id}/export-excel', [StockOpnameController::class, 'exportExcel']);
});

/*
|--------------------------------------------------------------------------
| Modul D: Manajemen Hak Akses Pengguna (User Access Security)
|--------------------------------------------------------------------------
| 1. Kasir: Shift, Penjualan, Hold/Recall, Cetak Struk, Shift Closing. (No Void without SPV)
| 2. Supervisor / Manager: Void Auth, Diskon Manual, Koreksi Stok Harian, Stock Opname
| 3. Owner / Superadmin: Full Control, Master Data, Plafon, Konsolidasi 3 Cabang, Pajak, User Management
*/

// User Management (Kelola Akun Kasir, SPV, Manager, Owner)
Route::prefix('users')->group(function () {
    Route::get('/', [UserManagementController::class, 'index']);
    Route::post('/', [UserManagementController::class, 'store']);
    Route::get('/{id}', [UserManagementController::class, 'show']);
    Route::put('/{id}', [UserManagementController::class, 'update']);
    Route::delete('/{id}', [UserManagementController::class, 'destroy']);
    Route::put('/{id}/pin', [UserManagementController::class, 'resetPin']);
    Route::put('/{id}/password', [UserManagementController::class, 'resetPassword']);
    Route::post('/{id}/toggle-status', [UserManagementController::class, 'toggleStatus']);
});

// System & Tax Settings (Pengaturan Pajak PPN & Keamanan Toko)
Route::prefix('settings')->group(function () {
    Route::get('/', [SettingsController::class, 'index']);
    Route::put('/store', [SettingsController::class, 'updateStore']);
    Route::put('/tax', [SettingsController::class, 'updateTax']);
    Route::put('/security', [SettingsController::class, 'updateSecurity']);
});

// Owner Executive API Endpoints
Route::prefix('owner')->group(function () {
    Route::get('/dashboard-summary', [OwnerDashboardController::class, 'summary']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
    Route::post('/audit-logs', [AuditLogController::class, 'store']);
    Route::get('/profile', [OwnerProfileController::class, 'show']);
    Route::put('/profile', [OwnerProfileController::class, 'update']);
});

// Branch & Warehouse Management API
Route::apiResource('branches', BranchController::class);

// Konsolidasi Laporan 3 Cabang & Real-time P&L (Owner / Superadmin)
Route::prefix('reports')->group(function () {
    Route::get('/consolidated-branches', [ConsolidatedReportController::class, 'index']);
});

// Koreksi Stok Harian Cabang (Supervisor / Manager)
Route::prefix('inventory/stock-corrections')->group(function () {
    Route::get('/', [StockCorrectionController::class, 'index']);
    Route::post('/', [StockCorrectionController::class, 'store']);
});

// Otorisasi Diskon Manual Kasir (Supervisor / Manager)
Route::prefix('pos/discount')->group(function () {
    Route::post('/authorize', [DiscountAuthorizationController::class, 'authorizeDiscount']);
});

/*
|--------------------------------------------------------------------------
| Modul E: Pelaporan & Akuntansi Dasar
|--------------------------------------------------------------------------
| 1. Laporan Penjualan (Per-shift, Per-cabang, Konsolidasi 3 Cabang: Harian, Mingguan, Bulanan)
| 2. Laporan Laba/Rugi Bersih (HPP & Bagi Hasil Komisi UMKM)
| 3. Analisis Barang Terlaris (Fast Moving Items & Rekomendasi Restock)
| 4. Laporan Rekonsiliasi Kas (Cash Count / Shift Closing)
| 5. Pengaturan Pajak (Tax / Non-Tax Products Management & Checkout PPN)
*/
Route::prefix('reports')->group(function () {
    Route::get('/sales', [AccountingReportController::class, 'sales']);
    Route::get('/profit-loss', [AccountingReportController::class, 'profitLoss']);
    Route::get('/fast-moving', [AccountingReportController::class, 'fastMoving']);
    Route::get('/cash-reconciliation', [AccountingReportController::class, 'cashReconciliation']);
});

// Tax / Non-Tax Products Management
Route::prefix('products')->group(function () {
    Route::get('/tax/list', [AccountingReportController::class, 'productTaxList']);
    Route::put('/{id}/tax-status', [AccountingReportController::class, 'updateProductTax']);
});

// Kupon BBM & Voucher Operasional (ERD Kupon BBM)
Route::prefix('coupons')->group(function () {
    Route::get('/', [CouponController::class, 'index']);
    Route::post('/', [CouponController::class, 'store']);
    Route::put('/{id}', [CouponController::class, 'update']);
    Route::delete('/{id}', [CouponController::class, 'destroy']);
    Route::post('/{id}/redeem', [CouponController::class, 'redeem']);
});

/*
|--------------------------------------------------------------------------
| Modul Manajerial, Manager & Supervisor (SVP)
|--------------------------------------------------------------------------
| 1. Manager Dashboard KPI & Ringkasan Operasional Real-time
| 2. Manajemen Promosi & Diskon Kadaluarsa (H-7 / H-3)
| 3. Retur Toko ke DC / Supplier & Persetujuan Supervisor
| 4. Rekap Absensi, Jadwal Shift & Pengajuan Izin / Cuti
*/

// Dashboard Manager (dipakai oleh halaman manager/dashboard)
Route::get('/manager/dashboard-summary', [ManagerDashboardController::class, 'summary']);

// Promosi & Aturan Diskon
Route::prefix('promotions')->group(function () {
    Route::get('/', [PromotionController::class, 'index']);
    Route::post('/', [PromotionController::class, 'store']);
    Route::get('/settings', [PromotionController::class, 'getSettings']);
    Route::put('/settings', [PromotionController::class, 'updateSettings']);
    Route::put('/{id}', [PromotionController::class, 'update']);
    Route::delete('/{id}', [PromotionController::class, 'destroy']);
});

// Retur Barang Toko
Route::prefix('returns')->group(function () {
    Route::get('/', [ReturnController::class, 'index']);
    Route::post('/', [ReturnController::class, 'store']);
    Route::put('/{id}/status', [ReturnController::class, 'updateStatus']);
    Route::delete('/{id}', [ReturnController::class, 'destroy']);
});

// Absensi & Shift Karyawan / Kasir
Route::prefix('attendance')->group(function () {
    Route::get('/', [AttendanceController::class, 'index']);
    Route::post('/', [AttendanceController::class, 'store']);
    Route::get('/leaves', [AttendanceController::class, 'leaves']);
    Route::post('/leaves', [AttendanceController::class, 'storeLeave']);
    Route::put('/leaves/{id}/status', [AttendanceController::class, 'updateLeaveStatus']);
    Route::get('/shifts', [AttendanceController::class, 'shifts']);
});

// Penerimaan Barang Masuk (Transfer Gudang DC / Supplier)
Route::prefix('goods-receipts')->group(function () {
    Route::get('/', [GoodsReceiptController::class, 'index']);
    Route::post('/', [GoodsReceiptController::class, 'store']);
    Route::post('/{id}/verify', [GoodsReceiptController::class, 'verify']);
    Route::put('/{id}/status', [GoodsReceiptController::class, 'updateStatus']);
});

// Sistem Notifikasi Realtime Berdasarkan Role
Route::prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/', [NotificationController::class, 'store']);
    Route::post('/mark-read/{id?}', [NotificationController::class, 'markAsRead']);
    Route::post('/clear-all', [NotificationController::class, 'clearAll']);
    Route::delete('/{id}', [NotificationController::class, 'destroy']);
});

