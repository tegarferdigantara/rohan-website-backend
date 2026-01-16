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
        Schema::connection('rohan_manage')->create('server_settings', function (Blueprint $table) {
            $table->string('key', 50)->primary();
            $table->string('value', 255);
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

        });

        //Default Value
        DB::connection('rohan_manage')->table('server_settings')->upsert([
            ['key' => 'max_clients_per_ip', 'value' => '4'],
            ['key' => 'session_timeout_seconds', 'value' => '60'],
            ['key' => 'maintenance_mode', 'value' => '0'],
            ['key' => 'launcher_secret', 'value' => 'o6LDOB3E2Nv4mYPM'],
            ['key' => 'server_list', 'value' => 'Odin|127.0.0.1|22100|3|3|1|0|0|0|International Server|'],
            ['key' => 'down_flag', 'value' => 'ROHAN|1|1|ROHAN|DEFAULT'],
        ],['key'], ['value']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('rohan_manage')->dropIfExists('server_settings');
    }
};
