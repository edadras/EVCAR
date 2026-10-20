<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('charging_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('cloud_session_uuid')->nullable()->unique();
            $table->foreignId('charger_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connector_id')->constrained()->cascadeOnDelete();
            $table->uuid('cached_user_uuid')->nullable()->index();
            $table->uuid('cached_vehicle_uuid')->nullable();
            $table->uuid('cached_tariff_uuid')->nullable();
            $table->unsignedBigInteger('ocpp_transaction_id')->nullable()->index();
            $table->string('id_tag', 64)->nullable();
            $table->string('auth_method', 32);
            $table->string('status', 20)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->unsignedBigInteger('meter_start_wh')->nullable();
            $table->unsignedBigInteger('meter_stop_wh')->nullable();
            $table->decimal('energy_kwh', 12, 3)->default(0);
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->decimal('cost_amount', 14, 2)->default(0);
            $table->string('currency', 8)->default('IRR');
            $table->string('stop_reason', 64)->nullable();
            $table->boolean('pending_sync')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });

        Schema::create('session_meter_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('charging_session_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sampled_at');
            $table->unsignedBigInteger('energy_wh')->nullable();
            $table->unsignedInteger('power_w')->nullable();
            $table->unsignedInteger('voltage_v')->nullable();
            $table->decimal('current_a', 8, 2)->nullable();
            $table->unsignedTinyInteger('soc_percent')->nullable();
            $table->boolean('pending_sync')->default(true);
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['charging_session_id', 'sampled_at']);
        });

        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('cloud_reservation_uuid')->nullable()->unique();
            $table->uuid('cached_user_uuid')->index();
            $table->foreignId('charger_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('connector_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamp('reserved_from');
            $table->timestamp('reserved_until');
            $table->boolean('pending_sync')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('pending_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('charging_session_uuid')->nullable()->index();
            $table->uuid('cached_user_uuid')->nullable()->index();
            $table->string('type', 32); // wallet_debit, payment, refund
            $table->decimal('amount', 14, 2);
            $table->string('status', 20)->default('pending');
            $table->string('idempotency_key', 64)->unique();
            $table->json('payload')->nullable();
            $table->boolean('pending_sync')->default(true);
            $table->timestamp('occurred_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_transactions');
        Schema::dropIfExists('reservations');
        Schema::dropIfExists('session_meter_values');
        Schema::dropIfExists('charging_sessions');
    }
};
