<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
