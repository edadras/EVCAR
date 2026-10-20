<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chargers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('cloud_charger_uuid')->nullable()->unique();
            $table->string('charge_point_id', 64)->unique();
            $table->string('vendor')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('firmware_version')->nullable();
            $table->unsignedSmallInteger('max_power_kw')->nullable();
            $table->boolean('is_online')->default(false);
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->string('status', 20)->default('inactive');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('connectors', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('cloud_connector_uuid')->nullable()->unique();
            $table->foreignId('charger_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('connector_number');
            $table->string('ocpp_status', 32)->default('Unavailable');
            $table->string('operational_status', 20)->default('inactive');
            $table->unsignedSmallInteger('max_power_kw')->nullable();
            $table->timestamp('status_updated_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['charger_id', 'connector_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connectors');
        Schema::dropIfExists('chargers');
    }
};
