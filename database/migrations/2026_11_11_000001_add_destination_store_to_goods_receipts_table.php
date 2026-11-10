<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('goods_receipts') && !Schema::hasColumn('goods_receipts', 'destination_store')) {
            Schema::table('goods_receipts', function (Blueprint $table) {
                $table->string('destination_store', 100)->nullable()->after('origin_source');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('goods_receipts') && Schema::hasColumn('goods_receipts', 'destination_store')) {
            Schema::table('goods_receipts', function (Blueprint $table) {
                $table->dropColumn('destination_store');
            });
        }
    }
};
