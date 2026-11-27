<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\PosTerminal;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductStock;
use App\Models\Supplier;
use App\Models\User;
use App\Services\LocationStockService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterDataSyncController extends Controller
{
    /**
     * GET /api/v1/sync/master/categories
     * Delta & paginated sync provider for Product Categories (Global parent entity).
     */
    public function categories(Request $request): JsonResponse
    {
        $terminal = $this->touchTerminalPull($request);
        $serverTime = now()->utc()->toIso8601String();
        $perPage = $this->resolvePerPage($request);

        $query = ProductCategory::query();
        $this->applyUpdatedSince($query, $request, false);
        $query->orderBy('updated_at', 'asc')->orderBy('uuid', 'asc');

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(fn (ProductCategory $cat) => [
            'uuid' => $cat->uuid,
            'name' => $cat->name,
            'created_at' => $cat->created_at?->toIso8601String(),
            'updated_at' => $cat->updated_at?->toIso8601String(),
        ])->values()->all();

        return $this->formatPaginatedResponse($serverTime, $data, $paginator, $terminal);
    }

    /**
     * GET /api/v1/sync/master/locations
     * Delta & paginated sync provider scoped strictly to `$terminal->branch_id`.
     */
    public function locations(Request $request): JsonResponse
    {
        $terminal = $this->touchTerminalPull($request);
        $serverTime = now()->utc()->toIso8601String();
        $perPage = $this->resolvePerPage($request);

        $query = Location::query()->where('id', $terminal->branch_id);
        $this->applyUpdatedSince($query, $request, false);
        $query->orderBy('updated_at', 'asc')->orderBy('uuid', 'asc');

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(fn (Location $loc) => [
            'uuid' => $loc->uuid,
            'code' => $loc->code,
            'name' => $loc->name,
            'type' => $loc->type,
            'address' => $loc->address,
            'manager' => $loc->manager,
            'manager_email' => $loc->manager_email,
            'phone' => $loc->phone,
            'status' => $loc->status ?? 'AKTIF',
            'is_active' => (bool) $loc->is_active,
            'created_at' => $loc->created_at?->toIso8601String(),
            'updated_at' => $loc->updated_at?->toIso8601String(),
        ])->values()->all();

        return $this->formatPaginatedResponse($serverTime, $data, $paginator, $terminal);
    }

    /**
     * GET /api/v1/sync/master/suppliers
     * Delta & paginated sync provider for Suppliers & UMKM Partners (Global parent entity).
     */
    public function suppliers(Request $request): JsonResponse
    {
        $terminal = $this->touchTerminalPull($request);
        $serverTime = now()->utc()->toIso8601String();
        $perPage = $this->resolvePerPage($request);

        $query = Supplier::query();
        $this->applyUpdatedSince($query, $request, false);
        $query->orderBy('updated_at', 'asc')->orderBy('uuid', 'asc');

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(fn (Supplier $sup) => [
            'uuid' => $sup->uuid,
            'code' => $sup->code,
            'name' => $sup->name,
            'company_name' => $sup->company_name,
            'owner_name' => $sup->owner_name,
            'pic_name' => $sup->pic_name,
            'phone' => $sup->phone,
            'email' => $sup->email,
            'address' => $sup->address,
            'city' => $sup->city,
            'category' => $sup->category,
            'business_categories' => $sup->business_categories,
            'commission_percent' => (float) $sup->commission_percent,
            'settlement_period' => $sup->settlement_period,
            'bank_name' => $sup->bank_name,
            'bank_account' => $sup->bank_account,
            'status' => $sup->status ?? 'AKTIF',
            'supplier_type' => $sup->supplier_type ?? Supplier::TYPE_REGULAR,
            'notes' => $sup->notes,
            'created_at' => $sup->created_at?->toIso8601String(),
            'updated_at' => $sup->updated_at?->toIso8601String(),
        ])->values()->all();

        return $this->formatPaginatedResponse($serverTime, $data, $paginator, $terminal);
    }

    /**
     * GET /api/v1/sync/master/products
     * Sends global master product catalog, while strictly filtering stock (`stock`, `qty_produk`, `location_stocks`)
     * EXCLUSIVELY to `$terminal->branch_id` so no branch ever receives another branch's stock.
     */
    public function products(Request $request): JsonResponse
    {
        $terminal = $this->touchTerminalPull($request);
        $branchId = (int) $terminal->branch_id;
        $branchUuid = (string) $terminal->branch->uuid;

        $serverTime = now()->utc()->toIso8601String();
        $perPage = $this->resolvePerPage($request);

        $query = Product::withTrashed()->with([
            'productCategory',
            'supplier',
            'productStocks' => fn ($sq) => $sq->where('location_id', $branchId),
        ]);

        $updatedSince = trim((string) $request->query('updated_since', ''));
        if ($updatedSince !== '') {
            $since = Carbon::parse($updatedSince)->setTimezone(config('app.timezone'));
            $query->where(function (Builder $q) use ($since, $branchId) {
                $q->where('updated_at', '>=', $since)
                    ->orWhere('deleted_at', '>=', $since)
                    ->orWhereHas('productStocks', function (Builder $sq) use ($since, $branchId) {
                        $sq->where('location_id', $branchId)
                            ->where('updated_at', '>=', $since);
                    });
            });
        }
        $query->orderBy('updated_at', 'asc')->orderBy('uuid', 'asc');

        $paginator = $query->paginate($perPage);
        $items = collect($paginator->items());

        // Identify which products in this page have per-location `product_stocks` initialized anywhere (1 bulk query, 0 N+1)
        $pageProductIds = $items->pluck('id')->all();
        $productIdsWithAnyLocStock = !empty($pageProductIds)
            ? ProductStock::query()
                ->whereIn('product_id', $pageProductIds)
                ->distinct()
                ->pluck('product_id')
                ->flip()
                ->all()
            : [];

        $categoryUuidByName = ProductCategory::query()->pluck('uuid', 'name')->all();

        $data = $items->map(function (Product $p) use ($categoryUuidByName, $productIdsWithAnyLocStock, $branchUuid) {
            $categoryUuid = $p->productCategory?->uuid
                ?? ($p->category ? ($categoryUuidByName[$p->category] ?? null) : null);
            $categoryName = $p->productCategory?->name ?? ($p->category ?: 'Umum');

            /** @var ProductStock|null $branchStockRow */
            $branchStockRow = $p->productStocks->first();

            if ($branchStockRow !== null) {
                $branchStockQty = (int) $branchStockRow->stock;
                $branchMinStock = (int) ($branchStockRow->min_stock ?? $p->min_stock ?? 5);
                $branchIsActive = (bool) $branchStockRow->is_active;
            } elseif (isset($productIdsWithAnyLocStock[$p->id])) {
                // Other branches have stock rows, but this branch has 0 — NEVER leak another branch's or warehouse's stock!
                $branchStockQty = 0;
                $branchMinStock = (int) ($p->min_stock ?? 5);
                $branchIsActive = true;
            } else {
                // Standalone product with no per-location stocks anywhere yet
                $branchStockQty = (int) $p->stock;
                $branchMinStock = (int) ($p->min_stock ?? 5);
                $branchIsActive = true;
            }

            $locationStocks = [
                [
                    'location_uuid' => $branchUuid,
                    'stock' => $branchStockQty,
                    'min_stock' => $branchMinStock,
                    'is_active' => $branchIsActive,
                ],
            ];

            return [
                'uuid' => $p->uuid,
                'category_uuid' => $categoryUuid,
                'supplier_uuid' => $p->supplier?->uuid,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'name' => $p->name,
                'brand' => $p->brand,
                'category' => $categoryName,
                'unit' => $p->unit ?? 'Pcs',
                'cost_price' => (float) $p->cost_price,
                'last_cost_price' => (float) ($p->last_cost_price ?? $p->cost_price),
                'price' => (float) $p->price,
                'member_price' => $p->member_price !== null ? (float) $p->member_price : (float) $p->price,
                'stock' => $branchStockQty,
                'min_stock' => $branchMinStock,
                'is_taxable' => (bool) $p->is_taxable,
                'tax_rate' => (float) ($p->tax_rate ?? 11),
                'expired_date' => $p->expired_date?->format('Y-m-d'),
                'manufacturing_date' => $p->manufacturing_date?->format('Y-m-d'),
                'clearance_discount_percent' => (float) ($p->clearance_discount_percent ?? 0),
                'status' => $p->status ?? 'TERSEDIA',
                'is_consignment' => (bool) $p->is_consignment,
                'commission_percent' => (float) ($p->commission_percent ?? 0),
                'rack_location' => $p->rack_location,
                'id_produk' => $p->id_produk,
                'kode_barcode' => $p->kode_barcode ?? $p->barcode,
                'kode_produk' => $p->kode_produk ?? $p->sku,
                'qty_produk' => (float) $branchStockQty,
                'nama_produk' => $p->nama_produk ?? $p->name,
                'satuan_unit' => $p->satuan_unit ?? $p->unit ?? 'Pcs',
                'harga_beli' => (float) ($p->harga_beli ?? $p->cost_price),
                'harga_jual_umum' => (float) ($p->harga_jual_umum ?? $p->price),
                'harga_jual_member' => (float) ($p->harga_jual_member ?? $p->member_price ?? $p->price),
                'produk_disc' => (float) ($p->produk_disc ?? $p->clearance_discount_percent ?? 0),
                'minimum_stock' => $branchMinStock,
                'ppn_out' => (float) ($p->ppn_out ?? $p->tax_rate ?? 11),
                'ppn_chk' => $p->ppn_chk ?? ($p->is_taxable ? 'Y' : 'N'),
                'harga_beli_terakhir' => (float) ($p->harga_beli_terakhir ?? $p->last_cost_price ?? $p->cost_price),
                'status_barang' => $p->status_barang ?? $p->status ?? 'TERSEDIA',
                'kode_suplier' => $p->kode_suplier ?? $p->ko_sup,
                'location_stocks' => $locationStocks,
                'deleted_at' => $p->deleted_at?->toIso8601String(),
                'created_at' => $p->created_at?->toIso8601String(),
                'updated_at' => $p->updated_at?->toIso8601String(),
            ];
        })->values()->all();

        return $this->formatPaginatedResponse($serverTime, $data, $paginator, $terminal);
    }

    /**
     * GET /api/v1/sync/master/employees
     * Scopes employees to `$terminal->branch_id` (plus Manager/Supervisor/Owner accounts for offline supervisor PIN auth).
     */
    public function employees(Request $request, LocationStockService $stockService): JsonResponse
    {
        $terminal = $this->touchTerminalPull($request);
        /** @var Location $branch */
        $branch = $terminal->branch;
        $serverTime = now()->utc()->toIso8601String();
        $perPage = $this->resolvePerPage($request);

        $baseQuery = User::query()->with('location');
        $this->applyUpdatedSince($baseQuery, $request, true);
        $baseQuery->orderBy('updated_at', 'asc')->orderBy('uuid', 'asc');

        // Filter candidates authorised for `$terminal->branch` (or global Manager/Supervisor/Owner)
        $authorizedIds = (clone $baseQuery)->get()->filter(function (User $u) use ($branch, $stockService) {
            if ($u->isOwnerOrSuperadmin() || $u->isSupervisorOrManager()) {
                return true;
            }
            if ($u->location_id !== null) {
                return (int) $u->location_id === (int) $branch->id;
            }

            return $stockService->isUserAllowedInLocation($u, $branch);
        })->pluck('id')->all();

        $paginator = User::query()
            ->with('location')
            ->whereIn('id', $authorizedIds ?: [0])
            ->orderBy('updated_at', 'asc')
            ->orderBy('uuid', 'asc')
            ->paginate($perPage);

        $data = collect($paginator->items())->map(fn (User $u) => [
            'uuid' => $u->uuid,
            'location_uuid' => $u->location?->uuid ?? ($u->location_id === $branch->id ? $branch->uuid : null),
            'name' => $u->name,
            'username' => $u->username,
            'email' => $u->email,
            'nik' => $u->nik,
            'role' => $u->role,
            'pin' => $u->getRawOriginal('pin') ?? $u->pin,
            'branch' => $u->branch ?? $branch->name,
            'assigned_branches' => $u->assigned_branches,
            'is_active' => (bool) $u->is_active,
            'password' => (string) $u->getRawOriginal('password'),
            'deleted_at' => $u->deleted_at?->toIso8601String(),
            'created_at' => $u->created_at?->toIso8601String(),
            'updated_at' => $u->updated_at?->toIso8601String(),
        ])->values()->all();

        return $this->formatPaginatedResponse($serverTime, $data, $paginator, $terminal);
    }

    /**
     * GET /api/v1/sync/master/goods-receipts
     * Delta & paginated sync provider for GoodsReceipt shipments addressed strictly to `$terminal->branch_id`.
     */
    public function goodsReceipts(Request $request): JsonResponse
    {
        $terminal = $this->touchTerminalPull($request);
        $branchId = (int) $terminal->branch_id;
        $serverTime = now()->utc()->toIso8601String();
        $perPage = $this->resolvePerPage($request);

        // A single local POS node may serve multiple store branches (cashier picks branch at login).
        // Allow the node to request receipts for additional STORE locations via `location_uuids`.
        $servedLocationIds = self::resolveServedStoreLocationIds($request->query('location_uuids'), $branchId);

        $query = \App\Models\GoodsReceipt::query()
            ->with(['items.product', 'fromLocation', 'toLocation', 'sender', 'receiver'])
            ->whereNull('purchase_id')
            ->whereIn('to_location_id', $servedLocationIds);

        $this->applyUpdatedSince($query, $request, false);
        $query->orderBy('updated_at', 'asc')->orderBy('uuid', 'asc');

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(function (\App\Models\GoodsReceipt $gr) use ($terminal) {
            return [
                'uuid' => $gr->uuid,
                'receipt_number' => $gr->receipt_number,
                'from_location_uuid' => $gr->fromLocation?->uuid,
                'to_location_uuid' => $gr->toLocation?->uuid ?? $terminal->branch->uuid,
                'sender_uuid' => $gr->sender?->uuid,
                'receiver_uuid' => $gr->receiver?->uuid,
                'origin_source' => $gr->origin_source ?? 'Gudang Pusat',
                'destination_store' => $gr->destination_store ?? $terminal->branch->name,
                'driver_name' => $gr->driver_name,
                'plate_number' => $gr->plate_number,
                'status' => $gr->status,
                'notes' => $gr->notes,
                'received_at' => $gr->received_at?->toIso8601String(),
                'verified_at' => $gr->verified_at?->toIso8601String(),
                'created_at' => $gr->created_at?->toIso8601String(),
                'updated_at' => $gr->updated_at?->toIso8601String(),
                'items' => $gr->items->map(fn ($it) => [
                    'uuid' => $it->uuid,
                    'product_uuid' => $it->product?->uuid,
                    'qty_sent' => (float) $it->qty_sent,
                    'qty_received' => (float) $it->qty_received,
                    'actual_unit_cost' => (float) $it->actual_unit_cost,
                    'condition' => $it->condition ?? 'BAIK',
                    'expired_date' => $it->expired_date?->format('Y-m-d'),
                    'batch_number' => $it->batch_number,
                    'notes' => $it->notes,
                ])->filter(fn ($it) => !empty($it['product_uuid']))->values()->all(),
            ];
        })->values()->all();

        return $this->formatPaginatedResponse($serverTime, $data, $paginator, $terminal);
    }

    /**
     * Retrieve authenticated PosTerminal and stamp `last_pull_at = now()`.
     */
    protected function touchTerminalPull(Request $request): PosTerminal
    {
        /** @var PosTerminal $terminal */
        $terminal = $request->attributes->get('pos_terminal');
        $terminal->forceFill([
            'last_pull_at' => now(),
        ])->saveQuietly();

        return $terminal;
    }

    /**
     * Resolve the set of STORE location ids a local node serves: the terminal's own branch
     * plus any additional STORE locations explicitly requested (warehouses are never allowed).
     *
     * @param  mixed  $rawUuids  comma-separated string or array of location UUIDs
     * @return array<int>
     */
    public static function resolveServedStoreLocationIds(mixed $rawUuids, int $terminalBranchId): array
    {
        $uuids = is_array($rawUuids) ? $rawUuids : explode(',', (string) $rawUuids);
        $uuids = array_values(array_filter(array_map(fn ($u) => trim((string) $u), $uuids)));

        $ids = [$terminalBranchId];
        if (!empty($uuids)) {
            $extra = Location::query()
                ->whereIn('uuid', array_slice($uuids, 0, 50))
                ->where('type', '!=', 'WAREHOUSE')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
            $ids = array_merge($ids, $extra);
        }

        return array_values(array_unique(array_filter($ids)));
    }

    protected function resolvePerPage(Request $request): int
    {
        $default = (int) config('pos_sync.pull_per_page', 200);
        $requested = (int) $request->query('per_page', $default);

        return max(1, min(1000, $requested > 0 ? $requested : $default));
    }

    protected function applyUpdatedSince(Builder $query, Request $request, bool $hasDeletedAt): void
    {
        $updatedSince = trim((string) $request->query('updated_since', ''));
        if ($updatedSince === '') {
            return;
        }

        $since = Carbon::parse($updatedSince)->setTimezone(config('app.timezone'));
        if ($hasDeletedAt) {
            $query->where(function (Builder $q) use ($since) {
                $q->where('updated_at', '>=', $since)
                    ->orWhere('deleted_at', '>=', $since);
            });
        } else {
            $query->where('updated_at', '>=', $since);
        }
    }

    protected function formatPaginatedResponse(string $serverTime, array $data, $paginator, PosTerminal $terminal): JsonResponse
    {
        return response()->json([
            'success' => true,
            'server_time' => $serverTime,
            'branch' => [
                'id' => $terminal->branch_id,
                'uuid' => $terminal->branch->uuid,
                'code' => $terminal->branch->code,
                'name' => $terminal->branch->name,
            ],
            'terminal_code' => $terminal->terminal_code,
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ], 200);
    }
}
