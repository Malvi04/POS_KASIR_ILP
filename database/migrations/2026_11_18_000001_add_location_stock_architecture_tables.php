<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Stock Master vs Stock Toko (Per-Location Stock & FIFO/FEFO Architecture).
     */
    public function up(): void
    {
        if (!Schema::hasTable('product_stocks')) {
            Schema::create('product_stocks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->integer('stock')->default(0);
                $table->integer('min_stock')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('first_received_at')->nullable();
                $table->timestamp('last_received_at')->nullable();
                $table->timestamp('last_sold_at')->nullable();
                $table->unsignedBigInteger('version')->default(1);
                $table->timestamps();

                $table->unique(['location_id', 'product_id'], 'product_stocks_location_product_unique');
                $table->index(['location_id', 'stock'], 'product_stocks_location_stock_idx');
                $table->index(['product_id', 'location_id'], 'product_stocks_product_location_idx');
            });
        }

        if (!Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->foreignId('stock_batch_id')->nullable()->constrained('stock_batches')->nullOnDelete();
                $table->string('movement_type', 40)->index();
                $table->integer('qty_change');
                $table->integer('stock_before')->default(0);
                $table->integer('stock_after')->default(0);
                $table->string('reference_type', 60)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('reference_number', 100)->nullable()->index();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['location_id', 'product_id', 'created_at'], 'stock_movements_loc_prod_created_idx');
            });
        }

        if (Schema::hasTable('stock_batches')) {
            Schema::table('stock_batches', function (Blueprint $table) {
                $table->index(['location_id', 'product_id', 'remaining_qty'], 'idx_stock_batches_loc_prod_rem');
            });
        }

        if (Schema::hasTable('goods_receipts')) {
            Schema::table('goods_receipts', function (Blueprint $table) {
                if (!Schema::hasColumn('goods_receipts', 'from_location_id')) {
                    $table->foreignId('from_location_id')->nullable()->after('purchase_id')->constrained('locations')->nullOnDelete();
                }
                if (!Schema::hasColumn('goods_receipts', 'to_location_id')) {
                    $table->foreignId('to_location_id')->nullable()->after('from_location_id')->constrained('locations')->nullOnDelete();
                }
                if (!Schema::hasColumn('goods_receipts', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->after('received_at');
                }
            });
        }

        if (Schema::hasTable('goods_receipt_items')) {
            Schema::table('goods_receipt_items', function (Blueprint $table) {
                if (!Schema::hasColumn('goods_receipt_items', 'expired_date')) {
                    $table->date('expired_date')->nullable()->after('condition');
                }
                if (!Schema::hasColumn('goods_receipt_items', 'batch_number')) {
                    $table->string('batch_number', 100)->nullable()->after('expired_date');
                }
                if (!Schema::hasColumn('goods_receipt_items', 'batch_allocations')) {
                    $table->json('batch_allocations')->nullable()->after('batch_number');
                }
            });
        }

        if (Schema::hasTable('cashier_shifts')) {
            Schema::table('cashier_shifts', function (Blueprint $table) {
                if (!Schema::hasColumn('cashier_shifts', 'location_id')) {
                    $table->foreignId('location_id')->nullable()->after('terminal_code')->constrained('locations')->nullOnDelete();
                }
                if (!Schema::hasColumn('cashier_shifts', 'branch')) {
                    $table->string('branch', 100)->nullable()->after('location_id')->index();
                }
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (!Schema::hasColumn('transactions', 'location_id')) {
                    $table->foreignId('location_id')->nullable()->after('terminal_code')->constrained('locations')->nullOnDelete();
                }
                if (!Schema::hasColumn('transactions', 'branch')) {
                    $table->string('branch', 100)->nullable()->after('location_id')->index();
                }
            });
        }

        if (Schema::hasTable('transaction_items')) {
            Schema::table('transaction_items', function (Blueprint $table) {
                if (!Schema::hasColumn('transaction_items', 'stock_batch_id')) {
                    $table->foreignId('stock_batch_id')->nullable()->after('product_id')->constrained('stock_batches')->nullOnDelete();
                }
                if (!Schema::hasColumn('transaction_items', 'batch_allocations')) {
                    $table->json('batch_allocations')->nullable()->after('stock_batch_id');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('transaction_items')) {
            Schema::table('transaction_items', function (Blueprint $table) {
                if (Schema::hasColumn('transaction_items', 'stock_batch_id')) {
                    $table->dropForeign(['stock_batch_id']);
                    $table->dropColumn('stock_batch_id');
                }
                if (Schema::hasColumn('transaction_items', 'batch_allocations')) {
                    $table->dropColumn('batch_allocations');
                }
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                if (Schema::hasColumn('transactions', 'location_id')) {
                    $table->dropForeign(['location_id']);
                    $table->dropColumn('location_id');
                }
                if (Schema::hasColumn('transactions', 'branch')) {
                    $table->dropColumn('branch');
                }
            });
        }

        if (Schema::hasTable('cashier_shifts')) {
            Schema::table('cashier_shifts', function (Blueprint $table) {
                if (Schema::hasColumn('cashier_shifts', 'location_id')) {
                    $table->dropForeign(['location_id']);
                    $table->dropColumn('location_id');
                }
                if (Schema::hasColumn('cashier_shifts', 'branch')) {
                    $table->dropColumn('branch');
                }
            });
        }

        if (Schema::hasTable('goods_receipt_items')) {
            Schema::table('goods_receipt_items', function (Blueprint $table) {
                $cols = array_filter(['expired_date', 'batch_number', 'batch_allocations'], fn ($c) => Schema::hasColumn('goods_receipt_items', $c));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('goods_receipts')) {
            Schema::table('goods_receipts', function (Blueprint $table) {
                if (Schema::hasColumn('goods_receipts', 'from_location_id')) {
                    $table->dropForeign(['from_location_id']);
                    $table->dropColumn('from_location_id');
                }
                if (Schema::hasColumn('goods_receipts', 'to_location_id')) {
                    $table->dropForeign(['to_location_id']);
                    $table->dropColumn('to_location_id');
                }
                if (Schema::hasColumn('goods_receipts', 'verified_at')) {
                    $table->dropColumn('verified_at');
                }
            });
        }

        if (Schema::hasTable('stock_batches')) {
            Schema::table('stock_batches', function (Blueprint $table) {
                $table->dropIndex('idx_stock_batches_loc_prod_rem');
            });
        }

        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('product_stocks');
    }
};
