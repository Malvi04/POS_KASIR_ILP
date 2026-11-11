<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menambah kolom pada tabel stock_transfers / stock_transfer_items yang sudah ada
 * (hanya additive, data lama tidak berubah) untuk mendukung fitur Kirim Barang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('driver_name', 100)->nullable();
            $table->string('plate_number', 50)->nullable();
            $table->string('request_ref', 100)->nullable();
            $table->date('transfer_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('receiver_name', 100)->nullable();
        });

        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->string('condition', 50)->default('BAIK');
            $table->text('notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn(['condition', 'notes']);
        });
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn(['driver_name', 'plate_number', 'request_ref', 'transfer_date', 'notes', 'receiver_name']);
        });
    }
};
