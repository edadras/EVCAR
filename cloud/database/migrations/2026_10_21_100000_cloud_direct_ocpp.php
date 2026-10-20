<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سرور OCPP 1.6J ابری برای ایستگاه‌هایی که کنترلر محلی (Edge) ندارند:
 * شارژر مستقیم به سرور اصلی وصل می‌شود.
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
            $table->string('status', 16)->default('pending');
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
            $table->string('direction', 4);
            $table->unsignedTinyInteger('message_type');
            $table->string('action', 64)->nullable();
            $table->string('message_id', 64)->nullable();
            $table->text('payload')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::table('chargers', function (Blueprint $table) {
            $table->string('connection_mode', 16)->default('auto'); // auto | edge | direct
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_boot_at')->nullable();
            $table->string('ocpp_status', 32)->nullable();
            $table->string('error_code', 32)->nullable();
        });

        Schema::table('connectors', function (Blueprint $table) {
            $table->string('error_code', 32)->nullable();
        });

        // دستورهای راه دور ایستگاه بدون Edge مستقیم روی سرور OCPP ابری اجرا و همین‌جا ثبت می‌شوند.
        Schema::table('edge_commands', function (Blueprint $table) {
            $table->foreignId('edge_station_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('connectors', fn (Blueprint $t) => $t->dropColumn('error_code'));
        Schema::table('chargers', fn (Blueprint $t) => $t->dropColumn(['connection_mode', 'connected_at', 'last_boot_at', 'ocpp_status', 'error_code']));
        Schema::dropIfExists('ocpp_message_logs');
        Schema::dropIfExists('ocpp_calls');
        Schema::dropIfExists('ocpp_transactions');
    }
};
