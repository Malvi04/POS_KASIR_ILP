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
        if (Schema::hasTable('transaction_items')) {
            Schema::table('transaction_items', function (Blueprint $table) {
                if (!Schema::hasColumn('transaction_items', 'coupon_number')) {
                    $table->string('coupon_number', 100)->nullable()->after('product_name')->index();
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
                if (Schema::hasColumn('transaction_items', 'coupon_number')) {
                    $table->dropColumn('coupon_number');
                }
            });
        }
    }
};
