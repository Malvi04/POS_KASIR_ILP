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
        Schema::create('system_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('role', 30)->default('ALL')->index(); // KASIR, MANAGER, OWNER, ALL
            $table->string('title');
            $table->text('message');
            $table->string('category', 50)->default('SYSTEM')->index();
            $table->string('icon', 50)->default('notifications');
            $table->string('color', 30)->default('blue');
            $table->string('link')->nullable();
            $table->string('reference_id')->nullable();
            $table->boolean('is_read')->default(false)->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
