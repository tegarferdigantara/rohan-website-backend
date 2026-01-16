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
        Schema::connection('rohan_manage')->create('ip_rules', function (Blueprint $table) {
            $table->id();
            $table->string('ip_address', 45);
            $table->enum('rule_type', ['whitelist', 'blacklist']);
            $table->integer('max_clients')->default(4);
            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['ip_address', 'rule_type'], 'unique_ip_rule');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('rohan_manage')->dropIfExists('ip_rules');
    }
};
