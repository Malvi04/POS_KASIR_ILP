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
        if (Schema::hasTable('store_return_headers')) {
            Schema::table('store_return_headers', function (Blueprint $table) {
                if (!Schema::hasColumn('store_return_headers', 'cashier_username')) {
                    $table->string('cashier_username', 100)->nullable()->after('created_by');
                }
                if (!Schema::hasColumn('store_return_headers', 'branch')) {
                    $table->string('branch', 100)->nullable()->after('from_location_id');
                }
                if (!Schema::hasColumn('store_return_headers', 'terminal_code')) {
                    $table->string('terminal_code', 50)->nullable()->after('cashier_username');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('store_return_headers')) {
            Schema::table('store_return_headers', function (Blueprint $table) {
                if (Schema::hasColumn('store_return_headers', 'terminal_code')) {
                    $table->dropColumn('terminal_code');
                }
                if (Schema::hasColumn('store_return_headers', 'branch')) {
                    $table->dropColumn('branch');
                }
                if (Schema::hasColumn('store_return_headers', 'cashier_username')) {
                    $table->dropColumn('cashier_username');
                }
            });
        }
    }
};
