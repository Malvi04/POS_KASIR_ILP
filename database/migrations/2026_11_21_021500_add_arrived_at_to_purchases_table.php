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
        if (Schema::hasTable('purchases') && !Schema::hasColumn('purchases', 'arrived_at')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dateTime('arrived_at')->nullable()->after('purchase_date')->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchases') && Schema::hasColumn('purchases', 'arrived_at')) {
            Schema::table('purchases', function (Blueprint $table) {
                $table->dropIndex(['arrived_at']);
                $table->dropColumn('arrived_at');
            });
        }
    }
};
