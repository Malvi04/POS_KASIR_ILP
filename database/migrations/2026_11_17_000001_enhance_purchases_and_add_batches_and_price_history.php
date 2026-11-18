<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                if (!Schema::hasColumn('purchases', 'supplier_invoice_number')) {
                    $table->string('supplier_invoice_number', 100)->nullable()->after('supplier_id');
                }
                if (!Schema::hasColumn('purchases', 'warehouse_name')) {
                    $table->string('warehouse_name', 150)->nullable()->after('location_id');
                }
            });
        }

        if (Schema::hasTable('purchase_items')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                if (!Schema::hasColumn('purchase_items', 'unit')) {
                    $table->string('unit', 50)->default('Pcs')->after('product_id');
                }
                if (!Schema::hasColumn('purchase_items', 'conversion_factor')) {
                    $table->integer('conversion_factor')->default(1)->after('unit');
                }
                if (!Schema::hasColumn('purchase_items', 'received_qty')) {
                    $table->decimal('received_qty', 12, 2)->default(0)->after('qty');
                }
                if (!Schema::hasColumn('purchase_items', 'discount_amount')) {
                    $table->decimal('discount_amount', 15, 2)->default(0)->after('purchase_price');
                }
                if (!Schema::hasColumn('purchase_items', 'subtotal')) {
                    $table->decimal('subtotal', 15, 2)->default(0)->after('discount_amount');
                }
            });
        }

        if (!Schema::hasTable('stock_batches')) {
            Schema::create('stock_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_number', 100)->unique()->index();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
                $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
                $table->foreignId('goods_receipt_item_id')->nullable()->constrained('goods_receipt_items')->nullOnDelete();
                $table->string('ownership_type', 50)->default('OUTRIGHT')->index();
                $table->decimal('initial_qty', 12, 2)->default(0);
                $table->decimal('remaining_qty', 12, 2)->default(0);
                $table->decimal('unit_cost', 15, 2)->default(0);
                $table->dateTime('received_at')->index();
                $table->date('expired_date')->nullable()->index();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'remaining_qty', 'expired_date', 'received_at'], 'idx_stock_batches_fefo_fifo');
            });
        }

        if (!Schema::hasTable('purchase_price_histories')) {
            Schema::create('purchase_price_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
                $table->foreignId('purchase_item_id')->nullable()->constrained('purchase_items')->nullOnDelete();
                $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('purchase_number', 100)->nullable()->index();
                $table->string('unit', 50)->default('Pcs');
                $table->integer('conversion_factor')->default(1);
                $table->decimal('raw_unit_price', 15, 2)->default(0);
                $table->decimal('previous_price', 15, 2)->default(0);
                $table->decimal('new_price', 15, 2)->default(0);
                $table->decimal('price_diff', 15, 2)->default(0);
                $table->decimal('change_percent', 8, 2)->default(0);
                $table->string('change_type', 20)->default('TETAP')->index(); // NAIK, TURUN, TETAP
                $table->dateTime('recorded_at')->index();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'recorded_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_price_histories');
        Schema::dropIfExists('stock_batches');

        if (Schema::hasTable('purchase_items')) {
            Schema::table('purchase_items', function (Blueprint $table) {
                $cols = array_filter(['unit', 'conversion_factor', 'received_qty', 'discount_amount', 'subtotal'], fn ($c) => Schema::hasColumn('purchase_items', $c));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('purchases')) {
            Schema::table('purchases', function (Blueprint $table) {
                $cols = array_filter(['supplier_invoice_number', 'warehouse_name'], fn ($c) => Schema::hasColumn('purchases', $c));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
