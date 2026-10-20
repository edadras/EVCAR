<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سرور مرکزی OCPP 1.6J داخلی Edge:
 * - ocpp_transactions: شناسهٔ تراکنش OCPP (transactionId) که به شارژر داده می‌شود
 * - ocpp_calls: صف دستورهای خروجی (RemoteStart، Reset، ...) بین وب و پروسهٔ ocpp:serve
 * - ocpp_message_logs: لاگ خام پیام‌ها برای عیب‌یابی شارژرهای واقعی
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ocpp_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('charge_point_id', 64)->index();
            $table->unsignedInteger('connector_number');
            $table->string('id_tag', 20)->nullable();
            $table->string('id_tag_status', 20)->default('Accepted');
            $table->unsignedBigInteger('meter_start_wh')->default(0);
            $table->unsignedBigInteger('meter_stop_wh')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->string('stop_reason', 32)->nullable();
            $table->foreignId('charging_session_id')->nullable()->constrained('charging_sessions')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ocpp_calls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('charge_point_id', 64);
            $table->string('action', 64);
            $table->json('payload')->nullable();
            $table->string('status', 16)->default('pending'); // pending, sent, answered, error, timeout
            $table->string('message_id', 64)->nullable();
            $table->json('response')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'charge_point_id']);
        });

        Schema::create('ocpp_message_logs', function (Blueprint $table) {
            $table->id();
            $table->string('charge_point_id', 64)->index();
            $table->string('direction', 4); // in, out
            $table->unsignedTinyInteger('message_type');
            $table->string('action', 64)->nullable();
            $table->string('message_id', 64)->nullable();
            $table->text('payload')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::table('chargers', function (Blueprint $table) {
            $table->timestamp('last_boot_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->string('remote_address', 64)->nullable();
            $table->string('ocpp_status', 32)->nullable();
            $table->string('error_code', 32)->nullable();
        });

        Schema::table('connectors', function (Blueprint $table) {
            $table->string('error_code', 32)->nullable();
            $table->string('vendor_error_code', 64)->nullable();
        });

        Schema::table('cached_tariffs', function (Blueprint $table) {
            $table->boolean('is_default')->default(false);
            $table->boolean('station_assigned')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('cached_tariffs', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'station_assigned']);
        });
        Schema::table('connectors', function (Blueprint $table) {
            $table->dropColumn(['error_code', 'vendor_error_code']);
        });
        Schema::table('chargers', function (Blueprint $table) {
            $table->dropColumn(['last_boot_at', 'connected_at', 'remote_address', 'ocpp_status', 'error_code']);
        });
        Schema::dropIfExists('ocpp_message_logs');
        Schema::dropIfExists('ocpp_calls');
        Schema::dropIfExists('ocpp_transactions');
    }
};
