<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for full ERD alignment.
     */
    public function up(): void
    {
        // 1. Locations & User Location Access (ERD Master Data)
        if (!Schema::hasTable('locations')) {
            Schema::create('locations', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('name', 150);
                $table->string('type', 50)->default('STORE'); // STORE, WAREHOUSE, FACTORY, PLANTATION
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('user_location_access')) {
            Schema::create('user_location_access', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->unique(['user_id', 'location_id']);
                $table->timestamps();
            });
        }

        // 2. Product Categories & Units (ERD Master Data)
        if (!Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table) {
                $table->id();
                $table->string('name', 100)->unique();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('units')) {
            Schema::create('units', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50)->unique();
                $table->string('symbol', 20)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('product_barcodes')) {
            Schema::create('product_barcodes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->string('barcode', 100)->unique();
                $table->timestamps();
            });
        }

        // 3. Purchases & Purchase Items (ERD Pembelian & Penerimaan Barang)
        if (!Schema::hasTable('purchases')) {
            Schema::create('purchases', function (Blueprint $table) {
                $table->id();
                $table->string('purchase_number', 100)->unique();
                $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('purchase_date');
                $table->string('status', 50)->default('PENDING'); // PENDING, ORDERED, RECEIVED, CANCELLED
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('purchase_items')) {
            Schema::create('purchase_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('purchase_id')->constrained('purchases')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('qty', 12, 2)->default(1);
                $table->decimal('purchase_price', 15, 2)->default(0);
                $table->string('ownership_type', 50)->default('OUTRIGHT'); // OUTRIGHT, CONSIGNMENT
                $table->timestamps();
            });
        }

        // 4. Goods Receipts & Receipt Items (ERD Pembelian & Penerimaan Barang)
        if (!Schema::hasTable('goods_receipts')) {
            Schema::create('goods_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('receipt_number', 100)->unique();
                $table->foreignId('purchase_id')->nullable()->constrained('purchases')->nullOnDelete();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('received_at');
                $table->string('driver_name', 100)->nullable();
                $table->string('plate_number', 50)->nullable();
                $table->string('origin_source', 150)->nullable();
                $table->string('status', 50)->default('MENUNGGU_VERIFIKASI'); // MENUNGGU_VERIFIKASI, DITERIMA_LENGKAP, DITERIMA_SELISIH
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('goods_receipt_items')) {
            Schema::create('goods_receipt_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
                $table->foreignId('purchase_item_id')->nullable()->constrained('purchase_items')->nullOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('qty_sent', 12, 2)->default(0);
                $table->decimal('qty_received', 12, 2)->default(0);
                $table->decimal('actual_unit_cost', 15, 2)->default(0);
                $table->string('condition', 50)->default('BAIK'); // BAIK, RUSAK, KEMASAN_RUSAK
                $table->string('ownership_type', 50)->default('OUTRIGHT');
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // 5. Stock Requests & Stock Transfers antar Cabang / DC (ERD Transfer Barang)
        if (!Schema::hasTable('stock_requests')) {
            Schema::create('stock_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_number', 100)->unique();
                $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('requested_at');
                $table->string('status', 50)->default('PENDING'); // PENDING, APPROVED, SHIPPED, COMPLETED, REJECTED
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('stock_request_items')) {
            Schema::create('stock_request_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('request_id')->constrained('stock_requests')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('qty_requested', 12, 2);
                $table->decimal('qty_approved', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('stock_transfers')) {
            Schema::create('stock_transfers', function (Blueprint $table) {
                $table->id();
                $table->string('transfer_number', 100)->unique();
                $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('request_id')->nullable()->constrained('stock_requests')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('shipped_at')->nullable();
                $table->dateTime('received_at')->nullable();
                $table->string('status', 50)->default('IN_TRANSIT'); // IN_TRANSIT, RECEIVED, WITH_DISCREPANCY
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('stock_transfer_items')) {
            Schema::create('stock_transfer_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('qty_sent', 12, 2);
                $table->decimal('qty_received', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('transfer_discrepancies')) {
            Schema::create('transfer_discrepancies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('transfer_item_id')->constrained('stock_transfer_items')->cascadeOnDelete();
                $table->decimal('qty_difference', 12, 2);
                $table->string('reason', 255)->nullable();
                $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        // 6. Retur Barang (Sales Returns, Store Returns, Supplier Returns)
        if (!Schema::hasTable('sales_returns')) {
            Schema::create('sales_returns', function (Blueprint $table) {
                $table->id();
                $table->string('return_number', 100)->unique();
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('returned_at');
                $table->string('reason', 255)->nullable();
                $table->string('status', 50)->default('COMPLETED');
                $table->decimal('total_refund', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('sales_return_items')) {
            Schema::create('sales_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
                $table->foreignId('transaction_item_id')->nullable()->constrained('transaction_items')->nullOnDelete();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->decimal('qty', 12, 2);
                $table->decimal('refund_amount', 15, 2);
                $table->string('disposition', 50)->default('RETURN_TO_STOCK'); // RETURN_TO_STOCK, SCRAP, CLAIM_SUPPLIER
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('store_return_headers')) {
            Schema::create('store_return_headers', function (Blueprint $table) {
                $table->id();
                $table->string('return_number', 100)->unique();
                $table->foreignId('from_location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('to_location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('returned_at');
                $table->string('status', 50)->default('DRAFT'); // DRAFT, DIKIRIM_KE_GUDANG, SELESAI
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('store_return_items')) {
            Schema::create('store_return_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('store_return_id')->constrained('store_return_headers')->cascadeOnDelete();
                $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
                $table->decimal('qty', 12, 2);
                $table->string('disposition', 50)->default('RUSAK_BOCOR'); // RUSAK_BOCOR, KEDALUWARSA, OVERSTOCK
                $table->decimal('claim_price', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        // 7. Keuangan & Jurnal (Chart of Accounts, Journal Entries, Cash Handovers)
        if (!Schema::hasTable('chart_of_accounts')) {
            Schema::create('chart_of_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('account_code', 50)->unique();
                $table->string('account_name', 150);
                $table->string('account_type', 50); // ASSET, LIABILITY, EQUITY, REVENUE, EXPENSE
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('journal_entries')) {
            Schema::create('journal_entries', function (Blueprint $table) {
                $table->id();
                $table->string('journal_number', 100)->unique();
                $table->dateTime('journal_date');
                $table->string('source_type', 50); // POS_SALE, CASH_HANDOVER, CONSIGNMENT_SETTLEMENT, PURCHASE
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 50)->default('POSTED');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('journal_entry_lines')) {
            Schema::create('journal_entry_lines', function (Blueprint $table) {
                $table->id();
                $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
                $table->foreignId('account_id')->constrained('chart_of_accounts')->cascadeOnDelete();
                $table->decimal('debit', 15, 2)->default(0);
                $table->decimal('credit', 15, 2)->default(0);
                $table->string('description', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('cash_handover_headers')) {
            Schema::create('cash_handover_headers', function (Blueprint $table) {
                $table->id();
                $table->string('handover_number', 100)->unique();
                $table->foreignId('cashier_shift_id')->nullable()->constrained('cashier_shifts')->nullOnDelete();
                $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('handover_at');
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->string('status', 50)->default('CONFIRMED');
                $table->timestamps();
            });
        }

        // 8. Kupon BBM (ERD Kupon BBM)
        if (!Schema::hasTable('coupon_units')) {
            Schema::create('coupon_units', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
                $table->string('serial_number', 100)->unique();
                $table->string('status', 50)->default('AVAILABLE'); // AVAILABLE, ISSUED, REDEEMED, EXPIRED
                $table->decimal('nominal_value', 15, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('coupon_redemptions')) {
            Schema::create('coupon_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('coupon_unit_id')->constrained('coupon_units')->cascadeOnDelete();
                $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
                $table->dateTime('redeemed_at');
                $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 50)->default('COMPLETED');
                $table->timestamps();
            });
        }

        // 9. Operasional Toko & Audit Sequences (ERD Operasional Toko)
        if (!Schema::hasTable('store_operations')) {
            Schema::create('store_operations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
                $table->boolean('is_enabled')->default(true);
                $table->string('reason', 255)->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('updated_at_store')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('document_sequences')) {
            Schema::create('document_sequences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
                $table->string('document_type', 50); // TRX, PO, SJ, RTR, CM
                $table->string('period_key', 20); // YYYYMM
                $table->bigInteger('last_number')->default(0);
                $table->unique(['location_id', 'document_type', 'period_key']);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
        Schema::dropIfExists('store_operations');
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupon_units');
        Schema::dropIfExists('cash_handover_headers');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('chart_of_accounts');
        Schema::dropIfExists('store_return_items');
        Schema::dropIfExists('store_return_headers');
        Schema::dropIfExists('sales_return_items');
        Schema::dropIfExists('sales_returns');
        Schema::dropIfExists('transfer_discrepancies');
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
        Schema::dropIfExists('stock_request_items');
        Schema::dropIfExists('stock_requests');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_items');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('product_barcodes');
        Schema::dropIfExists('units');
        Schema::dropIfExists('product_categories');
        Schema::dropIfExists('user_location_access');
        Schema::dropIfExists('locations');
    }
};
