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
        // 1. Extend users table for staff, cashier, supervisor & manager
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->unique()->after('email');
            $table->string('nik')->nullable()->unique()->after('username');
            $table->string('role')->default('Kasir')->after('nik'); // Kasir, Supervisor, Manager, Admin
            $table->string('pin', 60)->default('123456')->after('role'); // Numeric 6 digit PIN
            $table->string('branch')->default('Pusat')->after('pin');
            $table->boolean('is_active')->default(true)->after('branch');
        });

        // 2. Cashier Shifts (Opening Cash & Audit Closing)
        Schema::create('cashier_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('shift_code')->unique()->index();
            $table->string('terminal_code')->index();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cashier_nik')->index();
            $table->string('cashier_name');
            $table->decimal('opening_cash', 14, 2); // Wajib diinput kasir sebelum transaksi pertama
            $table->decimal('cash_sales_total', 14, 2)->default(0);
            $table->decimal('qris_sales_total', 14, 2)->default(0);
            $table->decimal('credit_sales_total', 14, 2)->default(0);
            $table->decimal('total_sales', 14, 2)->default(0);
            $table->integer('transaction_count')->default(0);
            $table->decimal('cash_in', 14, 2)->default(0);
            $table->decimal('cash_out', 14, 2)->default(0);
            $table->decimal('expected_cash', 14, 2)->default(0);
            $table->decimal('actual_cash', 14, 2)->nullable(); // Audit closing uang fisik di laci
            $table->decimal('variance', 14, 2)->nullable(); // Selisih (+ lebih, - kurang)
            $table->string('variance_status')->nullable(); // SESUAI, KURANG, LEBIH
            $table->text('closing_notes')->nullable();
            $table->string('status')->default('OPEN')->index(); // OPEN, CLOSED
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['terminal_code', 'status']);
        });

        // 3. Products (Penjualan Cepat & Auto-Discount Expired)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique()->index();
            $table->string('barcode')->index();
            $table->string('name')->index();
            $table->string('category')->default('Umum')->index();
            $table->string('unit')->default('Pcs');
            $table->decimal('cost_price', 12, 2)->default(0);
            $table->decimal('price', 12, 2); // Harga umum
            $table->decimal('member_price', 12, 2)->nullable(); // Harga anggota
            $table->integer('stock')->default(0)->index();
            $table->integer('min_stock')->default(5);
            $table->boolean('is_taxable')->default(true); // Status pajak barang
            $table->decimal('tax_rate', 5, 2)->default(11.00); // Default PPN 11%
            $table->date('expired_date')->nullable()->index(); // Untuk warning H-14
            $table->decimal('clearance_discount_percent', 5, 2)->default(20.00); // Diskon otomatis clearance
            $table->string('status')->default('TERSEDIA'); // TERSEDIA, STOK_RENDAH, NEAR_EXPIRED, HABIS
            $table->timestamps();
        });

        // 4. Members (Kredit Anggota / Potong Gaji)
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_code')->unique()->index();
            $table->string('nik')->unique()->index(); // Validasi NIK
            $table->string('name')->index();
            $table->string('department')->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0); // Plafon kredit
            $table->decimal('current_debt', 14, 2)->default(0); // Kasbon/piutang berjalan
            $table->string('status')->default('AKTIF'); // AKTIF, DIBLOKIR
            $table->timestamps();
        });

        // 5. Transactions
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique()->index();
            $table->foreignId('shift_id')->constrained('cashier_shifts')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cashier_name');
            $table->string('terminal_code')->index();
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('member_nik')->nullable()->index();
            $table->string('member_name')->nullable();
            $table->string('payment_method')->index(); // TUNAI, QRIS, TRANSFER, KREDIT, CAMPURAN
            $table->decimal('subtotal', 14, 2);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2);
            $table->decimal('cash_received', 14, 2)->default(0);
            $table->decimal('change_returned', 14, 2)->default(0);
            $table->string('payment_reference')->nullable(); // Nomor referensi QRIS / transfer
            $table->decimal('split_cash', 14, 2)->default(0);
            $table->decimal('split_non_cash', 14, 2)->default(0);
            $table->string('status')->default('SUCCESS')->index(); // SUCCESS, VOIDED
            $table->timestamp('voided_at')->nullable();
            $table->string('voided_by')->nullable();
            $table->text('void_reason')->nullable();
            $table->timestamps();

            $table->index(['shift_id', 'status']);
        });

        // 6. Transaction Items
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('sku');
            $table->string('barcode');
            $table->string('product_name');
            $table->string('unit')->default('Pcs');
            $table->decimal('unit_price', 12, 2);
            $table->integer('qty');
            $table->boolean('is_taxable')->default(true);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->boolean('is_expired_clearance')->default(false);
            $table->decimal('clearance_discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_price', 12, 2);
            $table->timestamps();
        });

        // 7. Held Bills (Pending Transaksi / Hold Bill / Recall)
        Schema::create('held_bills', function (Blueprint $table) {
            $table->id();
            $table->string('hold_code')->unique()->index();
            $table->foreignId('shift_id')->constrained('cashier_shifts')->cascadeOnDelete();
            $table->string('terminal_code')->index();
            $table->string('customer_name');
            $table->foreignId('member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('member_nik')->nullable();
            $table->json('items_payload'); // Array keranjang belanja
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('grand_total', 14, 2);
            $table->integer('total_items')->default(0);
            $table->string('note')->nullable();
            $table->string('status')->default('HELD')->index(); // HELD, RECALLED, CANCELLED
            $table->timestamp('held_at');
            $table->timestamp('recalled_at')->nullable();
            $table->timestamps();

            $table->index(['shift_id', 'status']);
        });

        // 8. Void Logs (Anti-Fraud Control & Supervisor Authorization)
        Schema::create('void_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->foreignId('shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
            $table->string('action_type'); // VOID_ITEM, VOID_TRANSACTION
            $table->string('supervisor_nik');
            $table->string('supervisor_name');
            $table->string('cashier_name');
            $table->string('item_sku')->nullable();
            $table->string('item_name')->nullable();
            $table->integer('item_qty')->nullable();
            $table->decimal('amount_voided', 14, 2)->default(0);
            $table->text('reason');
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('void_logs');
        Schema::dropIfExists('held_bills');
        Schema::dropIfExists('transaction_items');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('members');
        Schema::dropIfExists('products');
        Schema::dropIfExists('cashier_shifts');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'nik', 'role', 'pin', 'branch', 'is_active']);
        });
    }
};
