<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Promo Engine ala Minimarket (Alfamart-style):
 * Harga Coret / JSM, Diskon %, Potongan Rp, Beli X Gratis Y, Beli Banyak Lebih Hemat,
 * Paket Bundling, dan Tebus Murah (Purchase With Purchase).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (!Schema::hasColumn('promotions', 'scope')) {
                $table->string('scope', 20)->default('ALL'); // ALL | CATEGORY | PRODUCTS
            }
            if (!Schema::hasColumn('promotions', 'product_ids')) {
                $table->json('product_ids')->nullable();
            }
            if (!Schema::hasColumn('promotions', 'categories')) {
                $table->json('categories')->nullable();
            }
            if (!Schema::hasColumn('promotions', 'buy_qty')) {
                $table->unsignedInteger('buy_qty')->nullable();
            }
            if (!Schema::hasColumn('promotions', 'get_qty')) {
                $table->unsignedInteger('get_qty')->nullable();
            }
            if (!Schema::hasColumn('promotions', 'min_qty')) {
                $table->unsignedInteger('min_qty')->nullable();
            }
            if (!Schema::hasColumn('promotions', 'min_purchase')) {
                $table->decimal('min_purchase', 14, 2)->nullable();
            }
            if (!Schema::hasColumn('promotions', 'max_qty_per_trx')) {
                $table->unsignedInteger('max_qty_per_trx')->nullable();
            }
            if (!Schema::hasColumn('promotions', 'reward_product_id')) {
                $table->unsignedBigInteger('reward_product_id')->nullable();
            }
            if (!Schema::hasColumn('promotions', 'excluded_categories')) {
                $table->json('excluded_categories')->nullable();
            }
            if (!Schema::hasColumn('promotions', 'days_of_week')) {
                $table->json('days_of_week')->nullable(); // ISO 1 (Senin) .. 7 (Minggu)
            }
            if (!Schema::hasColumn('promotions', 'time_start')) {
                $table->string('time_start', 5)->nullable();
            }
            if (!Schema::hasColumn('promotions', 'time_end')) {
                $table->string('time_end', 5)->nullable();
            }
            if (!Schema::hasColumn('promotions', 'member_only')) {
                $table->boolean('member_only')->default(false);
            }
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            if (!Schema::hasColumn('transaction_items', 'promotion_id')) {
                $table->unsignedBigInteger('promotion_id')->nullable();
            }
            if (!Schema::hasColumn('transaction_items', 'promo_name')) {
                $table->string('promo_name')->nullable();
            }
            if (!Schema::hasColumn('transaction_items', 'promo_discount')) {
                $table->decimal('promo_discount', 14, 2)->default(0);
            }
        });

        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'promo_discount_total')) {
                $table->decimal('promo_discount_total', 14, 2)->default(0);
            }
            if (!Schema::hasColumn('transactions', 'promo_enabled')) {
                $table->boolean('promo_enabled')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            foreach (['scope', 'product_ids', 'categories', 'buy_qty', 'get_qty', 'min_qty', 'min_purchase',
                'max_qty_per_trx', 'reward_product_id', 'excluded_categories', 'days_of_week',
                'time_start', 'time_end', 'member_only'] as $col) {
                if (Schema::hasColumn('promotions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('transaction_items', function (Blueprint $table) {
            foreach (['promotion_id', 'promo_name', 'promo_discount'] as $col) {
                if (Schema::hasColumn('transaction_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('transactions', function (Blueprint $table) {
            foreach (['promo_discount_total', 'promo_enabled'] as $col) {
                if (Schema::hasColumn('transactions', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
