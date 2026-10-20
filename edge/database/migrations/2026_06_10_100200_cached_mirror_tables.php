<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cached_users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('cloud_user_uuid')->nullable()->unique();
            $table->string('phone', 20)->unique();
            $table->string('name')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_local_only')->default(false);
            $table->boolean('pending_sync')->default(false);
            $table->unsignedInteger('cloud_version')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cached_wallets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('cloud_wallet_uuid')->nullable()->unique();
            $table->foreignId('cached_user_id')->constrained('cached_users')->cascadeOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->decimal('bonus_balance', 14, 2)->default(0);
            $table->string('currency', 8)->default('IRR');
            $table->unsignedInteger('cloud_version')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cached_rfid_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('cloud_rfid_uuid')->nullable()->unique();
            $table->uuid('cached_user_uuid')->nullable()->index();
            $table->string('id_tag', 64)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('cloud_version')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cached_tariffs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('cloud_tariff_uuid')->unique();
            $table->string('name');
            $table->string('pricing_model', 32);
            $table->decimal('price_per_kwh', 12, 4)->nullable();
            $table->decimal('price_per_minute', 12, 4)->nullable();
            $table->decimal('session_fee', 12, 2)->default(0);
            $table->json('rules')->nullable();
            $table->unsignedInteger('cloud_version')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cached_qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('cloud_qr_uuid')->nullable()->unique();
            $table->string('token', 128)->unique();
            $table->uuid('cloud_charger_uuid')->nullable();
            $table->uuid('cloud_connector_uuid')->nullable();
            $table->unsignedTinyInteger('connector_number')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('offline_capable')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('cloud_version')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cached_vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('cloud_vehicle_uuid')->nullable()->unique();
            $table->uuid('cached_user_uuid')->index();
            $table->string('plate_normalized', 16)->nullable()->index();
            $table->string('plate_two', 2)->nullable();
            $table->string('plate_letter', 4)->nullable();
            $table->string('plate_three', 3)->nullable();
            $table->string('plate_province', 2)->nullable();
            $table->unsignedInteger('cloud_version')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cached_vehicles');
        Schema::dropIfExists('cached_qr_tokens');
        Schema::dropIfExists('cached_tariffs');
        Schema::dropIfExists('cached_rfid_cards');
        Schema::dropIfExists('cached_wallets');
        Schema::dropIfExists('cached_users');
    }
};
