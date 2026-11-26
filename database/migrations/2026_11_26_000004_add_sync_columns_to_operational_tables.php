<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Non-destructively adds Dual-Key UUIDv7 (`uuid`) and sync tracking (`synced_at`, `sync_error`)
     * to operational tables (`attendances`, `audit_logs`, `goods_receipts`, `goods_receipt_items`).
     */
    public function up(): void
    {
        $tablesWithSyncState = ['attendances', 'audit_logs', 'goods_receipts'];

        foreach ($tablesWithSyncState as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('uuid')->nullable();
                });

                DB::table($tableName)->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) use ($tableName) {
                    foreach ($rows as $row) {
                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update(['uuid' => (string) Str::uuid7()]);
                    }
                });

                if (DB::getDriverName() === 'pgsql') {
                    DB::statement("ALTER TABLE \"{$tableName}\" ALTER COLUMN \"uuid\" SET NOT NULL");
                    DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS \"{$tableName}_uuid_unique\" ON \"{$tableName}\" (\"uuid\")");
                } else {
                    Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                        $table->uuid('uuid')->nullable(false)->unique("{$tableName}_uuid_unique")->change();
                    });
                }
            }

            if (!Schema::hasColumn($tableName, 'synced_at')) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $table->timestamp('synced_at')->nullable()->index("{$tableName}_synced_at_idx");
                });
            }

            if (!Schema::hasColumn($tableName, 'sync_error')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->text('sync_error')->nullable();
                });
            }
        }

        if (Schema::hasTable('goods_receipt_items') && !Schema::hasColumn('goods_receipt_items', 'uuid')) {
            Schema::table('goods_receipt_items', function (Blueprint $table) {
                $table->uuid('uuid')->nullable();
            });

            DB::table('goods_receipt_items')->whereNull('uuid')->orderBy('id')->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('goods_receipt_items')
                        ->where('id', $row->id)
                        ->update(['uuid' => (string) Str::uuid7()]);
                }
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement('ALTER TABLE "goods_receipt_items" ALTER COLUMN "uuid" SET NOT NULL');
                DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS "goods_receipt_items_uuid_unique" ON "goods_receipt_items" ("uuid")');
            } else {
                Schema::table('goods_receipt_items', function (Blueprint $table) {
                    $table->uuid('uuid')->nullable(false)->unique('goods_receipt_items_uuid_unique')->change();
                });
            }
        }

        // Ensure PostgreSQL tables with `uuid` have `DEFAULT gen_random_uuid()` fallback for raw bulk `insert()` calls
        // (Eloquent `Model::create()` still supplies chronological UUIDv7 via HasUuidV7 trait).
        if (DB::getDriverName() === 'pgsql') {
            $uuidTables = [
                'products',
                'product_categories',
                'locations',
                'suppliers',
                'users',
                'transactions',
                'transaction_items',
                'cashier_shifts',
                'pos_terminals',
                'attendances',
                'audit_logs',
                'goods_receipts',
                'goods_receipt_items',
            ];
            foreach ($uuidTables as $tbl) {
                if (Schema::hasTable($tbl) && Schema::hasColumn($tbl, 'uuid')) {
                    DB::statement("ALTER TABLE \"{$tbl}\" ALTER COLUMN \"uuid\" SET DEFAULT gen_random_uuid()");
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('goods_receipt_items') && Schema::hasColumn('goods_receipt_items', 'uuid')) {
            Schema::table('goods_receipt_items', function (Blueprint $table) {
                $table->dropColumn('uuid');
            });
        }

        foreach (['goods_receipts', 'audit_logs', 'attendances'] as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }
            $cols = array_filter(['uuid', 'synced_at', 'sync_error'], fn ($c) => Schema::hasColumn($tableName, $c));
            if (!empty($cols)) {
                Schema::table($tableName, function (Blueprint $table) use ($cols) {
                    $table->dropColumn($cols);
                });
            }
        }
    }
};
