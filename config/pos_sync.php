<?php

return [
    /*
    |--------------------------------------------------------------------------
    | POS Instance Node Role
    |--------------------------------------------------------------------------
    | Menentukan peran instance Laravel saat ini:
    | - 'local'   : PC Kasir lokal (mengirim transaksi & menarik master dari Server Pusat)
    | - 'central' : Server Pusat di Gudang (menerima sinkronisasi dari seluruh PC Kasir)
    */
    'node_role' => env('POS_NODE_ROLE', 'central'),

    /*
    |--------------------------------------------------------------------------
    | Local Branch & Cashier Terminal Identity (Multi-Branch & Multi-Terminal)
    |--------------------------------------------------------------------------
    | Digunakan pada PC Kasir lokal (POS_NODE_ROLE=local) untuk identitas struk
    | anti-bentrok (INV-{BRANCH_CODE}-{TERMINAL_CODE}-{YYYYMMDD}-{SEQUENCE}) dan
    | pengisian otomatis kolom cabang/terminal pada transaksi lokal.
    */
    'branch_uuid' => (string) env('POS_BRANCH_UUID', ''),

    'branch_code' => strtoupper(trim((string) env('POS_BRANCH_CODE', ''))),

    'terminal_code' => strtoupper(trim((string) env('POS_TERMINAL_CODE', ''))),

    /*
    |--------------------------------------------------------------------------
    | Central Server REST API Configuration
    |--------------------------------------------------------------------------
    */
    'central_api_url' => rtrim((string) env('CENTRAL_API_URL', 'http://127.0.0.1:8000'), '/'),

    'central_api_token' => (string) env('CENTRAL_API_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Background Sync Batch & HTTP Client Settings
    |--------------------------------------------------------------------------
    */
    'batch_size' => max(1, (int) env('POS_SYNC_BATCH_SIZE', 50)),

    'timeout_seconds' => max(3, (int) env('POS_SYNC_TIMEOUT', 15)),

    'retry_times' => max(1, (int) env('POS_SYNC_RETRY_TIMES', 3)),

    'retry_sleep_ms' => max(100, (int) env('POS_SYNC_RETRY_SLEEP_MS', 500)),

    /*
    |--------------------------------------------------------------------------
    | Master Data Pull Sync & Local Stock Tolerance Settings
    |--------------------------------------------------------------------------
    */
    'pull_per_page' => max(1, min(1000, (int) env('POS_PULL_PER_PAGE', 200))),

    'pull_sync_stock' => filter_var(env('POS_PULL_SYNC_STOCK', true), FILTER_VALIDATE_BOOLEAN),

    // Mengizinkan checkout lokal tetap jalan saat stok lokal sempat minus tipis akibat jeda sync antar kasir
    'allow_negative_local_stock' => filter_var(env('POS_ALLOW_NEGATIVE_LOCAL_STOCK', true), FILTER_VALIDATE_BOOLEAN),
];
