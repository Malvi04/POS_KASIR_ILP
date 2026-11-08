<?php

namespace App\Http\Controllers\Api\Manager;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockCorrection;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockCorrectionController extends Controller
{
    /**
     * List recent stock corrections for supervisor audit.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockCorrection::with('product')->orderByDesc('created_at');

        if ($request->filled('branch') && $request->branch !== 'all') {
            $query->where('branch', $request->branch);
        }

        if ($request->filled('reason') && $request->reason !== 'all') {
            $query->where('reason', $request->reason);
        }

        $corrections = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $corrections->items(),
            'pagination' => [
                'current_page' => $corrections->currentPage(),
                'last_page' => $corrections->lastPage(),
                'total' => $corrections->total(),
            ],
        ]);
    }

    /**
     * Store daily branch stock correction (Supervisor / Manager authorization required).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:ADD,SUBTRACT,ADJUST_TO',
            'qty' => 'required|integer|min:1',
            'reason' => 'required|string|max:100', // Rusak, Kadaluwarsa, Selisih Hitung, Retur
            'branch' => 'required|string|max:100',
            'pin' => 'required|string',
            'notes' => 'nullable|string|max:255',
        ]);

        // Validate Supervisor / Manager / Owner PIN
        $supervisor = User::where(function ($q) {
            $q->whereIn(DB::raw('LOWER(role)'), ['supervisor', 'manager', 'owner', 'superadmin', 'admin', 'spv']);
        })->where('pin', $validated['pin'])->first();

        // Fallback for default PIN 123456
        if (!$supervisor && $validated['pin'] === '123456') {
            $supervisor = User::whereIn(DB::raw('LOWER(role)'), ['supervisor', 'manager', 'owner', 'superadmin', 'admin', 'spv'])->first();
        }

        if (!$supervisor) {
            return response()->json([
                'success' => false,
                'message' => 'Otorisasi ditolak. Koreksi stok harian membutuhkan PIN Supervisor atau Manager yang valid.',
            ], 403);
        }

        $product = Product::findOrFail($validated['product_id']);
        $stockBefore = (int) $product->stock;
        $qty = (int) $validated['qty'];

        if ($validated['type'] === 'SUBTRACT') {
            $stockAfter = max(0, $stockBefore - $qty);
        } elseif ($validated['type'] === 'ADD') {
            $stockAfter = $stockBefore + $qty;
        } else { // ADJUST_TO
            $stockAfter = max(0, $qty);
        }

        DB::beginTransaction();
        try {
            // Update product stock
            $product->stock = $stockAfter;
            // Update product status
            if ($stockAfter <= 0) {
                $product->status = 'HABIS';
            } elseif ($stockAfter <= $product->min_stock) {
                $product->status = 'STOK_RENDAH';
            } else {
                $product->status = 'TERSEDIA';
            }
            $product->save();

            // Record correction audit log
            $correctionCode = 'SC-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
            $correction = StockCorrection::create([
                'correction_code' => $correctionCode,
                'product_id' => $product->id,
                'branch' => $validated['branch'],
                'type' => $validated['type'],
                'qty' => $qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'reason' => $validated['reason'],
                'user_id' => $supervisor->id,
                'supervisor_name' => $supervisor->name,
                'notes' => $validated['notes'] ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Koreksi stok harian untuk {$product->name} berhasil disimpan oleh {$supervisor->name}.",
                'data' => [
                    'correction' => $correction,
                    'product' => [
                        'id' => $product->id,
                        'name' => $product->name,
                        'sku' => $product->sku,
                        'stock_before' => $stockBefore,
                        'stock_after' => $stockAfter,
                        'status' => $product->status,
                    ],
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan koreksi stok: ' . $e->getMessage(),
            ], 500);
        }
    }
}
