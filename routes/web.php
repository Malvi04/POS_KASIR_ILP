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
