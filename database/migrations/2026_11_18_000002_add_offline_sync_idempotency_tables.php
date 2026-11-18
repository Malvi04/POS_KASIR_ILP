<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transactions') && !Schema::hasColumn('transactions', 'offline_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->string('offline_id', 120)->nullable()->unique()->after('invoice_number');
            });
        }

        if (Schema::hasTable('cashier_shifts') && !Schema::hasColumn('cashier_shifts', 'offline_id')) {
            Schema::table('cashier_shifts', function (Blueprint $table) {
                $table->string('offline_id', 120)->nullable()->unique()->after('shift_code');
            });
        }

        if (!Schema::hasTable('cash_movements')) {
            Schema::create('cash_movements', function (Blueprint $table) {
                $table->id();
                $table->string('offline_id', 120)->nullable()->unique();
                $table->foreignId('shift_id')->constrained('cashier_shifts')->cascadeOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->string('terminal_code', 50)->index();
                $table->string('type', 30);
                $table->decimal('amount', 15, 2);
                $table->string('reason', 255)->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('cashier_nik', 50)->nullable();
                $table->timestamp('occurred_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');

        if (Schema::hasTable('cashier_shifts') && Schema::hasColumn('cashier_shifts', 'offline_id')) {
            Schema::table('cashier_shifts', function (Blueprint $table) {
                $table->dropUnique(['offline_id']);
                $table->dropColumn('offline_id');
            });
        }

        if (Schema::hasTable('transactions') && Schema::hasColumn('transactions', 'offline_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropUnique(['offline_id']);
                $table->dropColumn('offline_id');
            });
        }
    }
};
