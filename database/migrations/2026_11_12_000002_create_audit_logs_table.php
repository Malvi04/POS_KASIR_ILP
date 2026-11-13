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
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->string('log_id', 50)->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('user_name', 150);
                $table->string('user_role', 50)->default('Owner');
                $table->string('location', 100)->default('Pusat');
                $table->string('category', 100)->default('Umum');
                $table->string('action_name', 150);
                $table->text('description');
                $table->string('reference', 100)->nullable();
                $table->string('status', 50)->default('BERHASIL');
                $table->string('source', 100)->nullable();
                $table->json('changes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
