<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tariffs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('code', 32)->unique();
            $table->string('pricing_model', 32); // energy, time, combined
            $table->string('currency', 8)->default('IRR');
            $table->decimal('price_per_kwh', 12, 4)->nullable();
            $table->decimal('price_per_minute', 12, 4)->nullable();
            $table->decimal('session_fee', 12, 2)->default(0);
            $table->decimal('member_discount_percent', 5, 2)->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tariff_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tariff_id')->constrained()->cascadeOnDelete();
            $table->string('rule_type', 32); // peak, off_peak, weekend, member
            $table->string('day_of_week', 16)->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->decimal('price_per_kwh', 12, 4)->nullable();
            $table->decimal('price_per_minute', 12, 4)->nullable();
            $table->decimal('multiplier', 6, 3)->default(1);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamps();
        });

        Schema::create('station_tariffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tariff_id')->constrained()->cascadeOnDelete();
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->timestamps();

            $table->unique(['station_id', 'tariff_id']);
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('balance', 14, 2)->default(0);
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->decimal('bonus_balance', 14, 2)->default(0);
            $table->string('currency', 8)->default('IRR');
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('version')->default(1); // optimistic lock for sync
            $table->timestamps();
        });

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32); // topup, charge, refund, bonus, withdraw, corporate
            $table->decimal('amount', 14, 2);
            $table->decimal('balance_after', 14, 2);
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key', 64)->nullable()->unique();
            $table->string('edge_station_uuid')->nullable()->index();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
        Schema::dropIfExists('station_tariffs');
        Schema::dropIfExists('tariff_rules');
        Schema::dropIfExists('tariffs');
    }
};
