<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create `pos_terminals` table for per-terminal SHA-256 token auth & branch authority
        if (!Schema::hasTable('pos_terminals')) {
            Schema::create('pos_terminals', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index('pos_terminals_uuid_idx');
                $table->foreignId('branch_id')->constrained('locations')->cascadeOnDelete();
                $table->string('terminal_code', 30);
                $table->string('name', 120);
                $table->string('api_token_hash', 64)->unique('pos_terminals_api_token_hash_unique');
                $table->boolean('is_active')->default(true)->index('pos_terminals_is_active_idx');
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_push_at')->nullable();
                $table->timestamp('last_pull_at')->nullable();
                $table->timestamps();

                $table->unique(['branch_id', 'terminal_code'], 'pos_terminals_branch_terminal_unique');
            });
        }

        // 2. Non-destructively enhance `transactions` for nullable terminal_code & branch+terminal indexing
        if (Schema::hasTable('transactions')) {
            if (!Schema::hasColumn('transactions', 'terminal_code')) {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->string('terminal_code', 50)->nullable()->index('transactions_terminal_code_idx');
                });
            } else {
                if (DB::getDriverName() === 'pgsql') {
                    DB::statement('ALTER TABLE "transactions" ALTER COLUMN "terminal_code" DROP NOT NULL');
                } else {
                    Schema::table('transactions', function (Blueprint $table) {
                        $table->string('terminal_code', 50)->nullable()->change();
                    });
                }
            }

            if (!Schema::hasColumn('transactions', 'location_id')) {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->foreignId('location_id')->nullable()->after('terminal_code')->constrained('locations')->nullOnDelete();
                });
            }

            // Add composite index (location_id, terminal_code) if not already present
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('CREATE INDEX IF NOT EXISTS "transactions_location_terminal_idx" ON "transactions" ("location_id", "terminal_code")');
            } else {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->index(['location_id', 'terminal_code'], 'transactions_location_terminal_idx');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('transactions')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('DROP INDEX IF EXISTS "transactions_location_terminal_idx"');
            } else {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->dropIndex('transactions_location_terminal_idx');
                });
            }
        }

        Schema::dropIfExists('pos_terminals');
    }
};
