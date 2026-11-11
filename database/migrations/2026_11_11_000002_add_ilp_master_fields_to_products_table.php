<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'ilp_id')) {
                $table->string('ilp_id', 50)->nullable()->after('id')->index();
            }
            if (!Schema::hasColumn('products', 'last_cost_price')) {
                $table->decimal('last_cost_price', 14, 2)->nullable()->after('cost_price');
            }
            if (!Schema::hasColumn('products', 'reorder_point')) {
                $table->integer('reorder_point')->nullable()->after('min_stock');
            }
            if (!Schema::hasColumn('products', 'markup_percent')) {
                $table->decimal('markup_percent', 8, 2)->nullable()->after('clearance_discount_percent');
            }
            if (!Schema::hasColumn('products', 'extra_attributes')) {
                $table->json('extra_attributes')->nullable()->after('rack_location');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $columns = [];
            foreach (['ilp_id', 'last_cost_price', 'reorder_point', 'markup_percent', 'extra_attributes'] as $col) {
                if (Schema::hasColumn('products', $col)) {
                    $columns[] = $col;
                }
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
