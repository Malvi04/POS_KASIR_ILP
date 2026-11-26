<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| POS Background Transaction Sync Scheduler (Local Cashier -> Central Server)
|--------------------------------------------------------------------------
| Berjalan otomatis setiap 1 menit di background dengan proteksi
| withoutOverlapping() hanya jika instance berperan sebagai node 'local'.
*/
Schedule::command('pos:sync-transactions')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => strtolower((string) config('pos_sync.node_role', 'central')) === 'local');

/*
|--------------------------------------------------------------------------
| POS Background Master Data Pull Sync Scheduler (Central Server -> Local Cashier)
|--------------------------------------------------------------------------
| Berjalan otomatis setiap 5 menit di background dengan proteksi
| withoutOverlapping() hanya jika instance berperan sebagai node 'local'.
*/
Schedule::command('pos:pull-master')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->when(fn () => strtolower((string) config('pos_sync.node_role', 'central')) === 'local');

Schedule::command('pos:pull-master --entity=goods_receipts')
    ->everyMinute()
    ->withoutOverlapping()
    ->when(fn () => strtolower((string) config('pos_sync.node_role', 'central')) === 'local');
