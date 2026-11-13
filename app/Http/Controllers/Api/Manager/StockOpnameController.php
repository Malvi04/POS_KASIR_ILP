<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Shared\NotificationController;
use App\Models\Product;
use App\Models\StockOpname;
use App\Models\StockOpnameItem;
use App\Services\ExcelExportService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StockOpnameController extends Controller
{
    /**
     * List all Stock Opname sessions.
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status'); // DRAFT, COMPLETED, CANCELLED
        $query = StockOpname::query();

        if ($status && $status !== 'all') {
            $query->where('status', strtoupper($status));
        }

        $sessions = $query->orderBy('id', 'desc')->paginate(15);

        $summary = [
            'total_sessions' => StockOpname::count(),
            'active_drafts' => StockOpname::where('status', 'DRAFT')->count(),
            'completed_sessions' => StockOpname::where('status', 'COMPLETED')->count(),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $sessions->items(),
            'meta' => [
                'current_page' => $sessions->currentPage(),
                'last_page' => $sessions->lastPage(),
                'total' => $sessions->total(),
            ],
        ]);
    }

    /**
     * Create new Stock Opname session (Snapshot system stock without disrupting store operations).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'branch' => 'nullable|string|max:100',
            'conducted_by' => 'required|string|max:100',
            'category' => 'nullable|string', // Optional category filter
            'notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated) {
            $code = 'SOP-' . date('Ymd') . '-' . Str::upper(Str::random(4));

            $opname = StockOpname::create([
                'opname_code' => $code,
                'title' => $validated['title'],
                'branch' => $validated['branch'] ?? 'Pusat',
                'conducted_by' => $validated['conducted_by'],
                'status' => 'DRAFT',
                'notes' => $validated['notes'] ?? 'Stock opname berkala',
            ]);

            // Snapshot products without locking store sales
            $productQuery = Product::query();
            if (!empty($validated['category']) && $validated['category'] !== 'Semua') {
                $productQuery->where('category', $validated['category']);
            }
            $products = $productQuery->orderBy('name')->get();

            foreach ($products as $p) {
                StockOpnameItem::create([
                    'stock_opname_id' => $opname->id,
                    'product_id' => $p->id,
                    'product_sku' => $p->sku,
                    'product_name' => $p->name,
                    'system_stock' => (int) $p->stock,
                    'physical_stock' => (int) $p->stock, // Initialized with system stock
                    'variance' => 0,
                    'unit_cost' => (float) $p->cost_price,
                    'variance_value' => 0,
                    'variance_reason' => 'Sesuai',
                    'is_audited' => false,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => "Sesi Stock Opname [{$opname->title}] berhasil dibuat dengan {$products->count()} produk tersnapshot.",
                'data' => $opname->fresh('items'),
            ], 201);
        });
    }

    /**
     * Show Stock Opname detail with item rows, variance report, and audit progress.
     */
    public function show(int $id): JsonResponse
    {
        $opname = StockOpname::with('items')->find($id);
        if (!$opname) {
            return response()->json(['success' => false, 'message' => 'Sesi Stock Opname tidak ditemukan.'], 404);
        }

        $items = $opname->items;
        $auditedCount = $items->where('is_audited', true)->count();
        $varianceCount = $items->where('variance', '!=', 0)->count();
        $totalVarianceValue = (float) $items->sum('variance_value');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $opname->id,
                'opname_code' => $opname->opname_code,
                'title' => $opname->title,
                'branch' => $opname->branch,
                'conducted_by' => $opname->conducted_by,
                'status' => $opname->status,
                'notes' => $opname->notes,
                'created_at' => $opname->created_at->format('d M Y H:i'),
                'completed_at' => $opname->completed_at ? $opname->completed_at->format('d M Y H:i') : null,
                'progress' => [
                    'total_items' => $items->count(),
                    'audited_count' => $auditedCount,
                    'variance_count' => $varianceCount,
                    'total_variance_value' => $totalVarianceValue,
                    'audit_percent' => $items->count() > 0 ? round(($auditedCount / $items->count()) * 100, 1) : 0,
                ],
                'items' => $items,
            ],
        ]);
    }

    /**
     * Verify / Update physical stock for a single item (or barcode scan).
     */
    public function verifyItem(Request $request, int $id): JsonResponse
    {
        $opname = StockOpname::find($id);
        if (!$opname || $opname->status !== 'DRAFT') {
            return response()->json(['success' => false, 'message' => 'Sesi opname tidak aktif atau sudah selesai.'], 422);
        }

        $validated = $request->validate([
            'item_id' => 'nullable|integer|exists:stock_opname_items,id',
            'barcode' => 'nullable|string',
            'physical_stock' => 'nullable|integer|min:0',
            'increment_scan' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $item = null;
        if (!empty($validated['item_id'])) {
            $item = StockOpnameItem::where('stock_opname_id', $opname->id)->find($validated['item_id']);
        } elseif (!empty($validated['barcode'])) {
            $product = Product::where('barcode', $validated['barcode'])->orWhere('sku', $validated['barcode'])->first();
            if ($product) {
                $item = StockOpnameItem::where('stock_opname_id', $opname->id)->where('product_id', $product->id)->first();
            }
        }

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Produk tidak ditemukan dalam sesi opname ini.'], 404);
        }

        if (!empty($validated['increment_scan'])) {
            $item->physical_stock += 1;
        } elseif (isset($validated['physical_stock'])) {
            $item->physical_stock = (int) $validated['physical_stock'];
        }

        $item->variance = $item->physical_stock - $item->system_stock;
        $item->variance_value = round($item->variance * $item->unit_cost, 2);
        $item->is_audited = true;

        if ($item->variance < 0) {
            $item->variance_reason = 'Hilang / Selisih Kurang';
        } elseif ($item->variance > 0) {
            $item->variance_reason = 'Lebih / Bonus Supplier';
        } else {
            $item->variance_reason = 'Sesuai';
        }

        if (!empty($validated['notes'])) {
            $item->notes = $validated['notes'];
        }
        $item->save();

        return response()->json([
            'success' => true,
            'message' => "Stok fisik [{$item->product_name}] terverifikasi ({$item->physical_stock} fisik, selisih: {$item->variance}).",
            'data' => $item,
        ]);
    }

    /**
     * Finalize Stock Opname and apply inventory adjustments to products.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $opname = StockOpname::with('items')->find($id);
        if (!$opname) {
            return response()->json(['success' => false, 'message' => 'Sesi tidak ditemukan.'], 404);
        }

        if ($opname->status !== 'DRAFT') {
            return response()->json(['success' => false, 'message' => 'Sesi sudah selesai atau dibatalkan.'], 422);
        }

        return DB::transaction(function () use ($opname, $request) {
            $items = $opname->items;
            $varianceCount = 0;
            $totalVarianceValue = 0;

            foreach ($items as $item) {
                if ($item->is_audited && $item->variance !== 0) {
                    $product = Product::lockForUpdate()->find($item->product_id);
                    if ($product) {
                        $product->stock = $item->physical_stock;
                        if ($product->stock <= 0) {
                            $product->status = 'HABIS';
                        } elseif ($product->stock <= $product->min_stock) {
                            $product->status = 'STOK_RENDAH';
                        } else {
                            $product->status = 'TERSEDIA';
                        }
                        $product->save();
                    }
                    $varianceCount++;
                    $totalVarianceValue += $item->variance_value;
                }
            }

            $opname->update([
                'status' => 'COMPLETED',
                'total_items_audited' => $items->where('is_audited', true)->count(),
                'total_items_variance' => $varianceCount,
                'total_variance_value' => $totalVarianceValue,
                'completed_at' => now(),
                'notes' => $request->input('notes', $opname->notes),
            ]);

            try {
                $isDiscrepancy = $varianceCount > 0;
                NotificationController::notify(
                    role: 'MANAGER',
                    title: "Stock Opname Selesai: {$opname->opname_code}",
                    message: "Audit selesai ({$opname->total_items_audited} item). " . ($isDiscrepancy ? "Ditemukan {$varianceCount} item deviasi senilai Rp " . number_format($totalVarianceValue, 0, ',', '.') : "Semua item fisik cocok 100%."),
                    category: 'OPNAME',
                    link: '/manager/stock-opname',
                    icon: $isDiscrepancy ? 'warning' : 'inventory_2',
                    color: $isDiscrepancy ? 'amber' : 'emerald',
                    referenceId: $opname->opname_code
                );

                NotificationController::notify(
                    role: 'OWNER',
                    title: "Laporan Opname: {$opname->opname_code}",
                    message: "Hasil opname persediaan: {$varianceCount} item selisih, nilai deviasi Rp " . number_format($totalVarianceValue, 0, ',', '.') . '.',
                    category: 'OPNAME',
                    link: '/owner/products',
                    icon: $isDiscrepancy ? 'report_problem' : 'verified',
                    color: $isDiscrepancy ? 'rose' : 'emerald',
                    referenceId: $opname->opname_code
                );

                NotificationController::notify(
                    role: 'KASIR',
                    title: "Stok Sistem Diperbarui: {$opname->opname_code}",
                    message: "Hasil stock opname telah disinkronkan ke master inventori kasir.",
                    category: 'OPNAME',
                    link: '/kasir/inventory',
                    icon: 'sync',
                    color: 'blue',
                    referenceId: $opname->opname_code
                );
            } catch (\Throwable $e) {}

            return response()->json([
                'success' => true,
                'message' => "Sesi Stock Opname [{$opname->opname_code}] berhasil diselesaikan. Penyesuaian stok sistem telah otomatis diterapkan.",
                'data' => $opname,
            ]);
        });
    }

    /**
     * Export Stock Opname Variance Report to professional Excel (.xlsx) format.
     */
    public function exportExcel(int $id, ExcelExportService $excelService): StreamedResponse
    {
        $opname = StockOpname::with('items')->findOrFail($id);
        $filename = 'Variance_Report_' . $opname->opname_code . '.xlsx';
        $spreadsheet = $excelService->createStockOpnameSpreadsheet($opname);

        return $excelService->downloadSpreadsheet($spreadsheet, $filename);
    }

    /**
     * Export Stock Opname Variance Report to CSV (Excel-compatible with sep=,).
     */
    public function exportCsv(int $id): StreamedResponse
    {
        $opname = StockOpname::with('items')->findOrFail($id);

        $filename = 'Variance_Report_' . $opname->opname_code . '.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return new StreamedResponse(function () use ($opname) {
            $output = fopen('php://output', 'w');
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
            fwrite($output, "sep=,\r\n"); // Excel delimiter directive to prevent column collapsing

            fputcsv($output, ['PT INDO LAMPUNG PERKASA - MINIMARKET KOPERASI']);
            fputcsv($output, ['LAPORAN VARIANCE STOCK OPNAME']);
            fputcsv($output, ['Kode Opname', $opname->opname_code]);
            fputcsv($output, ['Judul Audit', $opname->title]);
            fputcsv($output, ['Auditor', $opname->conducted_by]);
            fputcsv($output, ['Status', $opname->status]);
            fputcsv($output, ['Tanggal Selesai', $opname->completed_at ? $opname->completed_at->format('d/m/Y H:i') : '-']);
            fputcsv($output, []);

            fputcsv($output, [
                'No',
                'SKU Produk',
                'Nama Produk',
                'Stok Sistem',
                'Stok Fisik',
                'Selisih (Unit)',
                'Harga Pokok (Rp)',
                'Nominal Selisih (Rp)',
                'Status Selisih',
                'Catatan',
            ]);

            $no = 1;
            foreach ($opname->items as $item) {
                fputcsv($output, [
                    $no++,
                    $item->product_sku,
                    $item->product_name,
                    $item->system_stock,
                    $item->physical_stock,
                    $item->variance,
                    number_format($item->unit_cost, 2, '.', ''),
                    number_format($item->variance_value, 2, '.', ''),
                    $item->variance_reason,
                    $item->notes ?? '-',
                ]);
            }

            fputcsv($output, []);
            fputcsv($output, ['TOTAL NILAI SELISIH (RP)', '', '', '', '', '', '', number_format($opname->total_variance_value, 2, '.', '')]);

            fclose($output);
        }, 200, $headers);
    }
}
