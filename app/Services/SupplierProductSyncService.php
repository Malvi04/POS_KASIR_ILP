<?php

namespace App\Services;

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptItem;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\SupplierProduct;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SupplierProductSyncService
{
    /**
     * Synchronize the supplier_products relation after a confirmed goods receipt item.
     * Ensures no duplicate (supplier_id, product_id) rows and keeps supplier-specific last purchase price accurate.
     */
    public function syncFromReceipt(
        Purchase $purchase,
        GoodsReceipt $receipt,
        Product $product,
        GoodsReceiptItem $grItem,
        Carbon $receivedAt
    ): ?SupplierProduct {
        if (
            !$purchase->supplier_id ||
            $product->is_consignment ||
            strtoupper((string) ($grItem->ownership_type ?: 'OUTRIGHT')) === 'CONSIGNMENT' ||
            (float) $grItem->qty_received <= 0
        ) {
            return null;
        }

        $supplierId = (int) $purchase->supplier_id;
        $productId = (int) $product->id;

        $latestRow = DB::table('goods_receipt_items as gri')
            ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
            ->join('purchases as p', 'p.id', '=', 'gr.purchase_id')
            ->where('p.supplier_id', $supplierId)
            ->where('gri.product_id', $productId)
            ->where('gri.qty_received', '>', 0)
            ->where(function ($q) {
                $q->whereNull('gri.ownership_type')
                    ->orWhere('gri.ownership_type', '!=', 'CONSIGNMENT');
            })
            ->whereNotIn('p.status', [Purchase::STATUS_DIBATALKAN, 'CANCELLED'])
            ->orderByDesc('gr.received_at')
            ->orderByDesc('gri.id')
            ->select([
                'p.id as purchase_id',
                'gr.received_at',
                'gri.actual_unit_cost',
            ])
            ->first();

        $aggregates = DB::table('goods_receipt_items as gri')
            ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
            ->join('purchases as p', 'p.id', '=', 'gr.purchase_id')
            ->where('p.supplier_id', $supplierId)
            ->where('gri.product_id', $productId)
            ->where('gri.qty_received', '>', 0)
            ->where(function ($q) {
                $q->whereNull('gri.ownership_type')
                    ->orWhere('gri.ownership_type', '!=', 'CONSIGNMENT');
            })
            ->whereNotIn('p.status', [Purchase::STATUS_DIBATALKAN, 'CANCELLED'])
            ->selectRaw('MIN(gr.received_at) as first_at, COUNT(DISTINCT p.id) as tx_count')
            ->first();

        $firstAt = $aggregates?->first_at ? Carbon::parse($aggregates->first_at) : $receivedAt;
        $lastAt = $latestRow?->received_at ? Carbon::parse($latestRow->received_at) : $receivedAt;
        $lastPrice = $latestRow ? round((float) $latestRow->actual_unit_cost, 2) : round((float) $grItem->actual_unit_cost, 2);
        $lastPurchaseId = $latestRow ? (int) $latestRow->purchase_id : (int) $purchase->id;
        $txCount = max(1, (int) ($aggregates?->tx_count ?? 1));

        $relation = SupplierProduct::where('supplier_id', $supplierId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();

        if (!$relation) {
            return SupplierProduct::create([
                'supplier_id' => $supplierId,
                'product_id' => $productId,
                'first_purchase_at' => $firstAt,
                'last_purchase_at' => $lastAt,
                'last_purchase_price' => $lastPrice,
                'last_purchase_id' => $lastPurchaseId,
                'total_transactions' => $txCount,
                'supply_status' => SupplierProduct::STATUS_AKTIF,
            ]);
        }

        $relation->first_purchase_at = $relation->first_purchase_at
            ? ($firstAt->lt($relation->first_purchase_at) ? $firstAt : $relation->first_purchase_at)
            : $firstAt;
        $relation->last_purchase_at = $lastAt;
        $relation->last_purchase_price = $lastPrice;
        $relation->last_purchase_id = $lastPurchaseId;
        $relation->total_transactions = $txCount;
        $relation->supply_status = SupplierProduct::STATUS_AKTIF;
        $relation->save();

        return $relation;
    }

    /**
     * Resolve global latest purchase cost price across all suppliers from valid confirmed receipts.
     */
    public function resolveGlobalLatestCostPrice(int $productId): ?float
    {
        $latest = DB::table('goods_receipt_items as gri')
            ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
            ->join('purchases as p', 'p.id', '=', 'gr.purchase_id')
            ->where('gri.product_id', $productId)
            ->where('gri.qty_received', '>', 0)
            ->where(function ($q) {
                $q->whereNull('gri.ownership_type')
                    ->orWhere('gri.ownership_type', '!=', 'CONSIGNMENT');
            })
            ->whereNotIn('p.status', [Purchase::STATUS_DIBATALKAN, 'CANCELLED'])
            ->orderByDesc('gr.received_at')
            ->orderByDesc('gri.id')
            ->select('gri.actual_unit_cost')
            ->first();

        return $latest ? round((float) $latest->actual_unit_cost, 2) : null;
    }
}
