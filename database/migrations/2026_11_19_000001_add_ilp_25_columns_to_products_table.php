<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add 25 standard ILP columns directly to products table.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'min_qty1')) {
                $table->decimal('min_qty1', 10, 2)->default(1)->after('price');
            }
            if (!Schema::hasColumn('products', 'min_qty2')) {
                $table->decimal('min_qty2', 10, 2)->nullable()->after('min_qty1');
            }
            if (!Schema::hasColumn('products', 'min_qty3')) {
                $table->decimal('min_qty3', 10, 2)->nullable()->after('min_qty2');
            }
            if (!Schema::hasColumn('products', 'harga_level1')) {
                $table->decimal('harga_level1', 14, 2)->nullable()->after('min_qty3');
            }
            if (!Schema::hasColumn('products', 'ko_sup')) {
                $table->string('ko_sup', 50)->nullable()->after('markup_percent')->index();
            }
            if (!Schema::hasColumn('products', 'act')) {
                $table->string('act', 10)->default('Y')->after('ko_sup');
            }
            if (!Schema::hasColumn('products', 'masa')) {
                $table->string('masa', 50)->nullable()->after('act');
            }
            if (!Schema::hasColumn('products', 'dump')) {
                $table->text('dump')->nullable()->after('masa');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $cols = ['min_qty1', 'min_qty2', 'min_qty3', 'harga_level1', 'ko_sup', 'act', 'masa', 'dump'];
            $existing = [];
            foreach ($cols as $c) {
                if (Schema::hasColumn('products', $c)) {
                    $existing[] = $c;
                }
            }
            if (!empty($existing)) {
                $table->dropColumn($existing);
            }
        });
    }
};
