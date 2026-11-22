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
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'is_over_limit')) {
                $table->boolean('is_over_limit')->default(false)->after('status');
            }
            if (!Schema::hasColumn('transactions', 'debt_status')) {
                $table->string('debt_status', 30)->nullable()->after('is_over_limit');
            }
        });

        Schema::table('debt_mutations', function (Blueprint $table) {
            if (!Schema::hasColumn('debt_mutations', 'is_over_limit')) {
                $table->boolean('is_over_limit')->default(false)->after('new_limit');
            }
            if (!Schema::hasColumn('debt_mutations', 'debt_status')) {
                $table->string('debt_status', 30)->nullable()->after('is_over_limit');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'is_over_limit')) {
                $table->dropColumn('is_over_limit');
            }
            if (Schema::hasColumn('transactions', 'debt_status')) {
                $table->dropColumn('debt_status');
            }
        });

        Schema::table('debt_mutations', function (Blueprint $table) {
            if (Schema::hasColumn('debt_mutations', 'is_over_limit')) {
                $table->dropColumn('is_over_limit');
            }
            if (Schema::hasColumn('debt_mutations', 'debt_status')) {
                $table->dropColumn('debt_status');
            }
        });
    }
};
