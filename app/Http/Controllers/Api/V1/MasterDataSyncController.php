<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MasterDataSyncController extends Controller
{
    /**
     * GET /api/v1/sync/master/categories
     * Delta & paginated sync provider for Product Categories (Parent entity).
     */
    public function categories(Request $request): JsonResponse
    {
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

        return $this->formatPaginatedResponse($serverTime, $data, $paginator);
    }

    /**
     * GET /api/v1/sync/master/locations
     * Delta & paginated sync provider for Store/Warehouse Locations (Parent entity).
     */
    public function locations(Request $request): JsonResponse
    {
        $serverTime = now()->utc()->toIso8601String();
        $perPage = $this->resolvePerPage($request);

        $query = Location::query();
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

        return $this->formatPaginatedResponse($serverTime, $data, $paginator);
    }

    /**
     * GET /api/v1/sync/master/suppliers
     * Delta & paginated sync provider for Suppliers & UMKM Partners (Parent entity).
     */
    public function suppliers(Request $request): JsonResponse
    {
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

        return $this->formatPaginatedResponse($serverTime, $data, $paginator);
    }

    /**
     * GET /api/v1/sync/master/products
     * Delta & paginated sync provider for Products (includes withTrashed() soft-deleted records, UUID relations, and per-location store stocks).
     */
    public function products(Request $request): JsonResponse
    {
        $serverTime = now()->utc()->toIso8601String();
        $perPage = $this->resolvePerPage($request);

        $query = Product::withTrashed()->with(['productCategory', 'supplier', 'productStocks']);
        $updatedSince = trim((string) $request->query('updated_since', ''));
        if ($updatedSince !== '') {
            $since = Carbon::parse($updatedSince)->setTimezone(config('app.timezone'));
            $query->where(function (Builder $q) use ($since) {
                $q->where('updated_at', '>=', $since)
                    ->orWhere('deleted_at', '>=', $since)
                    ->orWhereHas('productStocks', fn (Builder $sq) => $sq->where('updated_at', '>=', $since));
            });
        }
        $query->orderBy('updated_at', 'asc')->orderBy('uuid', 'asc');

        $paginator = $query->paginate($perPage);

        // Pre-map category name -> uuid and location id -> uuid without N+1 queries
        $categoryUuidByName = ProductCategory::query()->pluck('uuid', 'name')->all();
        $locationUuidById = Location::query()->pluck('uuid', 'id')->all();

        $data = collect($paginator->items())->map(function (Product $p) use ($categoryUuidByName, $locationUuidById) {
            $categoryUuid = $p->productCategory?->uuid
                ?? ($p->category ? ($categoryUuidByName[$p->category] ?? null) : null);
            $categoryName = $p->productCategory?->name ?? ($p->category ?: 'Umum');

            $locationStocks = $p->productStocks->map(function ($ps) use ($locationUuidById, $p) {
                $locUuid = $locationUuidById[$ps->location_id] ?? null;
                if (!$locUuid) {
                    return null;
                }

                return [
                    'location_uuid' => $locUuid,
                    'stock' => (int) $ps->stock,
                    'min_stock' => (int) ($ps->min_stock ?? $p->min_stock ?? 5),
                    'is_active' => (bool) $ps->is_active,
                ];
            })->filter()->values()->all();

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
                'stock' => (int) $p->stock,
                'min_stock' => (int) ($p->min_stock ?? 5),
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
                'qty_produk' => (float) ($p->qty_produk ?? $p->stock),
                'nama_produk' => $p->nama_produk ?? $p->name,
                'satuan_unit' => $p->satuan_unit ?? $p->unit ?? 'Pcs',
                'harga_beli' => (float) ($p->harga_beli ?? $p->cost_price),
                'harga_jual_umum' => (float) ($p->harga_jual_umum ?? $p->price),
                'harga_jual_member' => (float) ($p->harga_jual_member ?? $p->member_price ?? $p->price),
                'produk_disc' => (float) ($p->produk_disc ?? $p->clearance_discount_percent ?? 0),
                'minimum_stock' => (int) ($p->minimum_stock ?? $p->min_stock ?? 5),
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

        return $this->formatPaginatedResponse($serverTime, $data, $paginator);
    }

    /**
     * GET /api/v1/sync/master/employees
     * Delta & paginated sync provider for Employees/Users (includes raw bcrypt password hash for offline login).
     */
    public function employees(Request $request): JsonResponse
    {
        $serverTime = now()->utc()->toIso8601String();
        $perPage = $this->resolvePerPage($request);

        $query = User::query()->with('location');
        $this->applyUpdatedSince($query, $request, true);
        $query->orderBy('updated_at', 'asc')->orderBy('uuid', 'asc');

        $paginator = $query->paginate($perPage);

        $data = collect($paginator->items())->map(fn (User $u) => [
            'uuid' => $u->uuid,
            'location_uuid' => $u->location?->uuid,
            'name' => $u->name,
            'username' => $u->username,
            'email' => $u->email,
            'nik' => $u->nik,
            'role' => $u->role,
            'pin' => $u->getRawOriginal('pin') ?? $u->pin,
            'branch' => $u->branch ?? 'Pusat',
            'assigned_branches' => $u->assigned_branches,
            'is_active' => (bool) $u->is_active,
            'password' => (string) $u->getRawOriginal('password'),
            'deleted_at' => $u->deleted_at?->toIso8601String(),
            'created_at' => $u->created_at?->toIso8601String(),
            'updated_at' => $u->updated_at?->toIso8601String(),
        ])->values()->all();

        return $this->formatPaginatedResponse($serverTime, $data, $paginator);
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

    protected function formatPaginatedResponse(string $serverTime, array $data, $paginator): JsonResponse
    {
        return response()->json([
            'success' => true,
            'server_time' => $serverTime,
            'data' => $data,
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ], 200);
    }
}
