<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Additional reference master tables receiving Dual-Key (`id` BIGINT + `uuid` PostgreSQL native UUIDv7).
     *
     * @var array<int, string>
     */
    protected array $additionalUuidTables = [
        'units',
        'suppliers',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create `sync_states` table for persistent local pull cursor tracking per entity
        if (!Schema::hasTable('sync_states')) {
            Schema::create('sync_states', function (Blueprint $table) {
                $table->id();
                $table->string('entity', 80)->unique();
                $table->timestamp('last_synced_at')->nullable()->index();
                $table->string('last_status', 30)->default('idle');
                $table->text('last_error')->nullable();
                $table->timestamps();
            });
        }

        // 2. Add Dual-Key `uuid` with 3-step safety backfill to `units` and `suppliers`
        foreach ($this->additionalUuidTables as $tableName) {
            if (!Schema::hasTable($tableName)) {
                continue;
            }

            if (!Schema::hasColumn($tableName, 'uuid')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->uuid('uuid')->nullable()->after('id');
                });
            }

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

        // 3. Enhance `products` with non-destructive `category_id` FK and `deleted_at` (SoftDeletes)
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (!Schema::hasColumn('products', 'category_id')) {
                    $table->foreignId('category_id')
                        ->nullable()
                        ->after('category')
                        ->constrained('product_categories')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('products', 'deleted_at')) {
                    $table->softDeletes();
                }
            });

            // Backfill `products.category_id` from existing `products.category` string if categories exist
            if (Schema::hasTable('product_categories')) {
                $categoriesByName = DB::table('product_categories')->pluck('id', 'name');
                foreach ($categoriesByName as $catName => $catId) {
                    DB::table('products')
                        ->whereNull('category_id')
                        ->where('category', $catName)
                        ->update(['category_id' => $catId]);
                }
            }
        }

        // 4. Enhance `users` with non-destructive `location_id` FK and `deleted_at`
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (!Schema::hasColumn('users', 'location_id')) {
                    $table->foreignId('location_id')
                        ->nullable()
                        ->after('branch')
                        ->constrained('locations')
                        ->nullOnDelete();
                }
                if (!Schema::hasColumn('users', 'deleted_at')) {
                    $table->softDeletes();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'location_id')) {
                    $table->dropForeign(['location_id']);
                    $table->dropColumn('location_id');
                }
                if (Schema::hasColumn('users', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (Schema::hasColumn('products', 'category_id')) {
                    $table->dropForeign(['category_id']);
                    $table->dropColumn('category_id');
                }
                if (Schema::hasColumn('products', 'deleted_at')) {
                    $table->dropSoftDeletes();
                }
            });
        }

        foreach (array_reverse($this->additionalUuidTables) as $tableName) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'uuid')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                $table->dropUnique("{$tableName}_uuid_unique");
                $table->dropIndex("{$tableName}_uuid_idx");
                $table->dropColumn('uuid');
            });
        }

        Schema::dropIfExists('sync_states');
    }
};
