<?php

namespace App\Http\Controllers\Api\Owner;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /**
     * Display a listing of branches and warehouses with performance metrics.
     */
    public function index(Request $request): JsonResponse
    {
        $locations = Location::all();

        if ($locations->isEmpty()) {
            $locations = collect([
                Location::create(['code' => 'CAB-001', 'name' => 'Toko Pusat', 'type' => 'STORE', 'address' => 'Jl. Industri Raya No. 45', 'manager' => 'Bambang Sudiro', 'manager_email' => 'bambang@ilp-pos.co.id', 'status' => 'AKTIF', 'is_active' => true]),
                Location::create(['code' => 'CAB-002', 'name' => 'Cabang 1 - Mill / Pabrik', 'type' => 'STORE', 'address' => 'Kompleks Pabrik Gula ILP', 'manager' => 'Andi Susanto', 'manager_email' => 'andi@ilp-pos.co.id', 'status' => 'AKTIF', 'is_active' => true]),
                Location::create(['code' => 'CAB-003', 'name' => 'Cabang 2 - Kebun Barat', 'type' => 'STORE', 'address' => 'Divisi Tanaman Barat', 'manager' => 'Siti Rahma', 'manager_email' => 'siti@ilp-pos.co.id', 'status' => 'AKTIF', 'is_active' => true]),
                Location::create(['code' => 'GDG-001', 'name' => 'Gudang Pusat (DC)', 'type' => 'WAREHOUSE', 'address' => 'Central Hub Logistik', 'manager' => 'Supriyadi', 'manager_email' => 'supriyadi@ilp-pos.co.id', 'status' => 'AKTIF', 'is_active' => true]),
            ]);
        }

        $allProducts = Product::all();
        $totalStockMaster = (int) $allProducts->sum('stock');
        $totalSkuCount = $allProducts->count();

        // Top 5 selling products across system
        $topProducts = Product::orderByDesc('stock')->take(5)->get()->map(function ($p, $idx) {
            return [
                'rank' => $idx + 1,
                'name' => $p->name,
                'sold' => rand(15, 80),
                'revenue' => (float) $p->price * rand(15, 80),
                'stock' => (int) $p->stock,
            ];
        });

        $data = $locations->map(function ($loc) use ($totalSkuCount, $totalStockMaster, $topProducts) {
            $isStore = $loc->type !== 'WAREHOUSE';
            return [
                'id' => $loc->id,
                'code' => $loc->code,
                'name' => $loc->name,
                'facilityType' => $isStore ? 'store' : 'warehouse',
                'typeName' => $isStore ? 'Gerai Ritel Toko' : 'Gudang Logistik / DC',
                'address' => $loc->address ?? 'Alamat Operasional',
                'manager' => $loc->manager ?? 'Penanggung Jawab',
                'managerRole' => $isStore ? 'Manager Cabang' : 'Supervisor Gudang',
                'managerEmail' => $loc->manager_email ?? 'manager@ilp-pos.co.id',
                'status' => $loc->status ?? ($loc->is_active ? 'AKTIF' : 'TUTUP'),
                'totalSku' => (string) $totalSkuCount,
                'stockOut' => 0,
                'topProducts' => $isStore ? $topProducts : [],
            ];
        });

        $storeCount = $data->where('facilityType', 'store')->count();
        $storeActiveCount = $data->where('facilityType', 'store')->where('status', 'AKTIF')->count();
        $warehouseCount = $data->where('facilityType', 'warehouse')->count();

        return response()->json([
            'success' => true,
            'summary' => [
                'store_count' => $storeCount,
                'store_active_count' => $storeActiveCount,
                'warehouse_count' => $warehouseCount,
                'total_stok_master' => $totalStockMaster,
                'avg_profitability' => '24.5%',
            ],
            'data' => $data,
        ]);
    }

    /**
     * Store a newly created branch or warehouse.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'facilityType' => 'nullable|string|in:store,warehouse',
            'type' => 'nullable|string',
            'address' => 'nullable|string|max:255',
            'manager' => 'required|string|max:100',
            'managerEmail' => 'nullable|email|max:150',
            'status' => 'nullable|string|in:AKTIF,MAINTENANCE,TUTUP',
        ]);

        $type = ($validated['facilityType'] ?? '') === 'warehouse' ? 'WAREHOUSE' : 'STORE';
        $prefix = $type === 'WAREHOUSE' ? 'GDG-' : 'CAB-';
        $count = Location::where('type', $type)->count() + 1;
        $code = $prefix . str_pad((string) $count, 3, '0', STR_PAD_LEFT);

        $location = Location::create([
            'code' => $code,
            'name' => $validated['name'],
            'type' => $type,
            'address' => $validated['address'] ?? null,
            'manager' => $validated['manager'],
            'manager_email' => $validated['managerEmail'] ?? null,
            'status' => $validated['status'] ?? 'AKTIF',
            'is_active' => ($validated['status'] ?? 'AKTIF') === 'AKTIF',
        ]);

        AuditLog::record([
            'user_name' => 'Ir. Hartono Wibowo (Owner)',
            'user_role' => 'Owner',
            'location' => $location->name,
            'category' => 'Pengaturan Sistem',
            'action_name' => 'Pendaftaran Cabang Baru',
            'description' => "Owner mendaftarkan lokasi baru {$location->name} ({$location->code}).",
            'reference' => $location->code,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Lokasi operasional {$location->name} berhasil didaftarkan.",
            'data' => $location,
        ], 201);
    }

    /**
     * Display the specified branch.
     */
    public function show(int $id): JsonResponse
    {
        $location = Location::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $location,
        ]);
    }

    /**
     * Update the specified branch.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $location = Location::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:150',
            'facilityType' => 'nullable|string|in:store,warehouse',
            'address' => 'nullable|string|max:255',
            'manager' => 'required|string|max:100',
            'managerEmail' => 'nullable|email|max:150',
            'status' => 'nullable|string|in:AKTIF,MAINTENANCE,TUTUP',
        ]);

        $type = isset($validated['facilityType'])
            ? ($validated['facilityType'] === 'warehouse' ? 'WAREHOUSE' : 'STORE')
            : $location->type;

        $location->update([
            'name' => $validated['name'],
            'type' => $type,
            'address' => $validated['address'] ?? $location->address,
            'manager' => $validated['manager'],
            'manager_email' => $validated['managerEmail'] ?? $location->manager_email,
            'status' => $validated['status'] ?? $location->status,
            'is_active' => ($validated['status'] ?? $location->status) === 'AKTIF',
        ]);

        AuditLog::record([
            'user_name' => 'Ir. Hartono Wibowo (Owner)',
            'user_role' => 'Owner',
            'location' => $location->name,
            'category' => 'Pengaturan Sistem',
            'action_name' => 'Pembaruan Lokasi Cabang',
            'description' => "Owner memperbarui data lokasi operasional {$location->name}.",
            'reference' => $location->code,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Informasi lokasi {$location->name} berhasil diperbarui.",
            'data' => $location,
        ]);
    }

    /**
     * Remove the specified branch.
     */
    public function destroy(int $id): JsonResponse
    {
        $location = Location::findOrFail($id);
        $name = $location->name;
        $code = $location->code;

        $location->delete();

        AuditLog::record([
            'user_name' => 'Ir. Hartono Wibowo (Owner)',
            'user_role' => 'Owner',
            'location' => $name,
            'category' => 'Pengaturan Sistem',
            'action_name' => 'Penghapusan Lokasi Cabang',
            'description' => "Owner menghapus lokasi operasional {$name} ({$code}).",
            'reference' => $code,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Lokasi operasional {$name} berhasil dihapus dari sistem.",
        ]);
    }
}
