<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Tables receiving Dual-Key (`id` BIGINT + `uuid` PostgreSQL native UUIDv7).
     *
     * @var array<int, string>
     */
    protected array $uuidTables = [
        'users',
        'products',
        'members',
        'locations',
        'product_categories',
        'cashier_shifts',
        'transactions',
        'transaction_items',
    ];

    /**
     * Operational tables generated on local cashier nodes that track background sync state.
     *
     * @var array<int, string>
     */
    protected array $syncableTables = [
        'cashier_shifts',
        'transactions',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->uuidTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            // Step 1: Add `uuid` column as nullable first (non-destructive for populated tables)
            if (!Schema::hasColumn($tableName, 'uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('uuid')->nullable()->after('id');
                });
            }

            // Step 2: Safety backfill existing records with time-ordered UUIDv7
            DB::table($tableName)
                ->whereNull('uuid')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($tableName) {
                    DB::transaction(function () use ($rows, $tableName) {
                        foreach ($rows as $row) {
                            DB::table($tableName)
                                ->where('id', $row->id)
                                ->update(['uuid' => (string) Str::uuid7()]);
                        }
                    });
                });

            // Step 3: Enforce NOT NULL and add UNIQUE + INDEX constraint
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE \"{$tableName}\" ALTER COLUMN \"uuid\" SET NOT NULL");
            } else {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('uuid')->nullable(false)->change();
                });
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->unique('uuid', "{$tableName}_uuid_unique");
                $table->index('uuid', "{$tableName}_uuid_idx");
            });
        }

        // Step 4: Add `synced_at` and `sync_error` columns to operational tables
        foreach ($this->syncableTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'synced_at')) {
                    $table->timestamp('synced_at')->nullable()->index("{$tableName}_synced_at_idx");
                }
                if (!Schema::hasColumn($tableName, 'sync_error')) {
                    $table->text('sync_error')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->syncableTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'synced_at')) {
                    $table->dropIndex("{$tableName}_synced_at_idx");
                    $table->dropColumn('synced_at');
                }
                if (Schema::hasColumn($tableName, 'sync_error')) {
                    $table->dropColumn('sync_error');
                }
            });
        }

        foreach (array_reverse($this->uuidTables) as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'uuid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropUnique("{$tableName}_uuid_unique");
                $table->dropIndex("{$tableName}_uuid_idx");
                $table->dropColumn('uuid');
            });
        }
    }
};
