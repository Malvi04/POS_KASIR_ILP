<?php

namespace App\Console\Commands;

use App\Services\PosPushSyncService;
use Illuminate\Console\Command;

class PosSyncTransactionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:sync-transactions
                            {--batch= : Jumlah transaksi per batch pengiriman}
                            {--max-batches= : Batasi jumlah batch dalam satu kali eksekusi}
                            {--force : Jalankan meskipun POS_NODE_ROLE bukan local}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi transaksi kasir lokal yang belum terkirim (synced_at IS NULL) ke Server Pusat melalui REST API berbasis UUIDv7';

    /**
     * Execute the console command.
     */
    public function handle(PosPushSyncService $pushSyncService): int
    {
        $batchSize = $this->option('batch') !== null ? (int) $this->option('batch') : null;
        $maxBatches = $this->option('max-batches') !== null ? max(1, (int) $this->option('max-batches')) : null;
        $force = (bool) $this->option('force');

        $result = $pushSyncService->sync($batchSize, $maxBatches, $force);

        match ($result['status']) {
            'unconfigured', 'offline', 'locked' => $this->warn($result['message']),
            default => $this->info($result['message']),
        };

        return self::SUCCESS;
    }
}
