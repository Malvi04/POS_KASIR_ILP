<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\SyncState;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class PosPullSyncService
{
    public const LOCK_KEY = 'pos_sync_pull_lock';
    public const LOCK_SECONDS = 300;

    /**
     * Parent-first execution order for master data entities.
     *
     * @var array<int, string>
     */
    public const ENTITIES_ORDER = [
        'categories',
        'locations',
        'suppliers',
        'products',
        'employees',
    ];

    /**
     * Execute Pull Sync of master data from Central Warehouse Server into local PostgreSQL.
     * Protected by `Cache::lock('pos_sync_pull_lock', 300)`.
     *
     * @param callable|null $onProgress Optional callback `fn(string $level, string $message): void`
     * @return array{
     *     status: string,
     *     message: string,
     *     entities: array<string, array{status: string, upserted: int, error: ?string}>
     * }
     */
    public function pull(
        ?string $requestedEntity = null,
        bool $isFullSync = false,
        bool $force = false,
        ?callable $onProgress = null
    ): array {
        $emit = function (string $level, string $msg) use ($onProgress): void {
            if ($onProgress !== null) {
                $onProgress($level, $msg);
            }
        };

        $nodeRole = strtolower((string) config('pos_sync.node_role', 'central'));
        if ($nodeRole !== 'local' && !$force) {
            $msg = "Instance saat ini berperan sebagai [{$nodeRole}]. Pull sync hanya berjalan pada node [local] (gunakan --force untuk memaksa).";
            $emit('info', $msg);

            return [
                'status' => 'skipped_role',
                'message' => $msg,
                'entities' => [],
            ];
        }

        $centralUrl = rtrim((string) config('pos_sync.central_api_url', ''), '/');
        $centralToken = trim((string) config('pos_sync.central_api_token', ''));
        $perPage = max(1, min(1000, (int) config('pos_sync.pull_per_page', 200)));
        $timeout = max(3, (int) config('pos_sync.timeout_seconds', 15));
        $retryTimes = max(1, (int) config('pos_sync.retry_times', 3));
        $retrySleepMs = max(100, (int) config('pos_sync.retry_sleep_ms', 500));

        if ($centralUrl === '') {
            $msg = 'CENTRAL_API_URL belum dikonfigurasi.';
            $emit('warn', $msg);

            return [
                'status' => 'unconfigured',
                'message' => $msg,
                'entities' => [],
            ];
        }

        $cleanEntity = strtolower(trim((string) $requestedEntity));
        $allowedEntities = array_merge(self::ENTITIES_ORDER, ['goods_receipts', 'goods-receipts']);
        if ($cleanEntity !== '') {
            if (!in_array($cleanEntity, $allowedEntities, true)) {
                $msg = "Entitas [{$cleanEntity}] tidak valid. Pilihan: " . implode(', ', array_merge(self::ENTITIES_ORDER, ['goods_receipts']));
                $emit('error', $msg);

                return [
                    'status' => 'invalid_entity',
                    'message' => $msg,
                    'entities' => [],
                ];
            }
            $entities = [$cleanEntity === 'goods-receipts' ? 'goods_receipts' : $cleanEntity];
        } else {
            $entities = self::ENTITIES_ORDER;
        }

        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);
        if (!$lock->get()) {
            $msg = 'Proses pull sync sedang berjalan di proses lain (Cache lock aktif).';
            $emit('warn', $msg);

            return [
                'status' => 'locked',
                'message' => $msg,
                'entities' => [],
            ];
        }

        try {
            $entitySummaries = [];

            foreach ($entities as $entity) {
                $res = $this->pullEntity(
                    entity: $entity,
                    centralUrl: $centralUrl,
                    centralToken: $centralToken,
                    perPage: $perPage,
                    timeout: $timeout,
                    retryTimes: $retryTimes,
                    retrySleepMs: $retrySleepMs,
                    isFullSync: $isFullSync,
                    emit: $emit
                );

                $entitySummaries[$entity] = $res;

                if ($res['status'] !== 'success') {
                    // Stop dependent child entities if a parent entity failed or network disconnected
                    return [
                        'status' => 'failed',
                        'message' => $res['error'] ?? "Pull sync berhenti pada entitas [{$entity}].",
                        'entities' => $entitySummaries,
                    ];
                }
            }

            $doneMsg = 'Pull sync seluruh data master selesai.';
            $emit('info', $doneMsg);

            return [
                'status' => 'success',
                'message' => $doneMsg,
                'entities' => $entitySummaries,
            ];
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * Pull all pages for a single master entity, upsert into local PostgreSQL,
     * and atomically advance sync_states.last_synced_at to server_time only when all pages succeed.
     *
     * @return array{status: string, upserted: int, error: ?string}
     */
    protected function pullEntity(
        string $entity,
        string $centralUrl,
        string $centralToken,
        int $perPage,
        int $timeout,
        int $retryTimes,
        int $retrySleepMs,
        bool $isFullSync,
        callable $emit
    ): array {
        $syncState = SyncState::firstOrCreate(
            ['entity' => $entity],
            ['last_status' => 'idle']
        );

        $updatedSince = (!$isFullSync && $syncState->last_synced_at)
            ? $syncState->last_synced_at->copy()->utc()->toIso8601String()
            : null;

        $page = 1;
        $lastPage = 1;
        $capturedServerTime = null;
        $totalUpserted = 0;

        try {
            do {
                $queryParams = [
                    'page' => $page,
                    'per_page' => $perPage,
                ];
                if ($updatedSince !== null) {
                    $queryParams['updated_since'] = $updatedSince;
                }
                if ($entity === 'goods_receipts') {
                    // This node may serve several store branches (cashier selects branch at login)
                    $queryParams['location_uuids'] = implode(',', Location::query()
                        ->where('type', '!=', 'WAREHOUSE')
                        ->whereNotNull('uuid')
                        ->pluck('uuid')
                        ->all());
                }

                $endpointSlug = $entity === 'goods_receipts' ? 'goods-receipts' : $entity;
                $endpoint = "{$centralUrl}/api/v1/sync/master/{$endpointSlug}";

                $response = Http::withToken($centralToken)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->retry($retryTimes, $retrySleepMs, function (Throwable $exception) {
                        return $exception instanceof ConnectionException;
                    }, throw: false)
                    ->get($endpoint, $queryParams);

                if (!$response->successful()) {
                    $httpErr = "HTTP {$response->status()}: " . ($response->json('message') ?? $response->body());
                    $this->recordEntityFailure($syncState, $entity, $httpErr, $emit);

                    return [
                        'status' => 'failed',
                        'upserted' => $totalUpserted,
                        'error' => $httpErr,
                    ];
                }

                $payload = $response->json();
                if ($page === 1) {
                    $capturedServerTime = $payload['server_time'] ?? null;
                }

                $lastPage = max(1, (int) ($payload['last_page'] ?? 1));
                $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];

                if (!empty($rows)) {
                    $this->upsertEntityBatch($entity, $rows);
                    $totalUpserted += count($rows);
                }

                if ($lastPage > 5 && ($page % 10 === 0 || $page === $lastPage)) {
                    $emit('line', "   ... [{$entity}] halaman {$page}/{$lastPage} ({$totalUpserted} record)");
                }

                $page++;
            } while ($page <= $lastPage);

            // Advance cursor ONLY after all pages for this entity succeeded
            $nextSyncedAt = $capturedServerTime
                ? Carbon::parse($capturedServerTime)->setTimezone(config('app.timezone'))
                : ($syncState->last_synced_at ?? now());

            $syncState->update([
                'last_synced_at' => $nextSyncedAt,
                'last_status' => 'success',
                'last_error' => null,
            ]);

            $emit('info', " - [{$entity}] berhasil disinkronkan ({$totalUpserted} record).");

            return [
                'status' => 'success',
                'upserted' => $totalUpserted,
                'error' => null,
            ];
        } catch (ConnectionException $e) {
            $connMsg = 'Koneksi ke Server Pusat terputus (Offline): ' . $e->getMessage();
            $this->recordEntityFailure($syncState, $entity, $connMsg, $emit);

            return [
                'status' => 'failed',
                'upserted' => $totalUpserted,
                'error' => $connMsg,
            ];
        } catch (Throwable $e) {
            $errMsg = "Gagal menyinkronkan entitas [{$entity}]: " . $e->getMessage();
            $this->recordEntityFailure($syncState, $entity, $errMsg, $emit);

            return [
                'status' => 'failed',
                'upserted' => $totalUpserted,
                'error' => $errMsg,
            ];
        }
    }

    protected function recordEntityFailure(SyncState $syncState, string $entity, string $errorMessage, callable $emit): void
    {
        Log::warning("[POS Pull Master] Entitas [{$entity}] gagal: {$errorMessage}");
        $emit('warn', " - [{$entity}] gagal: {$errorMessage}");

        $syncState->update([
            'last_status' => 'failed',
            'last_error' => mb_substr($errorMessage, 0, 1000),
        ]);
    }

    protected function upsertEntityBatch(string $entity, array $rows): void
    {
        DB::transaction(function () use ($entity, $rows) {
            match ($entity) {
                'categories' => $this->upsertCategories($rows),
                'locations' => $this->upsertLocations($rows),
                'suppliers' => $this->upsertSuppliers($rows),
                'products' => $this->upsertProducts($rows),
                'employees' => $this->upsertEmployees($rows),
                'goods_receipts' => $this->upsertGoodsReceipts($rows),
                default => null,
            };
        });
    }

    protected function upsertCategories(array $rows): void
    {
        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $uuid = (string) ($row['uuid'] ?? '');
            $name = trim((string) ($row['name'] ?? ''));
            if ($uuid === '' || $name === '') {
                continue;
            }

            DB::table('product_categories')
                ->where('name', $name)
                ->where('uuid', '!=', $uuid)
                ->update(['uuid' => $uuid]);

            $payload[] = [
                'uuid' => $uuid,
                'name' => $name,
                'created_at' => !empty($row['created_at']) ? Carbon::parse($row['created_at']) : $now,
                'updated_at' => !empty($row['updated_at']) ? Carbon::parse($row['updated_at']) : $now,
            ];
        }

        if (!empty($payload)) {
            ProductCategory::upsert($payload, ['uuid'], ['name', 'updated_at']);
        }
    }

    protected function upsertLocations(array $rows): void
    {
        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $uuid = (string) ($row['uuid'] ?? '');
            $code = trim((string) ($row['code'] ?? ''));
            if ($uuid === '' || $code === '') {
                continue;
            }

            DB::table('locations')
                ->where('code', $code)
                ->where('uuid', '!=', $uuid)
                ->update(['uuid' => $uuid]);

            $payload[] = [
                'uuid' => $uuid,
                'code' => $code,
                'name' => $row['name'] ?? $code,
                'type' => $row['type'] ?? 'STORE',
                'address' => $row['address'] ?? null,
                'manager' => $row['manager'] ?? null,
                'manager_email' => $row['manager_email'] ?? null,
                'phone' => $row['phone'] ?? null,
                'status' => $row['status'] ?? 'AKTIF',
                'is_active' => (bool) ($row['is_active'] ?? true),
                'created_at' => !empty($row['created_at']) ? Carbon::parse($row['created_at']) : $now,
                'updated_at' => !empty($row['updated_at']) ? Carbon::parse($row['updated_at']) : $now,
            ];
        }

        if (!empty($payload)) {
            Location::upsert($payload, ['uuid'], [
                'code', 'name', 'type', 'address', 'manager', 'manager_email',
                'phone', 'status', 'is_active', 'updated_at',
            ]);
        }
    }

    protected function upsertSuppliers(array $rows): void
    {
        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $uuid = (string) ($row['uuid'] ?? '');
            $code = trim((string) ($row['code'] ?? ''));
            if ($uuid === '' || $code === '') {
                continue;
            }

            DB::table('suppliers')
                ->where('code', $code)
                ->where('uuid', '!=', $uuid)
                ->update(['uuid' => $uuid]);

            $businessCategories = $row['business_categories'] ?? null;
            if (is_array($businessCategories)) {
                $businessCategories = json_encode($businessCategories);
            }

            $payload[] = [
                'uuid' => $uuid,
                'code' => $code,
                'name' => $row['name'] ?? $code,
                'company_name' => $row['company_name'] ?? null,
                'owner_name' => $row['owner_name'] ?? null,
                'pic_name' => $row['pic_name'] ?? null,
                'phone' => $row['phone'] ?? null,
                'email' => $row['email'] ?? null,
                'address' => $row['address'] ?? null,
                'city' => $row['city'] ?? null,
                'category' => $row['category'] ?? 'Makanan Ringan',
                'business_categories' => $businessCategories,
                'commission_percent' => (float) ($row['commission_percent'] ?? 15),
                'settlement_period' => $row['settlement_period'] ?? 'BULANAN',
                'bank_name' => $row['bank_name'] ?? null,
                'bank_account' => $row['bank_account'] ?? null,
                'status' => $row['status'] ?? 'AKTIF',
                'supplier_type' => $row['supplier_type'] ?? Supplier::TYPE_REGULAR,
                'notes' => $row['notes'] ?? null,
                'created_at' => !empty($row['created_at']) ? Carbon::parse($row['created_at']) : $now,
                'updated_at' => !empty($row['updated_at']) ? Carbon::parse($row['updated_at']) : $now,
            ];
        }

        if (!empty($payload)) {
            Supplier::upsert($payload, ['uuid'], [
                'code', 'name', 'company_name', 'owner_name', 'pic_name', 'phone', 'email',
                'address', 'city', 'category', 'business_categories', 'commission_percent',
                'settlement_period', 'bank_name', 'bank_account', 'status', 'supplier_type',
                'notes', 'updated_at',
            ]);
        }
    }

    protected function upsertProducts(array $rows): void
    {
        $categoryUuids = array_values(array_unique(array_filter(array_column($rows, 'category_uuid'))));
        $supplierUuids = array_values(array_unique(array_filter(array_column($rows, 'supplier_uuid'))));
        $batchProductUuids = array_values(array_unique(array_filter(array_column($rows, 'uuid'))));

        $categoryIdByUuid = !empty($categoryUuids)
            ? ProductCategory::whereIn('uuid', $categoryUuids)->pluck('id', 'uuid')->all()
            : [];
        $categoryNameByUuid = !empty($categoryUuids)
            ? ProductCategory::whereIn('uuid', $categoryUuids)->pluck('name', 'uuid')->all()
            : [];
        $supplierIdByUuid = !empty($supplierUuids)
            ? Supplier::whereIn('uuid', $supplierUuids)->pluck('id', 'uuid')->all()
            : [];

        // Reconcile natural key `sku` in bulk before querying pending local stock
        $batchSkus = array_values(array_unique(array_filter(array_map(
            fn ($r) => trim((string) ($r['sku'] ?? '')),
            $rows
        ))));
        $existingUuidBySku = !empty($batchSkus)
            ? DB::table('products')->whereIn('sku', $batchSkus)->pluck('uuid', 'sku')->all()
            : [];

        foreach ($rows as $row) {
            $uuid = (string) ($row['uuid'] ?? '');
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($uuid !== '' && $sku !== '' && isset($existingUuidBySku[$sku]) && $existingUuidBySku[$sku] !== $uuid) {
                DB::table('products')
                    ->where('sku', $sku)
                    ->update(['uuid' => $uuid]);
            }
        }

        // Calculate pending unsynced local transaction quantities per product UUID
        $pendingQtyByProductUuid = [];
        if (!empty($batchProductUuids)) {
            $pendingQtyByProductUuid = DB::table('transaction_items')
                ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
                ->join('products', 'products.id', '=', 'transaction_items.product_id')
                ->whereNull('transactions.synced_at')
                ->where('transactions.status', 'SUCCESS')
                ->whereIn('products.uuid', $batchProductUuids)
                ->groupBy('products.uuid')
                ->selectRaw('products.uuid as product_uuid, COALESCE(SUM(transaction_items.qty), 0) as pending_qty')
                ->pluck('pending_qty', 'product_uuid')
                ->all();
        }

        $pullSyncStock = (bool) config('pos_sync.pull_sync_stock', true);
        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $uuid = (string) ($row['uuid'] ?? '');
            $sku = trim((string) ($row['sku'] ?? ''));
            if ($uuid === '' || $sku === '') {
                continue;
            }

            $catUuid = $row['category_uuid'] ?? null;
            $supUuid = $row['supplier_uuid'] ?? null;

            $localCategoryId = ($catUuid && isset($categoryIdByUuid[$catUuid]))
                ? (int) $categoryIdByUuid[$catUuid]
                : null;
            $localCategoryName = ($catUuid && isset($categoryNameByUuid[$catUuid]))
                ? (string) $categoryNameByUuid[$catUuid]
                : ((string) ($row['category'] ?? 'Umum'));
            $localSupplierId = ($supUuid && isset($supplierIdByUuid[$supUuid]))
                ? (int) $supplierIdByUuid[$supUuid]
                : null;

            $centralStock = (int) ($row['stock'] ?? $row['qty_produk'] ?? 0);
            $pendingLocalQty = (int) ($pendingQtyByProductUuid[$uuid] ?? 0);
            $effectiveLocalStock = $centralStock - $pendingLocalQty;

            $price = (float) ($row['price'] ?? $row['harga_jual_umum'] ?? 0);
            $memberPrice = (float) ($row['member_price'] ?? $row['harga_jual_member'] ?? $price);
            $costPrice = (float) ($row['cost_price'] ?? $row['harga_beli'] ?? 0);
            $lastCostPrice = (float) ($row['last_cost_price'] ?? $row['harga_beli_terakhir'] ?? $costPrice);
            $minStock = (int) ($row['min_stock'] ?? $row['minimum_stock'] ?? 5);
            $unit = (string) ($row['unit'] ?? $row['satuan_unit'] ?? 'Pcs');
            $barcode = (string) ($row['barcode'] ?? $row['kode_barcode'] ?? $sku);
            $name = (string) ($row['name'] ?? $row['nama_produk'] ?? $sku);
            $taxRate = (float) ($row['tax_rate'] ?? $row['ppn_out'] ?? 11);
            $isTaxable = (bool) ($row['is_taxable'] ?? true);
            $discPercent = (float) ($row['clearance_discount_percent'] ?? $row['produk_disc'] ?? 0);
            $status = (string) ($row['status'] ?? $row['status_barang'] ?? 'TERSEDIA');

            $payload[] = [
                'uuid' => $uuid,
                'sku' => $sku,
                'barcode' => $barcode,
                'name' => $name,
                'brand' => $row['brand'] ?? null,
                'category' => $localCategoryName,
                'category_id' => $localCategoryId,
                'supplier_id' => $localSupplierId,
                'unit' => $unit,
                'cost_price' => $costPrice,
                'last_cost_price' => $lastCostPrice,
                'price' => $price,
                'member_price' => $memberPrice,
                'stock' => $effectiveLocalStock,
                'min_stock' => $minStock,
                'is_taxable' => $isTaxable,
                'tax_rate' => $taxRate,
                'expired_date' => $row['expired_date'] ?? null,
                'manufacturing_date' => $row['manufacturing_date'] ?? null,
                'clearance_discount_percent' => $discPercent,
                'status' => $status,
                'is_consignment' => (bool) ($row['is_consignment'] ?? false),
                'commission_percent' => (float) ($row['commission_percent'] ?? 0),
                'rack_location' => $row['rack_location'] ?? null,
                'id_produk' => $row['id_produk'] ?? $sku,
                'kode_barcode' => $row['kode_barcode'] ?? $barcode,
                'kode_produk' => $row['kode_produk'] ?? $sku,
                'qty_produk' => (float) $effectiveLocalStock,
                'nama_produk' => $row['nama_produk'] ?? $name,
                'satuan_unit' => $row['satuan_unit'] ?? $unit,
                'harga_beli' => $costPrice,
                'harga_jual_umum' => $price,
                'harga_jual_member' => $memberPrice,
                'produk_disc' => $discPercent,
                'minimum_stock' => $minStock,
                'ppn_out' => $taxRate,
                'ppn_chk' => $row['ppn_chk'] ?? ($isTaxable ? 'Y' : 'N'),
                'harga_beli_terakhir' => $lastCostPrice,
                'status_barang' => $row['status_barang'] ?? $status,
                'kode_suplier' => $row['kode_suplier'] ?? null,
                'deleted_at' => !empty($row['deleted_at']) ? Carbon::parse($row['deleted_at']) : null,
                'created_at' => !empty($row['created_at']) ? Carbon::parse($row['created_at']) : $now,
                'updated_at' => !empty($row['updated_at']) ? Carbon::parse($row['updated_at']) : $now,
            ];
        }

        if (empty($payload)) {
            return;
        }

        $updateColumns = [
            'sku', 'barcode', 'name', 'brand', 'category', 'category_id', 'supplier_id',
            'unit', 'cost_price', 'last_cost_price', 'price', 'member_price', 'min_stock',
            'is_taxable', 'tax_rate', 'expired_date', 'manufacturing_date',
            'clearance_discount_percent', 'status', 'is_consignment', 'commission_percent',
            'rack_location', 'id_produk', 'kode_barcode', 'kode_produk', 'nama_produk',
            'satuan_unit', 'harga_beli', 'harga_jual_umum', 'harga_jual_member',
            'produk_disc', 'minimum_stock', 'ppn_out', 'ppn_chk', 'harga_beli_terakhir',
            'status_barang', 'kode_suplier', 'deleted_at', 'updated_at',
        ];

        if ($pullSyncStock) {
            $updateColumns[] = 'stock';
            $updateColumns[] = 'qty_produk';
        }

        foreach (array_chunk($payload, 500) as $productChunk) {
            Product::upsert($productChunk, ['uuid'], $updateColumns);
        }

        // Sync branch-filtered `location_stocks` into local `product_stocks` with pending local qty compensation
        $hasAnyLocationStocks = false;
        foreach ($rows as $r) {
            if (!empty($r['location_stocks']) && is_array($r['location_stocks'])) {
                $hasAnyLocationStocks = true;
                break;
            }
        }

        if ($hasAnyLocationStocks) {
            $localLocationIdByUuid = Location::query()->pluck('id', 'uuid')->all();
            $localProductIdByUuid = Product::withTrashed()->whereIn('uuid', $batchProductUuids)->pluck('id', 'uuid')->all();

            $pendingQtyByProdAndLoc = [];
            if (!empty($batchProductUuids)) {
                $pendingLocRows = DB::table('transaction_items')
                    ->join('transactions', 'transactions.id', '=', 'transaction_items.transaction_id')
                    ->join('products', 'products.id', '=', 'transaction_items.product_id')
                    ->whereNull('transactions.synced_at')
                    ->where('transactions.status', 'SUCCESS')
                    ->whereIn('products.uuid', $batchProductUuids)
                    ->groupBy('products.uuid', 'transactions.location_id')
                    ->selectRaw('products.uuid as product_uuid, transactions.location_id, COALESCE(SUM(transaction_items.qty), 0) as pending_qty')
                    ->get();

                foreach ($pendingLocRows as $pr) {
                    $keyLoc = $pr->location_id !== null ? (string) $pr->location_id : 'any';
                    $pendingQtyByProdAndLoc[$pr->product_uuid . ':' . $keyLoc] = (int) $pr->pending_qty;
                }
            }

            $stockBatch = [];
            foreach ($rows as $row) {
                $uuid = (string) ($row['uuid'] ?? '');
                $localProductId = $localProductIdByUuid[$uuid] ?? null;
                if (!$localProductId || empty($row['location_stocks']) || !is_array($row['location_stocks'])) {
                    continue;
                }

                foreach ($row['location_stocks'] as $ls) {
                    $locUuid = $ls['location_uuid'] ?? null;
                    $localLocId = ($locUuid && isset($localLocationIdByUuid[$locUuid]))
                        ? (int) $localLocationIdByUuid[$locUuid]
                        : null;
                    if (!$localLocId) {
                        continue;
                    }

                    $centralLocStock = (int) ($ls['stock'] ?? 0);
                    $pendingLocQty = (int) ($pendingQtyByProdAndLoc[$uuid . ':' . $localLocId] ?? 0)
                        + (int) ($pendingQtyByProdAndLoc[$uuid . ':any'] ?? 0);
                    $effectiveLocStock = $centralLocStock - $pendingLocQty;

                    $stockBatch[] = [
                        'product_id' => $localProductId,
                        'location_id' => $localLocId,
                        'stock' => $effectiveLocStock,
                        'min_stock' => (int) ($ls['min_stock'] ?? $row['min_stock'] ?? 5),
                        'is_active' => (bool) ($ls['is_active'] ?? true),
                        'version' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }

            if (!empty($stockBatch)) {
                $stockUpdateCols = ['min_stock', 'is_active', 'updated_at'];
                if ($pullSyncStock) {
                    $stockUpdateCols[] = 'stock';
                }

                foreach (array_chunk($stockBatch, 1000) as $stockChunk) {
                    DB::table('product_stocks')->upsert(
                        $stockChunk,
                        ['product_id', 'location_id'],
                        $stockUpdateCols
                    );
                }
            }
        }
    }

    protected function upsertEmployees(array $rows): void
    {
        $locationUuids = array_values(array_unique(array_filter(array_column($rows, 'location_uuid'))));
        $locationIdByUuid = !empty($locationUuids)
            ? Location::whereIn('uuid', $locationUuids)->pluck('id', 'uuid')->all()
            : [];

        $now = now();
        $payload = [];

        foreach ($rows as $row) {
            $uuid = (string) ($row['uuid'] ?? '');
            if ($uuid === '') {
                continue;
            }

            $username = !empty($row['username']) ? trim((string) $row['username']) : null;
            $email = !empty($row['email']) ? trim((string) $row['email']) : ($username ? "{$username}@minimarket.ilp" : "{$uuid}@minimarket.ilp");
            $nik = !empty($row['nik']) ? trim((string) $row['nik']) : null;

            DB::table('users')
                ->where('uuid', '!=', $uuid)
                ->where(function ($q) use ($username, $email, $nik) {
                    $q->where('email', $email);
                    if ($username) {
                        $q->orWhere('username', $username);
                    }
                    if ($nik) {
                        $q->orWhere('nik', $nik);
                    }
                })
                ->update(['uuid' => $uuid]);

            $locUuid = $row['location_uuid'] ?? null;
            $localLocationId = ($locUuid && isset($locationIdByUuid[$locUuid]))
                ? (int) $locationIdByUuid[$locUuid]
                : null;

            $assignedBranches = $row['assigned_branches'] ?? null;
            if (is_array($assignedBranches)) {
                $assignedBranches = json_encode($assignedBranches);
            }

            $payload[] = [
                'uuid' => $uuid,
                'name' => $row['name'] ?? ($username ?? 'Karyawan'),
                'username' => $username,
                'email' => $email,
                'nik' => $nik,
                'role' => $row['role'] ?? 'Kasir',
                'pin' => (string) ($row['pin'] ?? '123456'),
                'branch' => $row['branch'] ?? 'Pusat',
                'location_id' => $localLocationId,
                'assigned_branches' => $assignedBranches,
                'is_active' => (bool) ($row['is_active'] ?? true),
                'password' => (string) ($row['password'] ?? ''),
                'deleted_at' => !empty($row['deleted_at']) ? Carbon::parse($row['deleted_at']) : null,
                'created_at' => !empty($row['created_at']) ? Carbon::parse($row['created_at']) : $now,
                'updated_at' => !empty($row['updated_at']) ? Carbon::parse($row['updated_at']) : $now,
            ];
        }

        if (!empty($payload)) {
            User::upsert($payload, ['uuid'], [
                'name', 'username', 'email', 'nik', 'role', 'pin', 'branch',
                'location_id', 'assigned_branches', 'is_active', 'password',
                'deleted_at', 'updated_at',
            ]);
        }
    }

    /**
     * Upsert GoodsReceipt documents (Surat Jalan Barang Masuk) dispatched from Central Warehouse to this Store.
     * Preserves local verification state if the store cashier already verified the receipt locally.
     */
    protected function upsertGoodsReceipts(array $rows): void
    {
        if (empty($rows)) {
            return;
        }

        $locationIdByUuid = Location::query()->pluck('id', 'uuid')->all();
        $userIdByUuid = User::query()->pluck('id', 'uuid')->all();
        $now = now()->addSecond();

        foreach ($rows as $row) {
            $uuid = (string) ($row['uuid'] ?? '');
            $receiptNo = trim((string) ($row['receipt_number'] ?? ''));
            if ($uuid === '' || $receiptNo === '') {
                continue;
            }

            $existing = \App\Models\GoodsReceipt::where('uuid', $uuid)
                ->orWhere('receipt_number', $receiptNo)
                ->first();

            // Do not overwrite a locally verified receipt that hasn't pushed yet
            if ($existing && $existing->verified_at !== null && $existing->synced_at === null) {
                continue;
            }

            $fromLocId = !empty($row['from_location_uuid']) ? ($locationIdByUuid[$row['from_location_uuid']] ?? null) : null;
            $toLocId = !empty($row['to_location_uuid']) ? ($locationIdByUuid[$row['to_location_uuid']] ?? null) : null;
            if (!$toLocId && !empty($row['destination_store'])) {
                $toLocId = app(LocationStockService::class)->resolveLocation(null, $row['destination_store'], null, null, 'STORE')?->id;
            }

            $sentBy = !empty($row['sender_uuid']) ? ($userIdByUuid[$row['sender_uuid']] ?? null) : null;
            $receivedBy = !empty($row['receiver_uuid']) ? ($userIdByUuid[$row['receiver_uuid']] ?? null) : null;

            $attrs = [
                'uuid' => $uuid,
                'receipt_number' => $receiptNo,
                'from_location_id' => $fromLocId,
                'to_location_id' => $toLocId,
                'sent_by' => $sentBy,
                'received_by' => $receivedBy,
                'origin_source' => $row['origin_source'] ?? 'Gudang Pusat',
                'destination_store' => $row['destination_store'] ?? 'Cabang Pusat',
                'driver_name' => $row['driver_name'] ?? '-',
                'plate_number' => $row['plate_number'] ?? '-',
                'status' => $row['status'] ?? 'BELUM_SAMPAI',
                'notes' => $row['notes'] ?? '',
                'received_at' => !empty($row['received_at']) ? Carbon::parse($row['received_at']) : now(),
                'verified_at' => !empty($row['verified_at']) ? Carbon::parse($row['verified_at']) : null,
                'synced_at' => $now,
            ];

            if ($existing) {
                $existing->timestamps = false;
                $existing->forceFill(array_merge($attrs, [
                    'updated_at' => !empty($row['updated_at']) ? Carbon::parse($row['updated_at']) : now(),
                ]))->saveQuietly();
                $existing->timestamps = true;
                $gr = $existing;
            } else {
                $gr = \App\Models\GoodsReceipt::create($attrs);
            }

            $itemPayloads = is_array($row['items'] ?? null) ? $row['items'] : [];
            if (!empty($itemPayloads)) {
                $prodUuids = array_values(array_unique(array_filter(array_column($itemPayloads, 'product_uuid'))));
                $productIdByUuid = !empty($prodUuids)
                    ? Product::withTrashed()->whereIn('uuid', $prodUuids)->pluck('id', 'uuid')->all()
                    : [];

                foreach ($itemPayloads as $it) {
                    $pUuid = (string) ($it['product_uuid'] ?? '');
                    $localProdId = $productIdByUuid[$pUuid] ?? null;
                    if (!$localProdId) {
                        continue;
                    }
                    $itUuid = !empty($it['uuid']) ? (string) $it['uuid'] : (string) \Illuminate\Support\Str::uuid7();

                    \App\Models\GoodsReceiptItem::updateOrCreate(
                        ['goods_receipt_id' => $gr->id, 'product_id' => $localProdId],
                        [
                            'uuid' => $itUuid,
                            'qty_sent' => (float) ($it['qty_sent'] ?? 0),
                            'qty_received' => (float) ($it['qty_received'] ?? 0),
                            'actual_unit_cost' => (float) ($it['actual_unit_cost'] ?? 0),
                            'condition' => $it['condition'] ?? 'BAIK',
                            'expired_date' => $it['expired_date'] ?? null,
                            'batch_number' => $it['batch_number'] ?? null,
                            'notes' => $it['notes'] ?? '',
                        ]
                    );
                }
            }
        }
    }
}
