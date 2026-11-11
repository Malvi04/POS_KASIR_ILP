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
        // 1. Suppliers / Mitra UMKM Lokal (Konsinyasi)
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->index();
            $table->string('name')->index();
            $table->string('owner_name')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('category')->default('Makanan Ringan')->index();
            $table->decimal('commission_percent', 5, 2)->default(15.00); // Default komisi koperasi 15%
            $table->string('settlement_period')->default('BULANAN'); // MINGGUAN, BULANAN
            $table->string('bank_name')->nullable();
            $table->string('bank_account')->nullable();
            $table->string('status')->default('AKTIF')->index(); // AKTIF, NONAKTIF
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. Extend products table for consignment, supplier relation, rack location & indexing
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_consignment')->default(false)->after('status')->index();
            $table->foreignId('supplier_id')->nullable()->after('is_consignment')->constrained('suppliers')->nullOnDelete();
            $table->decimal('commission_percent', 5, 2)->default(0.00)->after('supplier_id');
            $table->string('rack_location')->nullable()->after('commission_percent');

            // Composite indexes for fast stock alert & near-expired lookup
            $table->index(['stock', 'min_stock']);
            $table->index(['status', 'stock']);
        });

        // 3. Multi-Satuan Barang (Unit Conversion - Hierarki Satuan)
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('unit_name'); // e.g. Dus, Pack, Karton, Renceng
            $table->integer('conversion_factor'); // e.g. 24 -> 1 Dus = 24 Pcs
            $table->string('barcode')->nullable()->index(); // Barcode grosir 1 dus
            $table->decimal('price', 12, 2); // Harga jual 1 dus
            $table->decimal('member_price', 12, 2)->nullable();
            $table->boolean('is_base_unit')->default(false);
            $table->timestamps();

            $table->unique(['product_id', 'unit_name']);
        });

        // 4. Consignment Settlements (Bagi Hasil Omzet UMKM vs Komisi Koperasi)
        Schema::create('consignment_settlements', function (Blueprint $table) {
            $table->id();
            $table->string('settlement_number')->unique()->index();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('gross_sales', 14, 2)->default(0); // Total omzet penjualan
            $table->decimal('coop_commission', 14, 2)->default(0); // Bagian komisi milik Koperasi
            $table->decimal('umkm_net_share', 14, 2)->default(0); // Bagian bersih hak UMKM
            $table->integer('items_sold_count')->default(0);
            $table->string('payment_status')->default('PENDING')->index(); // PENDING, PAID
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'payment_status']);
        });

        // 5. Stock Opname Sessions (Input Fisik tanpa Mengganggu Operasional)
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->string('opname_code')->unique()->index();
            $table->string('title');
            $table->string('branch')->default('Pusat')->index();
            $table->string('conducted_by')->index();
            $table->string('status')->default('DRAFT')->index(); // DRAFT, COMPLETED, CANCELLED
            $table->integer('total_items_audited')->default(0);
            $table->integer('total_items_variance')->default(0);
            $table->decimal('total_variance_value', 14, 2)->default(0); // Nominal selisih (Rp)
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        // 6. Stock Opname Items (Variance Report & Detail Selisih)
        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained('stock_opnames')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('product_sku')->index();
            $table->string('product_name');
            $table->integer('system_stock');
            $table->integer('physical_stock');
            $table->integer('variance'); // physical_stock - system_stock
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('variance_value', 14, 2)->default(0); // variance * unit_cost
            $table->string('variance_reason')->nullable(); // Sesuai, Hilang, Rusak, Lebih/Bonus
            $table->text('notes')->nullable();
            $table->boolean('is_audited')->default(false);
            $table->timestamps();

            $table->index(['stock_opname_id', 'is_audited']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('consignment_settlements');
        Schema::dropIfExists('product_units');

        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropIndex(['stock', 'min_stock']);
            $table->dropIndex(['status', 'stock']);
            $table->dropColumn(['is_consignment', 'supplier_id', 'commission_percent', 'rack_location']);
        });

        Schema::dropIfExists('suppliers');
    }
};
