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
        // 1. Extend members table for Multi-Cabang, Phone, and Joining information
        Schema::table('members', function (Blueprint $table) {
            $table->string('branch')->default('Cabang 1 - Mill / Pabrik')->after('department')->index();
            $table->string('phone')->nullable()->after('branch');
            $table->date('join_date')->nullable()->after('phone');
            $table->text('notes')->nullable()->after('status');

            // Composite indexes for fast multi-branch search & filtering
            $table->index(['branch', 'status']);
            $table->index(['department', 'status']);
        });

        // 2. Member Debt Mutations & Centralized Kasbon Audit Trail
        Schema::create('debt_mutations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('member_id')->constrained('members')->cascadeOnDelete();
            $table->string('member_nik')->index();
            $table->foreignId('transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->string('invoice_number')->nullable()->index();
            $table->string('branch')->index(); // Cabang tempat transaksi terjadi
            $table->string('mutation_type')->index(); // KASBON_POS, SETTLEMENT, PAYROLL_DEDUCTION, LIMIT_ADJUSTMENT
            $table->decimal('amount', 14, 2); // Nominal transaksi kasbon atau pelunasan
            $table->decimal('previous_debt', 14, 2);
            $table->decimal('new_debt', 14, 2);
            $table->decimal('previous_limit', 14, 2);
            $table->decimal('new_limit', 14, 2);
            $table->text('notes')->nullable();
            $table->string('processed_by')->nullable(); // Nama Kasir / Admin / HRD
            $table->timestamps();

            // High-performance composite indexes for monthly reporting & member statement
            $table->index(['member_id', 'created_at']);
            $table->index(['branch', 'created_at']);
            $table->index(['mutation_type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_mutations');

        Schema::table('members', function (Blueprint $table) {
            $table->dropIndex(['branch', 'status']);
            $table->dropIndex(['department', 'status']);
            $table->dropIndex(['branch']);
            $table->dropColumn(['branch', 'phone', 'join_date', 'notes']);
        });
    }
};
