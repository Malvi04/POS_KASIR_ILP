<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login-kasir', function () {
    return view('welcome', ['initialRole' => 'Kasir']);
})->name('cashier.login');

Route::get('/login-manager', function () {
    return view('welcome', ['initialRole' => 'Manager']);
})->name('manager.login');

Route::get('/inventory', function () {
    return view('manager.inventory');
});

Route::get('/pos', function () {
    return view('pos');
});

Route::get('/settings', function () {
    return view('manager.settings');
});

Route::get('/members', function () {
    return view('manager.members');
});

Route::get('/dashboard', function () {
    return view('manager.dashboard');
});

Route::get('/reports', function () {
    return view('manager.reports');
});

Route::get('/select-shift', function () {
    return view('select-shift');
});

Route::get('/proses-kredit', function () {
    return view('proses-kredit');
});

Route::get('/kupon', function () {
    return view('kupon');
});

Route::get('/retur', function () {
    return view('manager.retur');
});

Route::get('/cetak-struk', function () {
    return view('cetak-struk');
});

Route::get('/absensi', function () {
    return view('manager.absensi');
});

Route::get('/member-detail', function () {
    return view('manager.member-detail');
});

Route::get('/stock-opname', function () {
    return view('manager.stock-opname');
});

Route::get('/promosi', function () {
    return view('manager.promosi');
});

Route::get('/kelola-akun', function () {
    return view('manager.kelola-akun');
});

Route::get('/umkm-partners', function () {
    return view('manager.umkm-partners');
});

/*
|--------------------------------------------------------------------------
| Cashier Role Routes (ILP POS Kasir)
|--------------------------------------------------------------------------
*/

// 1. POS Utama
// /pos handled above

// 2. Sesi Kasir
Route::get('/kasir/sesi', function () {
    return view('cashier-session');
})->name('cashier.session');

// 3. Produk Submenu
Route::get('/kasir/produk/masuk', function () {
    return view('cashier-goods-in');
});

Route::get('/kasir/inventory', function () {
    return view('cashier-inventory');
});

// 4. Retur Barang Submenu
Route::get('/kasir/retur', function () {
    return view('cashier-retur');
});

Route::get('/kasir/retur/masuk', function () {
    return view('cashier-return-in');
});

// 5. Transaksi Submenu
Route::get('/kasir/transaksi/riwayat', function () {
    return view('cashier-transactions');
});

Route::get('/kasir/transaksi/ditahan', function () {
    return view('cashier-held-bills');
});

Route::get('/kasir/transaksi/refund', function () {
    return view('cashier-refund');
});

// 6. Pelanggan / Anggota
Route::get('/kasir/members', function () {
    return view('cashier-members');
});

// 7. Laporan Submenu
Route::get('/kasir/reports', function () {
    return view('cashier-reports');
});

Route::get('/kasir/laporan/kasir', function () {
    return view('cashier-report-by-cashier');
});

Route::get('/kasir/laporan/sesi', function () {
    return view('cashier-report-sessions');
});

Route::get('/kasir/laporan/operasional', function () {
    return view('cashier-report-operational');
});

// 8. Kupon
// /kupon handled above

// 9. Stock Opname
Route::get('/kasir/stock-opname', function () {
    return view('cashier-stock-opname');
});

// 10. Pengaturan
Route::get('/kasir/settings', function () {
    return view('cashier-settings');
});
