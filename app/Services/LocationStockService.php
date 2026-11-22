<?php

namespace App\Services;

use App\Models\CashierShift;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class LocationStockService
{
    /**
     * Ensure default 4 facilities exist if locations table is completely empty.
     */
    public function ensureDefaultLocations(): void
    {
        if (Location::count() > 0) {
            return;
        }

        $defaults = [
            ['code' => 'GDG-001', 'name' => 'Gudang Pusat', 'type' => 'WAREHOUSE', 'status' => 'AKTIF', 'is_active' => true],
            ['code' => 'CAB-001', 'name' => 'Cabang Pusat', 'type' => 'STORE', 'status' => 'AKTIF', 'is_active' => true],
            ['code' => 'CAB-002', 'name' => 'Cabang A', 'type' => 'STORE', 'status' => 'AKTIF', 'is_active' => true],
            ['code' => 'CAB-003', 'name' => 'Cabang B', 'type' => 'STORE', 'status' => 'AKTIF', 'is_active' => true],
        ];

        foreach ($defaults as $loc) {
            Location::firstOrCreate(['code' => $loc['code']], $loc);
        }
    }

    /**
     * Check whether store-level Stock Toko records exist in the database.
     */
    public function hasStoreStocks(): bool
    {
        return ProductStock::whereHas('location', fn ($q) => $q->where('type', '!=', 'WAREHOUSE'))->exists();
    }

    /**
     * Get active Warehouse (Gudang Pusat) location.
     */
    public function getWarehouseLocation(): ?Location
    {
        return Location::where('type', 'WAREHOUSE')
            ->where('is_active', true)
            ->orderBy('id')
            ->first()
            ?? Location::where('type', 'WAREHOUSE')->orderBy('id')->first();
    }

    /**
     * Get all active Store locations.
     */
    public function getStoreLocations(): Collection
    {
        return Location::where('type', '!=', 'WAREHOUSE')
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
    }

    /**
     * Resolve a Location by ID, branch name/code, terminal code, or user assignment.
     */
    public function resolveLocation(
        ?int $locationId = null,
        ?string $branchOrCode = null,
        ?string $terminalCode = null,
        ?User $user = null,
        ?string $expectedType = null
    ): ?Location {
        $baseQuery = function () use ($expectedType) {
            $q = Location::query();
            if ($expectedType === 'STORE') {
                $q->where('type', '!=', 'WAREHOUSE');
            } elseif ($expectedType === 'WAREHOUSE') {
                $q->where('type', 'WAREHOUSE');
            }
            return $q;
        };

        if ($locationId) {
            $loc = Location::find($locationId);
            if ($loc) {
                if ($expectedType === 'STORE' && $loc->type === 'WAREHOUSE') {
                    return null;
                }
                if ($expectedType === 'WAREHOUSE' && $loc->type !== 'WAREHOUSE') {
                    return null;
                }
                return $loc;
            }
        }

        if ($branchOrCode !== null && trim($branchOrCode) !== '') {
            $raw = trim($branchOrCode);
            $lower = strtolower($raw);

            // Exact code or exact case-insensitive name match
            $exact = $baseQuery()->where(function ($q) use ($raw, $lower) {
                $q->whereRaw('LOWER(code) = ?', [$lower])
                    ->orWhereRaw('LOWER(name) = ?', [$lower]);
            })->first();

            if ($exact) {
                return $exact;
            }

            // Alias matching for ILP standard facilities (exact code/name match first, then substring match)
            $aliases = $this->branchAliasCandidates($lower);
            if (!empty($aliases)) {
                $candidates = $baseQuery()->get();
                $matched = $candidates->first(function (Location $loc) use ($aliases) {
                    $nameL = strtolower($loc->name);
                    $codeL = strtolower($loc->code);
                    foreach ($aliases as $alias) {
                        if ($codeL === $alias || $nameL === $alias) {
                            return true;
                        }
                    }
                    return false;
                });
                if (!$matched) {
                    $matched = $candidates->first(function (Location $loc) use ($aliases) {
                        $nameL = strtolower($loc->name);
                        foreach ($aliases as $alias) {
                            if (str_contains($nameL, $alias)) {
                                return true;
                            }
                        }
                        return false;
                    });
                }
                if ($matched) {
                    return $matched;
                }
            }
        }

        if ($terminalCode !== null && trim($terminalCode) !== '') {
            $term = strtoupper(trim($terminalCode));

            $activeShift = CashierShift::where('terminal_code', $term)
                ->whereNotNull('location_id')
                ->latest('id')
                ->first();
            if ($activeShift && $activeShift->location_id) {
                $loc = Location::find($activeShift->location_id);
                if ($loc && ($expectedType !== 'STORE' || $loc->type !== 'WAREHOUSE')) {
                    return $loc;
                }
            }

            $expected = $this->resolveExpectedLocationForTerminal($term);
            if ($expected && ($expectedType !== 'WAREHOUSE')) {
                return $expected;
            }
        }

        if ($user && !empty($user->branch)) {
            $firstBranch = trim(explode(',', $user->branch)[0]);
            return $this->resolveLocation(null, $firstBranch, null, null, $expectedType);
        }

        return null;
    }

    /**
     * Resolve fixed physical store location assigned to a numbered POS terminal (POS-01..15).
     */
    public function resolveExpectedLocationForTerminal(?string $terminalCode): ?Location
    {
        if ($terminalCode === null || trim($terminalCode) === '') {
            return null;
        }
        $term = strtoupper(trim($terminalCode));
        if (!preg_match('/^POS-(\d+)$/', $term, $m)) {
            return null;
        }
        $num = (int) $m[1];
        $inferredBranch = null;
        if ($num >= 1 && $num <= 5) {
            $inferredBranch = 'Toko Pusat';
        } elseif ($num >= 6 && $num <= 10) {
            $inferredBranch = 'Cabang A';
        } elseif ($num >= 11 && $num <= 15) {
            $inferredBranch = 'Cabang B';
        }

        return $inferredBranch
            ? $this->resolveLocation(null, $inferredBranch, null, null, 'STORE')
            : null;
    }

    /**
     * Check whether a cashier user is authorized for the given store location.
     */
    public function isUserAllowedInLocation(User $user, Location $location): bool
    {
        if ($user->isOwnerOrSuperadmin() || $user->isSupervisorOrManager()) {
            return true;
        }

        // Check explicit pivot table if assigned
        $pivotExists = $user->belongsToMany(Location::class, 'user_location_access')->exists();
        if ($pivotExists) {
            return $user->belongsToMany(Location::class, 'user_location_access')
                ->where('locations.id', $location->id)
                ->exists();
        }

        if (empty($user->branch)) {
            return true;
        }

        $branches = array_map('trim', explode(',', $user->branch));
        foreach ($branches as $b) {
            if ($b === '') continue;
            $userLoc = $this->resolveLocation(null, $b, null, null, 'STORE');
            if ($userLoc && $userLoc->id === $location->id) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add stock into a specific location and create a StockBatch for FIFO/FEFO tracking.
     */
    public function addStock(int $productId, int $locationId, int $qty, array $meta = []): ProductStock
    {
        $now = now();
        $productStock = ProductStock::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (!$productStock) {
            $productStock = ProductStock::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'stock' => 0,
                'is_active' => true,
                'first_received_at' => $qty > 0 ? $now : null,
                'last_received_at' => $qty > 0 ? $now : null,
                'version' => 1,
            ]);
        }

        $stockBefore = (int) $productStock->stock;
        $stockAfter = $stockBefore + $qty;

        $batch = null;
        if ($qty > 0 && empty($meta['skip_batch'])) {
            $product = Product::find($productId);
            $batchNumber = trim((string) ($meta['batch_number'] ?? ''));
            if ($batchNumber === '' || StockBatch::where('batch_number', $batchNumber)->exists()) {
                $prefix = $product?->sku ?: ('PRD-' . $productId);
                $batchNumber = 'BTH-' . $prefix . '-' . date('YmdHis') . '-' . Str::upper(Str::random(4));
            }

            $batch = StockBatch::create([
                'batch_number' => $batchNumber,
                'product_id' => $productId,
                'location_id' => $locationId,
                'supplier_id' => $meta['supplier_id'] ?? $product?->supplier_id,
                'purchase_id' => $meta['purchase_id'] ?? null,
                'goods_receipt_id' => $meta['goods_receipt_id'] ?? null,
                'goods_receipt_item_id' => $meta['goods_receipt_item_id'] ?? null,
                'ownership_type' => $meta['ownership_type'] ?? ($product?->is_consignment ? 'CONSIGNMENT' : 'OUTRIGHT'),
                'initial_qty' => $qty,
                'remaining_qty' => $qty,
                'unit_cost' => (float) ($meta['unit_cost'] ?? $product?->cost_price ?? 0),
                'received_at' => !empty($meta['received_at']) ? Carbon::parse($meta['received_at']) : $now,
                'expired_date' => !empty($meta['expired_date']) ? Carbon::parse($meta['expired_date'])->toDateString() : null,
                'notes' => $meta['notes'] ?? null,
            ]);
        }

        $productStock->stock = $stockAfter;
        if (!$productStock->first_received_at && $qty > 0) {
            $productStock->first_received_at = $now;
        }
        if ($qty > 0) {
            $productStock->last_received_at = $now;
        }
        $productStock->is_active = true;
        $productStock->version = (int) $productStock->version + 1;
        $productStock->save();

        if ($qty !== 0) {
            StockMovement::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'stock_batch_id' => $batch?->id,
                'movement_type' => $meta['movement_type'] ?? 'TRANSFER_IN',
                'qty_change' => $qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reference_type' => $meta['reference_type'] ?? null,
                'reference_id' => $meta['reference_id'] ?? null,
                'reference_number' => $meta['reference_number'] ?? null,
                'user_id' => $meta['user_id'] ?? null,
                'notes' => $meta['notes'] ?? null,
            ]);
        }

        $this->syncGlobalProductStock($productId);

        return $productStock;
    }

    /**
     * Deduct stock from a specific location using FEFO (expired products) or FIFO (non-expired products).
     */
    public function deductStock(int $productId, int $locationId, int $qty, array $meta = []): array
    {
        $allowNegative = (bool) ($meta['allow_negative'] ?? false);
        $productStock = ProductStock::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (!$productStock) {
            return [
                'success' => false,
                'error_code' => 'PRODUCT_NOT_IN_STORE_STOCK',
                'message' => 'Produk belum terdaftar pada Stock Toko lokasi ini.',
            ];
        }

        $stockBefore = (int) $productStock->stock;
        if (!$allowNegative && $stockBefore < $qty) {
            return [
                'success' => false,
                'error_code' => 'STOCK_INSUFFICIENT',
                'stock_available' => $stockBefore,
                'message' => "Stok lokasi tidak mencukupi (tersedia: {$stockBefore}, dibutuhkan: {$qty}).",
            ];
        }

        $shortfall = max(0, $qty - $stockBefore);
        $actualDeduct = min($qty, max(0, $stockBefore));

        // Deduct from location batches: FEFO (earliest expired_date first) -> FIFO (earliest received_at first)
        $remainingToTake = $actualDeduct;
        $allocations = [];

        $batches = StockBatch::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->available()
            ->fefo()
            ->lockForUpdate()
            ->get();

        foreach ($batches as $batch) {
            if ($remainingToTake <= 0) {
                break;
            }
            $batchRem = (int) floor((float) $batch->remaining_qty);
            if ($batchRem <= 0) {
                continue;
            }
            $take = min($remainingToTake, $batchRem);
            $batch->remaining_qty = (float) $batch->remaining_qty - $take;
            $batch->save();

            $remainingToTake -= $take;
            $allocations[] = [
                'stock_batch_id' => $batch->id,
                'batch_number' => $batch->batch_number,
                'qty' => $take,
                'expired_date' => $batch->expired_date ? $batch->expired_date->toDateString() : null,
                'unit_cost' => (float) $batch->unit_cost,
            ];
        }

        $stockAfter = max(0, $stockBefore - $qty);
        $productStock->stock = $stockAfter;
        $productStock->version = (int) $productStock->version + 1;
        if (($meta['movement_type'] ?? '') === 'SALE_OUT') {
            $productStock->last_sold_at = now();
        }
        $productStock->save();

        $notes = $meta['notes'] ?? null;
        if ($shortfall > 0) {
            $notes = trim(($notes ? $notes . ' | ' : '') . "KONFLIK_STOK_OFFLINE: selisih {$shortfall} unit (stok sistem {$stockBefore}, terjual {$qty})");
        }

        StockMovement::create([
            'product_id' => $productId,
            'location_id' => $locationId,
            'stock_batch_id' => $allocations[0]['stock_batch_id'] ?? null,
            'movement_type' => $meta['movement_type'] ?? 'SALE_OUT',
            'qty_change' => -$actualDeduct,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
            'reference_number' => $meta['reference_number'] ?? null,
            'user_id' => $meta['user_id'] ?? null,
            'notes' => $notes,
        ]);

        $this->syncGlobalProductStock($productId);

        return [
            'success' => true,
            'primary_batch_id' => $allocations[0]['stock_batch_id'] ?? null,
            'allocations' => $allocations,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'shortfall' => $shortfall,
        ];
    }

    /**
     * Restore voided quantity back into the exact StockBatch records recorded during sale.
     */
    public function restoreVoidStock(int $productId, int $locationId, int $qty, array $allocations = [], ?int $primaryBatchId = null, array $meta = []): ProductStock
    {
        $totalRestore = !empty($allocations)
            ? (int) collect($allocations)->sum(fn ($a) => (int) ($a['qty'] ?? 0))
            : $qty;
        if ($totalRestore <= 0) {
            $totalRestore = $qty;
        }

        if (empty($allocations) && $primaryBatchId) {
            $allocations = [['stock_batch_id' => $primaryBatchId, 'qty' => $totalRestore]];
        }

        if (empty($allocations)) {
            return $this->addStock($productId, $locationId, $totalRestore, $meta);
        }

        $productStock = ProductStock::where('product_id', $productId)
            ->where('location_id', $locationId)
            ->lockForUpdate()
            ->first();

        if (!$productStock) {
            $productStock = ProductStock::create([
                'product_id' => $productId,
                'location_id' => $locationId,
                'stock' => 0,
                'is_active' => true,
                'version' => 1,
            ]);
        }

        $stockBefore = (int) $productStock->stock;
        $restoredTotal = 0;

        foreach ($allocations as $alloc) {
            $bId = $alloc['stock_batch_id'] ?? null;
            $bQty = (int) ($alloc['qty'] ?? 0);
            if ($bQty <= 0) {
                continue;
            }
            $batch = $bId ? StockBatch::where('id', $bId)->where('location_id', $locationId)->lockForUpdate()->first() : null;
            if ($batch) {
                $batch->remaining_qty = (float) $batch->remaining_qty + $bQty;
                $batch->save();
                $restoredTotal += $bQty;
            }
        }

        if ($restoredTotal < $totalRestore) {
            $remainder = $totalRestore - $restoredTotal;
            $fallbackBatch = StockBatch::where('product_id', $productId)
                ->where('location_id', $locationId)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();
            if ($fallbackBatch) {
                $fallbackBatch->remaining_qty = (float) $fallbackBatch->remaining_qty + $remainder;
                $fallbackBatch->save();
            }
        }

        $stockAfter = $stockBefore + $totalRestore;
        $productStock->stock = $stockAfter;
        $productStock->version = (int) $productStock->version + 1;
        $productStock->save();

        StockMovement::create([
            'product_id' => $productId,
            'location_id' => $locationId,
            'stock_batch_id' => $allocations[0]['stock_batch_id'] ?? $primaryBatchId,
            'movement_type' => $meta['movement_type'] ?? 'VOID_IN',
            'qty_change' => $totalRestore,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
            'reference_number' => $meta['reference_number'] ?? null,
            'user_id' => $meta['user_id'] ?? null,
            'notes' => $meta['notes'] ?? null,
        ]);

        $this->syncGlobalProductStock($productId);

        return $productStock;
    }

    /**
     * Synchronize global aggregate products.stock & status from all product_stocks rows.
     */
    public function syncGlobalProductStock(int $productId): int
    {
        $product = Product::find($productId);
        if (!$product) {
            return 0;
        }

        $hasAnyLocStock = ProductStock::where('product_id', $productId)->exists();
        if (!$hasAnyLocStock) {
            return (int) $product->stock;
        }

        $totalStock = (int) ProductStock::where('product_id', $productId)->sum('stock');
        $product->stock = $totalStock;

        // Refresh earliest available batch expired_date if batches exist
        $earliestBatchExp = StockBatch::where('product_id', $productId)
            ->available()
            ->whereNotNull('expired_date')
            ->orderBy('expired_date', 'asc')
            ->value('expired_date');
        if ($earliestBatchExp) {
            $product->expired_date = $earliestBatchExp;
        }

        if ($totalStock <= 0) {
            $product->status = 'HABIS';
        } elseif ($totalStock <= (int) $product->min_stock) {
            $product->status = 'STOK_RENDAH';
        } else {
            $product->status = 'TERSEDIA';
        }
        $product->save();

        return $totalStock;
    }

    /**
     * Build location stock map keyed by location code (e.g. GDG-001, CAB-001, CAB-002, CAB-003).
     */
    public function getProductBranchStockMap(Product $product): array
    {
        $map = [];
        $stocks = $product->relationLoaded('productStocks')
            ? $product->productStocks
            : $product->productStocks()->with('location')->get();

        foreach ($stocks as $ps) {
            if ($ps->location) {
                $map[$ps->location->code] = (int) $ps->stock;
            }
        }

        return $map;
    }

    /**
     * Resolve an explicit API report filter (branch code/name/alias or location_id).
     *
     * @return array{requested: bool, location: ?Location}
     */
    public function resolveReportFilterLocation(?string $branchParam = null, ?int $locationIdParam = null): array
    {
        $branchTrim = trim((string) ($branchParam ?? ''));
        $isAll = ($branchTrim === '' || strtolower($branchTrim) === 'all' || strtolower($branchTrim) === 'semua');

        if (!$locationIdParam && $isAll) {
            return ['requested' => false, 'location' => null];
        }

        $allLocations = Location::all();
        if ($locationIdParam) {
            return [
                'requested' => true,
                'location' => $allLocations->firstWhere('id', $locationIdParam),
            ];
        }

        return [
            'requested' => true,
            'location' => $this->matchLocationDeterministically($branchTrim, $allLocations),
        ];
    }

    /**
     * Deterministically classify a Transaction into STORE, WAREHOUSE, or UNMAPPED.
     * Priority: transactions.location_id -> shift.location_id -> transaction.branch / shift.branch -> strict POS-01..15 pattern.
     * Never guesses unknown terminals/branches; marks insufficient history as UNMAPPED.
     */
    public function classifyTransactionLocation(
        Transaction $tx,
        Collection $locationsById,
        Collection $allLocations,
        Collection $shiftsById
    ): array {
        // 1. Official foreign key transactions.location_id
        if ($tx->location_id && $locationsById->has($tx->location_id)) {
            $loc = $locationsById->get($tx->location_id);
            return [
                'classification' => $loc->type === 'WAREHOUSE' ? 'WAREHOUSE' : 'STORE',
                'location' => $loc,
                'location_id' => $loc->id,
                'source' => 'location_id',
            ];
        }

        // 2a. Historical fallback: linked shift location_id
        $shift = $tx->shift_id ? $shiftsById->get($tx->shift_id) : null;
        if ($shift && $shift->location_id && $locationsById->has($shift->location_id)) {
            $loc = $locationsById->get($shift->location_id);
            return [
                'classification' => $loc->type === 'WAREHOUSE' ? 'WAREHOUSE' : 'STORE',
                'location' => $loc,
                'location_id' => $loc->id,
                'source' => 'shift.location_id',
            ];
        }

        // 2b. Historical fallback: explicit transaction.branch or shift.branch
        foreach (['transaction.branch' => $tx->branch, 'shift.branch' => $shift?->branch] as $src => $rawBranch) {
            if ($rawBranch !== null && trim((string) $rawBranch) !== '') {
                $matched = $this->matchLocationDeterministically((string) $rawBranch, $allLocations);
                if ($matched) {
                    return [
                        'classification' => $matched->type === 'WAREHOUSE' ? 'WAREHOUSE' : 'STORE',
                        'location' => $matched,
                        'location_id' => $matched->id,
                        'source' => $src,
                    ];
                }
            }
        }

        // 2c. Historical fallback: strict numbered POS-01..15 terminal pattern ONLY (no index-based guessing)
        $term = strtoupper(trim((string) ($tx->terminal_code ?? '')));
        if ($term !== '' && preg_match('/^POS-0?(\d+)$/', $term, $m)) {
            $num = (int) $m[1];
            $inferred = null;
            if ($num >= 1 && $num <= 5) {
                $inferred = 'Toko Pusat';
            } elseif ($num >= 6 && $num <= 10) {
                $inferred = 'Cabang A';
            } elseif ($num >= 11 && $num <= 15) {
                $inferred = 'Cabang B';
            }
            if ($inferred) {
                $storeOnly = $allLocations->where('type', '!=', 'WAREHOUSE');
                $matched = $this->matchLocationDeterministically($inferred, $storeOnly);
                if ($matched) {
                    return [
                        'classification' => 'STORE',
                        'location' => $matched,
                        'location_id' => $matched->id,
                        'source' => 'terminal_code',
                    ];
                }
            }
        }

        // 3. Insufficient evidence -> UNMAPPED (do NOT guess or mutate history)
        return [
            'classification' => 'UNMAPPED',
            'location' => null,
            'location_id' => null,
            'source' => 'unmapped',
        ];
    }

    /**
     * Deduplicate transactions and partition them by official store location, warehouse, and unmapped history.
     */
    public function partitionTransactionsByStoreLocation(
        Collection $transactions,
        Collection $storeLocations,
        ?Location $filterLoc = null,
        bool $filterRequested = false
    ): array {
        $uniqueTxs = $transactions->unique('id')->values();
        $allLocations = Location::all();
        $locationsById = $allLocations->keyBy('id');

        $shiftIds = $uniqueTxs->whereNull('location_id')->pluck('shift_id')->filter()->unique()->values()->all();
        $shiftsById = empty($shiftIds)
            ? collect()
            : CashierShift::whereIn('id', $shiftIds)->get()->keyBy('id');

        $byLocationId = [];
        foreach ($storeLocations as $loc) {
            $byLocationId[$loc->id] = collect();
        }

        $allStoreTxs = collect();
        $warehouseTxs = collect();
        $unmappedTxs = collect();
        $fallbackMappedCount = 0;

        foreach ($uniqueTxs as $tx) {
            $info = $this->classifyTransactionLocation($tx, $locationsById, $allLocations, $shiftsById);
            if ($info['classification'] === 'WAREHOUSE') {
                $warehouseTxs->push($tx);
            } elseif ($info['classification'] === 'STORE' && $info['location_id']) {
                if ($info['source'] !== 'location_id') {
                    $fallbackMappedCount++;
                }
                if (!isset($byLocationId[$info['location_id']])) {
                    $byLocationId[$info['location_id']] = collect();
                }
                $byLocationId[$info['location_id']]->push($tx);
                $allStoreTxs->push($tx);
            } else {
                $unmappedTxs->push($tx);
            }
        }

        if ($filterRequested) {
            if ($filterLoc && $filterLoc->type !== 'WAREHOUSE') {
                $filteredStoreTxs = $byLocationId[$filterLoc->id] ?? collect();
            } else {
                $filteredStoreTxs = collect();
            }
        } else {
            $filteredStoreTxs = $allStoreTxs;
        }

        return [
            'store_transactions' => $filteredStoreTxs->values(),
            'all_store_transactions' => $allStoreTxs->values(),
            'by_location_id' => $byLocationId,
            'warehouse_transactions' => $warehouseTxs->values(),
            'unmapped_transactions' => $unmappedTxs->values(),
            'fallback_mapped_count' => $fallbackMappedCount,
        ];
    }

    /**
     * Compute total COGS (HPP) for a collection of eager-loaded transactions without N+1 queries.
     */
    public function calculateTransactionsCogs(Collection $transactions): float
    {
        $neededSkus = [];
        foreach ($transactions as $tx) {
            foreach ($tx->items as $item) {
                if (empty($item->cost_price) && !empty($item->sku)) {
                    $neededSkus[$item->sku] = true;
                }
            }
        }
        $skuCosts = empty($neededSkus)
            ? []
            : Product::whereIn('sku', array_keys($neededSkus))->pluck('cost_price', 'sku')->all();

        $totalCogs = 0.0;
        foreach ($transactions as $tx) {
            foreach ($tx->items as $item) {
                $cost = $item->cost_price ?? null;
                if (!$cost) {
                    $cost = $skuCosts[$item->sku] ?? 0.0;
                }
                $totalCogs += ((float) $cost * (int) ($item->quantity ?? $item->qty ?? 1));
            }
        }

        return round($totalCogs, 2);
    }

    /**
     * Format concise audit summary for excluded/unmapped transaction groups.
     */
    public function summarizeExcludedTransactions(Collection $transactions): array
    {
        return [
            'count' => $transactions->count(),
            'total_amount' => round((float) $transactions->sum('grand_total'), 2),
            'invoice_numbers' => $transactions->take(20)->pluck('invoice_number')->values()->all(),
        ];
    }

    private function matchLocationDeterministically(string $raw, Collection $locations): ?Location
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }
        $lower = strtolower($trimmed);

        // 1. Exact code or exact case-insensitive name match
        foreach ($locations as $loc) {
            if (strtolower((string) $loc->code) === $lower || strtolower((string) $loc->name) === $lower) {
                return $loc;
            }
        }

        // 2. Numeric ID match if string is numeric
        if (ctype_digit($trimmed)) {
            $byId = $locations->firstWhere('id', (int) $trimmed);
            if ($byId) {
                return $byId;
            }
        }

        // 3. Official alias match (warehouse checked first inside branchAliasCandidates; never alias store strings into WAREHOUSE)
        $aliases = $this->branchAliasCandidates($lower);
        $isWarehouseLookup = in_array('gdg-001', $aliases, true);

        foreach ($locations as $loc) {
            if (!$isWarehouseLookup && $loc->type === 'WAREHOUSE') {
                continue;
            }
            $codeL = strtolower((string) $loc->code);
            $nameL = strtolower((string) $loc->name);
            foreach ($aliases as $alias) {
                if ($codeL === $alias || $nameL === $alias) {
                    return $loc;
                }
            }
        }
        foreach ($locations as $loc) {
            if (!$isWarehouseLookup && $loc->type === 'WAREHOUSE') {
                continue;
            }
            $nameL = strtolower((string) $loc->name);
            foreach ($aliases as $alias) {
                if (str_contains($nameL, $alias)) {
                    return $loc;
                }
            }
        }

        return null;
    }

    private function branchAliasCandidates(string $lower): array
    {
        if (str_contains($lower, 'gudang') || $lower === 'dc' || str_contains($lower, 'logistik') || $lower === 'gdg-001' || $lower === 'loc-dc') {
            return ['gdg-001', 'loc-dc', 'gudang', 'dc'];
        }
        if ($lower === 'pusat' || str_contains($lower, 'cabang pusat') || str_contains($lower, 'toko pusat') || str_contains($lower, 'minimarket pusat') || $lower === 'cab-001' || $lower === 'loc-01') {
            return ['cab-001', 'loc-01', 'cabang pusat', 'toko pusat', 'pusat'];
        }
        if (str_contains($lower, 'cabang a') || str_contains($lower, 'cabang 1') || str_contains($lower, 'sektor a') || $lower === 'cab-002' || $lower === 'loc-02') {
            return ['cab-002', 'loc-02', 'cabang a', 'cabang 1'];
        }
        if (str_contains($lower, 'cabang b') || str_contains($lower, 'cabang 2') || str_contains($lower, 'sektor b') || $lower === 'cab-003' || $lower === 'loc-03') {
            return ['cab-003', 'loc-03', 'cabang b', 'cabang 2'];
        }

        return [$lower];
    }
}
