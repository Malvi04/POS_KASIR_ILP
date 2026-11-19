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
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL specific clean migration
            DB::statement('ALTER TABLE transaction_items ALTER COLUMN product_id DROP NOT NULL');
            DB::statement('ALTER TABLE transaction_items DROP CONSTRAINT IF EXISTS transaction_items_product_id_foreign');
            DB::statement('ALTER TABLE transaction_items ADD CONSTRAINT transaction_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL');

            DB::statement('ALTER TABLE stock_opname_items ALTER COLUMN product_id DROP NOT NULL');
            DB::statement('ALTER TABLE stock_opname_items DROP CONSTRAINT IF EXISTS stock_opname_items_product_id_foreign');
            DB::statement('ALTER TABLE stock_opname_items ADD CONSTRAINT stock_opname_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL');
        } else {
            // SQLite / MySQL
            Schema::table('transaction_items', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            });
            Schema::table('stock_opname_items', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE transaction_items DROP CONSTRAINT IF EXISTS transaction_items_product_id_foreign');
            DB::statement('ALTER TABLE transaction_items ADD CONSTRAINT transaction_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id)');

            DB::statement('ALTER TABLE stock_opname_items DROP CONSTRAINT IF EXISTS stock_opname_items_product_id_foreign');
            DB::statement('ALTER TABLE stock_opname_items ADD CONSTRAINT stock_opname_items_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id)');
        }
    }
};
