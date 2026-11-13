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
        Schema::table('locations', function (Blueprint $table) {
            if (!Schema::hasColumn('locations', 'address')) {
                $table->string('address', 255)->nullable()->after('name');
            }
            if (!Schema::hasColumn('locations', 'manager')) {
                $table->string('manager', 100)->nullable()->after('address');
            }
            if (!Schema::hasColumn('locations', 'manager_email')) {
                $table->string('manager_email', 150)->nullable()->after('manager');
            }
            if (!Schema::hasColumn('locations', 'phone')) {
                $table->string('phone', 50)->nullable()->after('manager_email');
            }
            if (!Schema::hasColumn('locations', 'status')) {
                $table->string('status', 50)->default('AKTIF')->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['address', 'manager', 'manager_email', 'phone', 'status']);
        });
    }
};
