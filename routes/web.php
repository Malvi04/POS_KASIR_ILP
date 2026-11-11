<?php

use App\Http\Controllers\Api\Shared\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Portal & Login Routes
|--------------------------------------------------------------------------
*/
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
| Owner / Admin Console Routes (Dedicated Prefix & Views)
|--------------------------------------------------------------------------
*/
Route::get('/owner', function () {
    return view('owner.dashboard');
})->name('owner.dashboard');

Route::get('/owner/dashboard', function () {
    return view('owner.dashboard');
});

Route::get('/owner/cabang', function () {
    return view('owner.cabang');
})->name('owner.cabang');

Route::get('/owner/products', function () {
    return view('owner.products');
})->name('owner.products');

Route::get('/owner/inventory', function () {
    return view('owner.products');
});

Route::get('/owner/members', function () {
    return view('owner.members');
})->name('owner.members');

Route::get('/owner/umkm-partners', function () {
    return view('owner.umkm-partners');
})->name('owner.umkm-partners');

Route::get('/owner/reports', function () {
    return view('owner.reports');
})->name('owner.reports');

Route::get('/owner/kelola-akun', function () {
    return view('owner.kelola-akun');
})->name('owner.kelola-akun');

Route::get('/owner/users', function () {
    return view('owner.kelola-akun');
});

Route::get('/owner/audit-log', function () {
    return view('owner.audit-log');
})->name('owner.audit-log');

Route::get('/owner/activity-log', function () {
    return view('owner.audit-log');
});

Route::get('/owner/settings', function () {
    return view('owner.settings');
})->name('owner.settings');

Route::get('/owner/profile', function () {
    return view('owner.profile');
})->name('owner.profile');

/*
|--------------------------------------------------------------------------
| Manager Console Routes (Dedicated Prefix & Views)
|--------------------------------------------------------------------------
*/
Route::get('/manager/dashboard', function () {
    return view('manager.dashboard');
})->name('manager.dashboard');

Route::get('/manager/inventory', function () {
    return view('manager.inventory');
})->name('manager.inventory');

Route::get('/manager/products/export', [App\Http\Controllers\Api\Manager\ProductImportExportController::class, 'export'])->name('manager.products.export');
Route::get('/manager/products/template', [App\Http\Controllers\Api\Manager\ProductImportExportController::class, 'downloadTemplate'])->name('manager.products.template');

Route::get('/manager/kirim-barang', function () {
    return view('manager.kirim-barang', [
        'activeNav' => 'kirim_barang'
    ]);
})->name('manager.kirim-barang');

Route::get('/manager/stock-opname', function () {
    return view('manager.stock-opname');
})->name('manager.stock-opname');

Route::get('/manager/retur', function () {
    return view('manager.retur', [
        'initialTab' => request('tab', 'approval'),
        'activeNav' => 'retur'
    ]);
})->name('manager.retur');

Route::get('/manager/retur-masuk', function () {
    return view('manager.retur-masuk', [
        'activeNav' => 'retur_masuk'
    ]);
})->name('manager.retur-masuk');

Route::get('/manager/members', function () {
    return view('manager.members');
})->name('manager.members');

Route::get('/manager/member-detail', function () {
    return view('manager.member-detail');
})->name('manager.member-detail');

Route::get('/manager/umkm-partners', function () {
    return view('manager.umkm-partners');
})->name('manager.umkm-partners');

Route::get('/manager/promosi', function () {
    return view('manager.promosi');
})->name('manager.promosi');

Route::get('/manager/absensi', function () {
    return view('manager.absensi');
})->name('manager.absensi');

Route::get('/manager/reports', function () {
    return view('manager.reports');
})->name('manager.reports');

Route::get('/manager/kelola-akun', function () {
    return view('manager.kelola-akun');
})->name('manager.kelola-akun');

Route::get('/manager/settings', function () {
    return view('manager.settings');
})->name('manager.settings');

/*
|--------------------------------------------------------------------------
| Core Shared / Backward Compatible Routes (Role-Aware)
|--------------------------------------------------------------------------
*/
Route::get('/dashboard', function () {
    if (request('role') === 'owner') {
        return redirect('/owner');
    }
    return view('manager.dashboard');
})->name('dashboard');

Route::get('/inventory', function () {
    if (request('role') === 'owner') {
        return view('owner.products');
    }
    return view('manager.inventory');
})->name('inventory');

Route::get('/cabang', function () {
    return view('owner.cabang');
})->name('admin.cabang');

Route::get('/members', function () {
    if (request('role') === 'manager') {
        return view('manager.members');
    }
    return view('owner.members');
})->name('members');

Route::get('/member-detail', function () {
    return view('manager.member-detail');
});

Route::get('/umkm-partners', function () {
    if (request('role') === 'manager') {
        return view('manager.umkm-partners');
    }
    return view('owner.umkm-partners');
})->name('umkm-partners');

Route::get('/promosi', function () {
    return view('manager.promosi');
})->name('promosi');

Route::get('/absensi', function () {
    return view('manager.absensi');
})->name('absensi');

Route::get('/reports', function () {
    if (request('role') === 'manager') {
        return view('manager.reports');
    }
    return view('owner.reports');
})->name('reports');

Route::get('/kelola-akun', function () {
    if (request('role') === 'manager') {
        return view('manager.kelola-akun');
    }
    return view('owner.kelola-akun');
})->name('kelola-akun');

Route::get('/audit-log', function () {
    return redirect('/owner/audit-log');
});

Route::get('/settings', function () {
    if (request('role') === 'manager') {
        return view('manager.settings');
    }
    return view('owner.settings');
})->name('settings');

Route::get('/stock-opname', function () {
    return view('manager.stock-opname');
});

Route::get('/retur', function () {
    return view('manager.retur', [
        'initialTab' => request('tab', 'approval'),
        'activeNav' => 'retur'
    ]);
})->name('retur');

Route::get('/retur-masuk', function () {
    return view('manager.retur-masuk', [
        'activeNav' => 'retur_masuk'
    ]);
});

Route::get('/kirim-barang', function () {
    return view('manager.kirim-barang', [
        'activeNav' => 'kirim_barang'
    ]);
});

/*
|--------------------------------------------------------------------------
| Cashier Role Routes (ILP POS Kasir)
|--------------------------------------------------------------------------
*/
// 1. POS Utama
Route::get('/pos', function () {
    return view('kasir.pos');
})->name('cashier.pos');

Route::get('/kasir/pos', function () {
    return view('kasir.pos');
});

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
Route::get('/kupon', function () {
    return view('kasir.kupon');
});
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

// 11. Select Shift, Proses Kredit, Cetak Struk
Route::get('/select-shift', function () {
    return view('kasir.select-shift');
});
Route::get('/kasir/select-shift', function () {
    return view('kasir.select-shift');
});

Route::get('/proses-kredit', function () {
    return view('kasir.proses-kredit');
});
Route::get('/kasir/proses-kredit', function () {
    return view('kasir.proses-kredit');
});

Route::get('/cetak-struk', function () {
    return view('kasir.cetak-struk');
});
Route::get('/kasir/cetak-struk', function () {
    return view('kasir.cetak-struk');
});

// Legacy redirects
Route::get('/kasir/login', function () {
    return redirect('/login-kasir');
});
Route::get('/kasir/placeholder', function () {
    return redirect('/kasir/pos');
});
