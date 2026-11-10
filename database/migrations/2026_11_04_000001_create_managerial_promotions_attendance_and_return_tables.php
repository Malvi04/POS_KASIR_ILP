<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Enhance store_return_headers if columns don't exist
        if (Schema::hasTable('store_return_headers')) {
            Schema::table('store_return_headers', function (Blueprint $table) {
                if (!Schema::hasColumn('store_return_headers', 'direction')) {
                    $table->string('direction', 50)->default('toko_ke_gudang');
                }
                if (!Schema::hasColumn('store_return_headers', 'condition_type')) {
                    $table->string('condition_type', 50)->default('rusak');
                }
                if (!Schema::hasColumn('store_return_headers', 'notes')) {
                    $table->text('notes')->nullable();
                }
                if (!Schema::hasColumn('store_return_headers', 'driver_name')) {
                    $table->string('driver_name', 100)->nullable();
                }
                if (!Schema::hasColumn('store_return_headers', 'vehicle_plate')) {
                    $table->string('vehicle_plate', 30)->nullable();
                }
                if (!Schema::hasColumn('store_return_headers', 'subtotal')) {
                    $table->decimal('subtotal', 15, 2)->default(0);
                }
                if (!Schema::hasColumn('store_return_headers', 'truck_fee')) {
                    $table->decimal('truck_fee', 15, 2)->default(0);
                }
                if (!Schema::hasColumn('store_return_headers', 'net_amount')) {
                    $table->decimal('net_amount', 15, 2)->default(0);
                }
            });
        }

        // 2. Promotions table
        if (!Schema::hasTable('promotions')) {
            Schema::create('promotions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name');
                $table->string('condition')->nullable();
                $table->string('type', 30)->default('PERCENTAGE'); // PERCENTAGE, FIXED, BOGO
                $table->decimal('value', 12, 2)->default(0);
                $table->string('badge_class')->nullable();
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->string('status', 30)->default('AKTIF'); // AKTIF, MENDATANG, NONAKTIF
                $table->string('status_color', 30)->default('green');
                $table->timestamps();
            });
        }

        // 3. Attendances table
        if (!Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->id();
                $table->string('staff_name');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('terminal', 50)->default('Kasir 01');
                $table->string('branch', 100)->default('Cabang Pusat');
                $table->date('date');
                $table->string('check_in', 20)->nullable();
                $table->string('check_out', 20)->nullable();
                $table->string('status', 50)->default('TEPAT_WAKTU'); // TEPAT_WAKTU, TERLAMBAT, PULANG_CEPAT
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 4. Leave requests table
        if (!Schema::hasTable('leave_requests')) {
            Schema::create('leave_requests', function (Blueprint $table) {
                $table->id();
                $table->string('staff_name');
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('type', 30)->default('IZIN'); // SAKIT, CUTI, IZIN
                $table->string('date_range', 100);
                $table->text('reason')->nullable();
                $table->string('status', 30)->default('MENUNGGU'); // MENUNGGU, DISETUJUI, DITOLAK
                $table->string('approved_by', 100)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('promotions');

        if (Schema::hasTable('store_return_headers')) {
            Schema::table('store_return_headers', function (Blueprint $table) {
                $table->dropColumn([
                    'direction',
                    'condition_type',
                    'notes',
                    'driver_name',
                    'vehicle_plate',
                    'subtotal',
                    'truck_fee',
                    'net_amount'
                ]);
            });
        }
    }
};
