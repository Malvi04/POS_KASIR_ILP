<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // High performance indexes for scale (18,000+ products and large datasets)
        DB::statement('CREATE INDEX IF NOT EXISTS products_name_pattern_idx ON products (LOWER(name) varchar_pattern_ops);');
        DB::statement('CREATE INDEX IF NOT EXISTS products_sku_pattern_idx ON products (sku varchar_pattern_ops);');
        DB::statement('CREATE INDEX IF NOT EXISTS products_barcode_pattern_idx ON products (barcode varchar_pattern_ops);');
        DB::statement('CREATE INDEX IF NOT EXISTS products_cat_stock_idx ON products (category, stock);');
        DB::statement('CREATE INDEX IF NOT EXISTS products_stock_min_idx ON products (stock, min_stock);');
        DB::statement('CREATE INDEX IF NOT EXISTS transactions_created_status_idx ON transactions (created_at, status);');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS products_name_pattern_idx;');
        DB::statement('DROP INDEX IF EXISTS products_sku_pattern_idx;');
        DB::statement('DROP INDEX IF EXISTS products_barcode_pattern_idx;');
        DB::statement('DROP INDEX IF EXISTS products_cat_stock_idx;');
        DB::statement('DROP INDEX IF EXISTS products_stock_min_idx;');
        DB::statement('DROP INDEX IF EXISTS transactions_created_status_idx;');
    }
};
