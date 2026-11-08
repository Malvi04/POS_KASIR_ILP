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
        Schema::table('coupon_units', function (Blueprint $table) {
            if (!Schema::hasColumn('coupon_units', 'category')) {
                $table->string('category', 50)->default('bbm')->after('product_id');
            }
            if (!Schema::hasColumn('coupon_units', 'recipient_name')) {
                $table->string('recipient_name', 150)->nullable()->after('serial_number');
            }
            if (!Schema::hasColumn('coupon_units', 'recipient_nik')) {
                $table->string('recipient_nik', 50)->nullable()->after('recipient_name');
            }
            if (!Schema::hasColumn('coupon_units', 'recipient_dept')) {
                $table->string('recipient_dept', 100)->nullable()->after('recipient_nik');
            }
            if (!Schema::hasColumn('coupon_units', 'vehicle_no')) {
                $table->string('vehicle_no', 50)->nullable()->after('recipient_dept');
            }
            if (!Schema::hasColumn('coupon_units', 'volume')) {
                $table->string('volume', 50)->nullable()->after('vehicle_no');
            }
            if (!Schema::hasColumn('coupon_units', 'notes')) {
                $table->text('notes')->nullable()->after('nominal_value');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coupon_units', function (Blueprint $table) {
            $table->dropColumn([
                'category',
                'recipient_name',
                'recipient_nik',
                'recipient_dept',
                'vehicle_no',
                'volume',
                'notes',
            ]);
        });
    }
};
