<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                if (!Schema::hasColumn('suppliers', 'company_name')) {
                    $table->string('company_name', 150)->nullable()->after('name');
                }
                if (!Schema::hasColumn('suppliers', 'pic_name')) {
                    $table->string('pic_name', 100)->nullable()->after('owner_name');
                }
                if (!Schema::hasColumn('suppliers', 'email')) {
                    $table->string('email', 150)->nullable()->after('phone');
                }
                if (!Schema::hasColumn('suppliers', 'city')) {
                    $table->string('city', 100)->nullable()->after('address');
                }
                if (!Schema::hasColumn('suppliers', 'business_categories')) {
                    $table->json('business_categories')->nullable()->after('category');
                }
                if (!Schema::hasColumn('suppliers', 'supplier_type')) {
                    $table->string('supplier_type', 30)->default('REGULAR')->after('status')->index();
                }
            });

            // Classify existing UMKM consignment partners cleanly without touching regular suppliers
            DB::table('suppliers')
                ->where('code', 'like', 'UMKM-%')
                ->update(['supplier_type' => 'CONSIGNMENT']);
        }

        if (!Schema::hasTable('supplier_products')) {
            Schema::create('supplier_products', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->dateTime('first_purchase_at')->nullable();
                $table->dateTime('last_purchase_at')->nullable()->index();
                $table->decimal('last_purchase_price', 15, 2)->default(0);
                $table->foreignId('last_purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
                $table->integer('total_transactions')->default(0);
                $table->string('supply_status', 30)->default('AKTIF')->index(); // AKTIF, TIDAK_AKTIF
                $table->timestamps();

                $table->unique(['supplier_id', 'product_id'], 'uq_supplier_products_supplier_product');
                $table->index(['supplier_id', 'supply_status']);
                $table->index(['product_id', 'last_purchase_at']);
            });

            // Backfill supplier_products from any already-confirmed regular purchase receipts
            $this->backfillSupplierProductsFromReceipts();
        }
    }

    /**
     * Backfill supplier_products from existing confirmed GoodsReceiptItem rows (no dummy data).
     */
    protected function backfillSupplierProductsFromReceipts(): void
    {
        if (
            !Schema::hasTable('goods_receipt_items') ||
            !Schema::hasTable('goods_receipts') ||
            !Schema::hasTable('purchases') ||
            !Schema::hasTable('products')
        ) {
            return;
        }

        $rows = DB::table('goods_receipt_items as gri')
            ->join('goods_receipts as gr', 'gr.id', '=', 'gri.goods_receipt_id')
            ->join('purchases as p', 'p.id', '=', 'gr.purchase_id')
            ->join('products as prod', 'prod.id', '=', 'gri.product_id')
            ->whereNotNull('p.supplier_id')
            ->where('gri.qty_received', '>', 0)
            ->where('prod.is_consignment', false)
            ->where(function ($q) {
                $q->whereNull('gri.ownership_type')
                    ->orWhere('gri.ownership_type', '!=', 'CONSIGNMENT');
            })
            ->whereNotIn('p.status', ['DIBATALKAN', 'CANCELLED'])
            ->orderBy('gr.received_at', 'asc')
            ->orderBy('gri.id', 'asc')
            ->select([
                'p.supplier_id',
                'gri.product_id',
                'p.id as purchase_id',
                'gr.received_at',
                'gri.actual_unit_cost',
            ])
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $grouped = [];
        foreach ($rows as $row) {
            $key = $row->supplier_id . ':' . $row->product_id;
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'supplier_id' => (int) $row->supplier_id,
                    'product_id' => (int) $row->product_id,
                    'first_purchase_at' => $row->received_at,
                    'last_purchase_at' => $row->received_at,
                    'last_purchase_price' => (float) $row->actual_unit_cost,
                    'last_purchase_id' => (int) $row->purchase_id,
                    'purchase_ids' => [(int) $row->purchase_id => true],
                ];
            } else {
                $grouped[$key]['last_purchase_at'] = $row->received_at;
                $grouped[$key]['last_purchase_price'] = (float) $row->actual_unit_cost;
                $grouped[$key]['last_purchase_id'] = (int) $row->purchase_id;
                $grouped[$key]['purchase_ids'][(int) $row->purchase_id] = true;
            }
        }

        $now = now();
        foreach ($grouped as $item) {
            DB::table('supplier_products')->insert([
                'supplier_id' => $item['supplier_id'],
                'product_id' => $item['product_id'],
                'first_purchase_at' => $item['first_purchase_at'],
                'last_purchase_at' => $item['last_purchase_at'],
                'last_purchase_price' => $item['last_purchase_price'],
                'last_purchase_id' => $item['last_purchase_id'],
                'total_transactions' => count($item['purchase_ids']),
                'supply_status' => 'AKTIF',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_products');

        if (Schema::hasTable('suppliers')) {
            Schema::table('suppliers', function (Blueprint $table) {
                $cols = array_filter([
                    'company_name',
                    'pic_name',
                    'email',
                    'city',
                    'business_categories',
                    'supplier_type',
                ], fn ($c) => Schema::hasColumn('suppliers', $c));

                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
