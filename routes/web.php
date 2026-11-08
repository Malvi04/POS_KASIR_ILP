<?php

use App\Http\Controllers\Api\Shared\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::get('/login-kasir', function () {
    return view('welcome', ['initialRole' => 'Kasir']);
})->name('cashier.login');

Route::get('/login-manager', function () {
    return view('welcome', ['initialRole' => 'Manager']);
})->name('manager.login');
Route::get('/manager/login', function () {
    return view('welcome', ['initialRole' => 'Manager']);
});

/*
|--------------------------------------------------------------------------
| Manager Role Routes (ILP POS Manager, Supervisor & Owner)
|--------------------------------------------------------------------------
*/
// 1. Dashboard
Route::get('/dashboard', function () {
    return view('manager.dashboard');
})->name('manager.dashboard');
Route::get('/manager/dashboard', function () {
    return view('manager.dashboard');
});

// 2. Persediaan & Master Produk
Route::get('/inventory', function () {
    return view('manager.inventory');
})->name('manager.inventory');
Route::get('/manager/inventory', function () {
    return view('manager.inventory');
});

// 3. Stock Opname
Route::get('/stock-opname', function () {
    return view('manager.stock-opname');
})->name('manager.stock-opname');
Route::get('/manager/stock-opname', function () {
    return view('manager.stock-opname');
});

// 4. Retur Barang Toko
Route::get('/retur', function () {
    return view('manager.retur');
})->name('manager.retur');
Route::get('/manager/retur', function () {
    return view('manager.retur');
});

// 5. Pelanggan & Anggota Koperasi
Route::get('/members', function () {
    return view('manager.members');
})->name('manager.members');
Route::get('/manager/members', function () {
    return view('manager.members');
});
Route::get('/member-detail', function () {
    return view('manager.member-detail');
})->name('manager.member-detail');
Route::get('/manager/member-detail', function () {
    return view('manager.member-detail');
});

// 6. Mitra UMKM Konsinyasi
Route::get('/umkm-partners', function () {
    return view('manager.umkm-partners');
})->name('manager.umkm-partners');
Route::get('/manager/umkm-partners', function () {
    return view('manager.umkm-partners');
});

// 7. Promosi & Diskon
Route::get('/promosi', function () {
    return view('manager.promosi');
})->name('manager.promosi');
Route::get('/manager/promosi', function () {
    return view('manager.promosi');
});

// 8. Absensi Karyawan & Shift
Route::get('/absensi', function () {
    return view('manager.absensi');
})->name('manager.absensi');
Route::get('/manager/absensi', function () {
    return view('manager.absensi');
});

// 9. Laporan & Konsolidasi 3 Cabang
Route::get('/reports', function () {
    return view('manager.reports');
})->name('manager.reports');
Route::get('/manager/reports', function () {
    return view('manager.reports');
});

// 10. Kelola Akun & Penugasan Kasir
Route::get('/kelola-akun', function () {
    return view('manager.kelola-akun');
})->name('manager.kelola-akun');
Route::get('/manager/kelola-akun', function () {
    return view('manager.kelola-akun');
});

// 11. Pengaturan Sistem & Pajak
Route::get('/settings', function () {
    return view('manager.settings');
})->name('manager.settings');
Route::get('/manager/settings', function () {
    return view('manager.settings');
});

Route::get('/pos', function () {
    return view('kasir.pos');
});

Route::get('/select-shift', function () {
    return view('kasir.select-shift');
});

Route::get('/proses-kredit', function () {
    return view('kasir.proses-kredit');
});

Route::get('/kupon', function () {
    return view('kasir.kupon');
});

Route::get('/cetak-struk', function () {
    return view('kasir.cetak-struk');
});

/*
|--------------------------------------------------------------------------
| Cashier Role Routes (ILP POS Kasir)
|--------------------------------------------------------------------------
*/

// 1. POS Utama
Route::get('/kasir/pos', function () {
    return view('kasir.pos');
})->name('cashier.pos');

// 2. Sesi Kasir
Route::get('/kasir/sesi', function () {
    return view('kasir.session');
})->name('cashier.session');
Route::get('/cashier-session', function () {
    return view('kasir.session');
});

// 3. Produk Submenu
Route::get('/kasir/produk/masuk', function () {
    return view('kasir.goods-in');
});
Route::get('/cashier-goods-in', function () {
    return view('kasir.goods-in');
});

Route::get('/kasir/inventory', function () {
    return view('kasir.inventory');
});
Route::get('/cashier-inventory', function () {
    return view('kasir.inventory');
});

// 4. Retur Barang Submenu
Route::get('/kasir/retur', function () {
    return view('kasir.retur');
});
Route::get('/cashier-retur', function () {
    return view('kasir.retur');
});

Route::get('/kasir/retur/masuk', function () {
    return view('kasir.return-in');
});
Route::get('/cashier-return-in', function () {
    return view('kasir.return-in');
});

// 5. Transaksi Submenu
Route::get('/kasir/transaksi/riwayat', function () {
    return view('kasir.transactions');
});
Route::get('/cashier-transactions', function () {
    return view('kasir.transactions');
});

Route::get('/kasir/transaksi/ditahan', function () {
    return view('kasir.held-bills');
});
Route::get('/cashier-held-bills', function () {
    return view('kasir.held-bills');
});

Route::get('/kasir/transaksi/refund', function () {
    return view('kasir.refund');
});
Route::get('/cashier-refund', function () {
    return view('kasir.refund');
});

// 6. Pelanggan / Anggota
Route::get('/kasir/members', function () {
    return view('kasir.members');
});
Route::get('/cashier-members', function () {
    return view('kasir.members');
});

// 7. Laporan Submenu
Route::get('/kasir/reports', function () {
    return view('kasir.reports');
});
Route::get('/cashier-reports', function () {
    return view('kasir.reports');
});

Route::get('/kasir/laporan/kasir', function () {
    return view('kasir.report-by-cashier');
});
Route::get('/cashier-report-by-cashier', function () {
    return view('kasir.report-by-cashier');
});

Route::get('/kasir/laporan/sesi', function () {
    return view('kasir.report-sessions');
});
Route::get('/cashier-report-sessions', function () {
    return view('kasir.report-sessions');
});

Route::get('/kasir/laporan/operasional', function () {
    return view('kasir.report-operational');
});
Route::get('/cashier-report-operational', function () {
    return view('kasir.report-operational');
});

// 8. Kupon
Route::get('/kasir/kupon', function () {
    return view('kasir.kupon');
});

// 9. Stock Opname
Route::get('/kasir/stock-opname', function () {
    return view('kasir.stock-opname');
});
Route::get('/cashier-stock-opname', function () {
    return view('kasir.stock-opname');
});

// 10. Pengaturan
Route::get('/kasir/settings', function () {
    return view('kasir.settings');
});
Route::get('/cashier-settings', function () {
    return view('kasir.settings');
});

// 11. Select Shift & Proses Kredit
Route::get('/kasir/select-shift', function () {
    return view('kasir.select-shift');
});
Route::get('/kasir/proses-kredit', function () {
    return view('kasir.proses-kredit');
});
Route::get('/kasir/cetak-struk', function () {
    return view('kasir.cetak-struk');
});
