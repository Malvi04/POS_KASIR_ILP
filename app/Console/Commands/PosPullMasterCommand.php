<?php

namespace App\Console\Commands;

use App\Services\PosPullSyncService;
use Illuminate\Console\Command;

class PosPullMasterCommand extends Command
{
    /**
     * Backward-compatible alias constant pointing to PosPullSyncService::ENTITIES_ORDER.
     *
     * @var array<int, string>
     */
    public const ENTITIES_ORDER = PosPullSyncService::ENTITIES_ORDER;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pos:pull-master
                            {--full : Abaikan last_synced_at dan tarik seluruh data master}
                            {--entity= : Tarik satu entitas spesifik saja (categories|locations|suppliers|products|employees)}
                            {--force : Jalankan meskipun POS_NODE_ROLE bukan local}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pull Sync data master (kategori, lokasi, supplier, produk, karyawan) dari Server Pusat ke PostgreSQL lokal berbasis Dual-Key UUIDv7';

    /**
     * Execute the console command.
     */
    public function handle(PosPullSyncService $pullSyncService): int
    {
        $requestedEntity = trim((string) $this->option('entity'));
        $isFullSync = (bool) $this->option('full');
        $force = (bool) $this->option('force');

        $result = $pullSyncService->pull(
            requestedEntity: $requestedEntity !== '' ? $requestedEntity : null,
            isFullSync: $isFullSync,
            force: $force,
            onProgress: function (string $level, string $message): void {
                match ($level) {
                    'error' => $this->error($message),
                    'warn' => $this->warn($message),
                    'line' => $this->line($message),
                    default => $this->info($message),
                };
            }
        );

        return $result['status'] === 'invalid_entity' ? self::FAILURE : self::SUCCESS;
    }
}
