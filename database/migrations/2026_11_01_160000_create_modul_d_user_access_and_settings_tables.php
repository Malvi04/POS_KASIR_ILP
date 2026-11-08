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
        // 1. Table for System & Tax Settings (PPN, Store Config, Security)
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index();
            $table->text('value')->nullable();
            $table->string('group')->default('general')->index(); // general, tax, security, store
            $table->timestamps();
        });

        // 2. Table for Daily Stock Corrections by Supervisor / Manager
        Schema::create('stock_corrections', function (Blueprint $table) {
            $table->id();
            $table->string('correction_code')->unique()->index();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('branch')->default('Pusat')->index();
            $table->string('type')->default('SUBTRACT'); // ADD, SUBTRACT, ADJUST_TO
            $table->integer('qty');
            $table->integer('stock_before');
            $table->integer('stock_after');
            $table->string('reason'); // Kerusakan, Kadaluwarsa, Selisih Harian, Koreksi Fisik
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('supervisor_name');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_corrections');
        Schema::dropIfExists('app_settings');
    }
};
