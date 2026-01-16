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
        Schema::connection('rohan_manage')->create('game_sessions', function ($table) {
            $table->id();
            $table->string('session_id', 64)->unique();
            $table->string('ip_address', 45);
            $table->string('hwid',64)->nullable();
            $table->string('client_hash', 64)->nullable();

            $table->timestamp('launched_at')->useCurrent();
            $table->timestamp('last_heartbeat')->useCurrent();

            $table->enum('status', ['active', 'closed'])->nullable()->default('active');

            $table->index('ip_address', 'idx_ip');
            $table->index('status', 'idx_status');
            $table->index('last_heartbeat', 'idx_heartbeat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('rohan_manage')->dropIfExists('game_sessions');
    }
};
