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
        // 1. Tambah brand pada tabel products
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'brand')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('brand', 100)->nullable()->after('name')->index();
            });
        }

        // 2. Tambah sent_by pada tabel goods_receipts (akun pengirim dari gudang/manager)
        if (Schema::hasTable('goods_receipts') && !Schema::hasColumn('goods_receipts', 'sent_by')) {
            Schema::table('goods_receipts', function (Blueprint $table) {
                $table->foreignId('sent_by')->nullable()->after('purchase_id')->constrained('users')->nullOnDelete();
            });
        }

        // 3. Tambah relasi produk, cabang, dan kuota pada coupon_units
        if (Schema::hasTable('coupon_units')) {
            Schema::table('coupon_units', function (Blueprint $table) {
                if (!Schema::hasColumn('coupon_units', 'product_id')) {
                    $table->foreignId('product_id')->nullable()->after('id')->constrained('products')->nullOnDelete();
                }
                if (!Schema::hasColumn('coupon_units', 'location_id')) {
                    $table->foreignId('location_id')->nullable()->after('product_id')->constrained('locations')->nullOnDelete();
                }
                if (!Schema::hasColumn('coupon_units', 'branch')) {
                    $table->string('branch', 100)->nullable()->after('location_id');
                }
                if (!Schema::hasColumn('coupon_units', 'quota')) {
                    $table->integer('quota')->default(1)->after('branch');
                }
                if (!Schema::hasColumn('coupon_units', 'allocated_by')) {
                    $table->foreignId('allocated_by')->nullable()->after('quota')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'brand')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('brand');
            });
        }

        if (Schema::hasTable('goods_receipts') && Schema::hasColumn('goods_receipts', 'sent_by')) {
            Schema::table('goods_receipts', function (Blueprint $table) {
                $table->dropForeign(['sent_by']);
                $table->dropColumn('sent_by');
            });
        }

        if (Schema::hasTable('coupon_units')) {
            Schema::table('coupon_units', function (Blueprint $table) {
                $cols = ['product_id', 'location_id', 'branch', 'quota', 'allocated_by'];
                foreach ($cols as $col) {
                    if (Schema::hasColumn('coupon_units', $col)) {
                        if (in_array($col, ['product_id', 'location_id', 'allocated_by'])) {
                            try { $table->dropForeign([$col]); } catch (\Throwable $e) {}
                        }
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
