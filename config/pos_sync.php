<?php

return [
    /*
    |--------------------------------------------------------------------------
    | POS Instance Node Role
    |--------------------------------------------------------------------------
    | Menentukan peran instance Laravel saat ini:
    | - 'local'   : PC Kasir lokal (mengirim transaksi secara background ke Server Pusat)
    | - 'central' : Server Pusat (menerima sinkronisasi transaksi dari seluruh PC Kasir)
    */
    'node_role' => env('POS_NODE_ROLE', 'central'),

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
    | Master Data Pull Sync Settings (Central Server -> Local Cashier)
    |--------------------------------------------------------------------------
    */
    'pull_per_page' => max(1, min(1000, (int) env('POS_PULL_PER_PAGE', 200))),

    'pull_sync_stock' => filter_var(env('POS_PULL_SYNC_STOCK', true), FILTER_VALIDATE_BOOLEAN),
];
