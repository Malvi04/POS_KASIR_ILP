<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login-kasir', function () {
    return view('cashier-login');
})->name('cashier.login');

Route::get('/login-manager', function () {
    return view('manager-login');
})->name('manager.login');

Route::get('/inventory', function () {
    return view('inventory');
});

Route::get('/pos', function () {
    return view('pos');
});

Route::get('/settings', function () {
    return view('settings');
});

Route::get('/members', function () {
    return view('members');
});

Route::get('/dashboard', function () {
    return view('dashboard');
});

Route::get('/reports', function () {
    return view('reports');
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
    return view('retur');
});

Route::get('/cetak-struk', function () {
    return view('cetak-struk');
});

Route::get('/absensi', function () {
    return view('absensi');
});

Route::get('/member-detail', function () {
    return view('member-detail');
});

Route::get('/stock-opname', function () {
    return view('stock-opname');
});

Route::get('/kasir/stock-opname', function () {
    return view('cashier-stock-opname');
});

Route::get('/kasir/inventory', function () {
    return view('cashier-inventory');
});

Route::get('/kasir/members', function () {
    return view('cashier-members');
});

Route::get('/promosi', function () {
    return view('promosi');
});

Route::get('/kasir/reports', function () {
    return view('cashier-reports');
});

Route::get('/kelola-akun', function () {
    return view('kelola-akun');
});

Route::get('/kasir/retur', function () {
    return view('cashier-retur');
});

Route::get('/umkm-partners', function () {
    return view('umkm-partners');
});

Route::get('/kasir/settings', function () {
    return view('cashier-settings');
});








