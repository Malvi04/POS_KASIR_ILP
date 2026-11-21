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
        Schema::table('members', function (Blueprint $table) {
            if (!Schema::hasColumn('members', 'customer_id')) {
                $table->string('customer_id', 60)->nullable()->after('id');
            }
            if (!Schema::hasColumn('members', 'kode_out')) {
                $table->string('kode_out', 60)->nullable()->after('member_code');
            }
            if (!Schema::hasColumn('members', 'division')) {
                $table->string('division', 150)->nullable()->after('department');
            }
            if (!Schema::hasColumn('members', 'address')) {
                $table->text('address')->nullable()->after('division');
            }
            if (!Schema::hasColumn('members', 'fax')) {
                $table->string('fax', 100)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('members', 'contact')) {
                $table->string('contact', 150)->nullable()->after('fax');
            }
            if (!Schema::hasColumn('members', 'tempo_bayar')) {
                $table->string('tempo_bayar', 60)->nullable()->after('contact');
            }
            if (!Schema::hasColumn('members', 'idtk')) {
                $table->string('idtk', 100)->nullable()->after('tempo_bayar');
            }
            if (!Schema::hasColumn('members', 'no_rekening')) {
                $table->string('no_rekening', 100)->nullable()->after('credit_limit');
            }
        });

        // Allow duplicate or empty Nip (e.g. '0' or blank in legacy customer sheets) while keeping fast B-Tree lookup
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE members ALTER COLUMN nik DROP NOT NULL');
            DB::statement('ALTER TABLE members ALTER COLUMN branch DROP NOT NULL');
            DB::statement('ALTER TABLE members DROP CONSTRAINT IF EXISTS members_nik_unique');
            DB::statement('DROP INDEX IF EXISTS members_nik_unique');
            DB::statement('CREATE INDEX IF NOT EXISTS members_nik_index ON members (nik)');
        } elseif ($driver === 'sqlite') {
            try {
                Schema::table('members', function (Blueprint $table) {
                    $table->dropUnique('members_nik_unique');
                    $table->index('nik', 'members_nik_index');
                });
            } catch (\Throwable $e) {
                // Ignore if index already adjusted
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $cols = [
                'customer_id',
                'kode_out',
                'division',
                'address',
                'fax',
                'contact',
                'tempo_bayar',
                'idtk',
                'no_rekening',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('members', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
