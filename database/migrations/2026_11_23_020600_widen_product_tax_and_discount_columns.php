<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE products ALTER COLUMN tax_rate TYPE NUMERIC(12, 2)");
        DB::statement("ALTER TABLE products ALTER COLUMN clearance_discount_percent TYPE NUMERIC(12, 2)");
        DB::statement("ALTER TABLE products ALTER COLUMN commission_percent TYPE NUMERIC(12, 2)");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE products ALTER COLUMN tax_rate TYPE NUMERIC(5, 2)");
        DB::statement("ALTER TABLE products ALTER COLUMN clearance_discount_percent TYPE NUMERIC(5, 2)");
        DB::statement("ALTER TABLE products ALTER COLUMN commission_percent TYPE NUMERIC(5, 2)");
    }
};
