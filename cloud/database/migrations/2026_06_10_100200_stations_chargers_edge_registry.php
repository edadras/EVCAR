<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->foreignId('partner_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32)->default('public'); // public, private, semi_public
            $table->string('status', 20)->default('active');
            $table->string('address')->nullable();
            $table->string('city', 64)->nullable();
            $table->string('province', 64)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('timezone', 64)->default('Asia/Tehran');
            $table->boolean('offline_mode_enabled')->default(true);
            $table->string('local_hostname')->nullable(); // station.local
            $table->string('wifi_ssid')->nullable();
            $table->json('operating_hours')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('chargers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('charge_point_id', 64)->unique(); // OCPP id
            $table->string('vendor')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('firmware_version')->nullable();
            $table->unsignedSmallInteger('max_power_kw')->nullable();
            $table->string('connector_type', 32)->nullable(); // CCS2, CHAdeMO, Type2
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('status', 20)->default('inactive');
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('connectors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('charger_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('connector_number');
            $table->string('ocpp_status', 32)->default('Unavailable');
            $table->string('operational_status', 20)->default('inactive');
            $table->unsignedSmallInteger('max_power_kw')->nullable();
            $table->string('qr_token_uuid')->nullable()->index();
            $table->timestamp('status_updated_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['charger_id', 'connector_number']);
        });

        Schema::create('edge_stations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('station_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('edge_code', 32)->unique();
            $table->string('api_token_hash', 128);
            $table->string('software_version', 32)->nullable();
            $table->string('hostname')->nullable();
            $table->string('public_ip', 45)->nullable();
            $table->string('sync_status', 20)->default('unknown');
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->unsignedBigInteger('last_sync_cursor')->default(0);
            $table->json('capabilities')->nullable(); // gsm, anpr, ocpp
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edge_stations');
        Schema::dropIfExists('connectors');
        Schema::dropIfExists('chargers');
        Schema::dropIfExists('stations');
    }
};
