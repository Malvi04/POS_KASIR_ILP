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
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'id_produk')) {
                $table->string('id_produk', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('products', 'kode_barcode')) {
                $table->string('kode_barcode', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('products', 'kode_produk')) {
                $table->string('kode_produk', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('products', 'qty_produk')) {
                $table->decimal('qty_produk', 15, 2)->default(0)->index();
            }
            if (!Schema::hasColumn('products', 'nama_produk')) {
                $table->string('nama_produk', 255)->nullable()->index();
            }
            if (!Schema::hasColumn('products', 'satuan_unit')) {
                $table->string('satuan_unit', 50)->nullable()->default('Pcs');
            }
            if (!Schema::hasColumn('products', 'harga_beli')) {
                $table->decimal('harga_beli', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('products', 'harga_jual_umum')) {
                $table->decimal('harga_jual_umum', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('products', 'harga_jual_member')) {
                $table->decimal('harga_jual_member', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('products', 'produk_disc')) {
                $table->decimal('produk_disc', 8, 2)->default(0);
            }
            if (!Schema::hasColumn('products', 'minimum_stock')) {
                $table->integer('minimum_stock')->default(5);
            }
            if (!Schema::hasColumn('products', 'ppn_out')) {
                $table->decimal('ppn_out', 8, 2)->default(11);
            }
            if (!Schema::hasColumn('products', 'ppn_chk')) {
                $table->string('ppn_chk', 10)->default('Y');
            }
            if (!Schema::hasColumn('products', 'harga_beli_terakhir')) {
                $table->decimal('harga_beli_terakhir', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('products', 'status_barang')) {
                $table->string('status_barang', 50)->default('TERSEDIA')->index();
            }
            if (!Schema::hasColumn('products', 'kode_suplier')) {
                $table->string('kode_suplier', 100)->nullable()->index();
            }
            if (!Schema::hasColumn('products', 'manufacturing_date')) {
                $table->date('manufacturing_date')->nullable();
            }
            if (!Schema::hasColumn('products', 'exp_date')) {
                $table->date('exp_date')->nullable()->index();
            }
        });

        // Backfill existing rows with matching data
        DB::statement("
            UPDATE products SET
                id_produk = COALESCE(id_produk, ilp_id, id::text),
                kode_barcode = COALESCE(kode_barcode, barcode, sku),
                kode_produk = COALESCE(kode_produk, sku),
                qty_produk = COALESCE(qty_produk, stock, 0),
                nama_produk = COALESCE(nama_produk, name),
                satuan_unit = COALESCE(satuan_unit, unit, 'Pcs'),
                harga_beli = COALESCE(harga_beli, cost_price, 0),
                harga_jual_umum = COALESCE(harga_jual_umum, price, 0),
                harga_jual_member = COALESCE(harga_jual_member, member_price, price, 0),
                produk_disc = COALESCE(produk_disc, clearance_discount_percent, 0),
                minimum_stock = COALESCE(minimum_stock, min_stock, 5),
                ppn_out = COALESCE(ppn_out, tax_rate, 11),
                ppn_chk = COALESCE(ppn_chk, CASE WHEN is_taxable THEN 'Y' ELSE 'N' END),
                harga_beli_terakhir = COALESCE(harga_beli_terakhir, last_cost_price, cost_price, 0),
                status_barang = COALESCE(status_barang, status, 'TERSEDIA'),
                kode_suplier = COALESCE(kode_suplier, ko_sup),
                exp_date = COALESCE(exp_date, expired_date)
            WHERE id_produk IS NULL OR nama_produk IS NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn([
                'id_produk',
                'kode_barcode',
                'kode_produk',
                'qty_produk',
                'nama_produk',
                'satuan_unit',
                'harga_beli',
                'harga_jual_umum',
                'harga_jual_member',
                'produk_disc',
                'minimum_stock',
                'ppn_out',
                'ppn_chk',
                'harga_beli_terakhir',
                'status_barang',
                'kode_suplier',
                'manufacturing_date',
                'exp_date',
            ]);
        });
    }
};
