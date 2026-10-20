<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('station_identity', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('cloud_station_uuid')->nullable()->unique();
            $table->string('edge_code', 32)->unique();
            $table->string('name');
            $table->string('cloud_api_url')->nullable();
            $table->string('cloud_sync_token')->nullable();
            $table->string('local_hostname')->default('station.local');
            $table->string('wifi_ssid')->nullable();
            $table->string('timezone', 64)->default('Asia/Tehran');
            $table->boolean('offline_mode_enabled')->default(true);
            $table->string('software_version', 32)->nullable();
            $table->timestamp('last_cloud_heartbeat_at')->nullable();
            $table->timestamp('last_cloud_sync_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('gsm_modem_config', function (Blueprint $table) {
            $table->id();
            $table->string('port', 64)->default('/dev/ttyUSB0');
            $table->unsignedInteger('baud_rate')->default(115200);
            $table->string('pin_code', 8)->nullable();
            $table->string('sms_center', 20)->nullable();
            $table->string('sender_id', 20)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->string('status', 20)->default('unknown');
            $table->timestamp('last_check_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gsm_modem_config');
        Schema::dropIfExists('station_identity');
    }
};
