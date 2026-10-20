<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // احراز هویت مشتری روی ایستگاه — ارسال OTP از مودم GSM
        Schema::create('otp_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('phone', 20)->index();
            $table->string('purpose', 32); // register, login, start_charge
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->string('status', 20)->default('pending'); // pending, sent, verified, expired, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->uuid('customer_uuid')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['phone', 'status']);
        });

        Schema::create('gsm_message_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('otp_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20);
            $table->text('message_body');
            $table->string('direction', 10)->default('outbound');
            $table->string('status', 20)->default('queued'); // queued, sent, delivered, failed
            $table->string('modem_reference')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('customer_uuid')->index();
            $table->string('token_hash', 128)->unique();
            $table->string('platform', 20)->default('web'); // web, android, ios (محلی WiFi)
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_sessions');
        Schema::dropIfExists('gsm_message_logs');
        Schema::dropIfExists('otp_requests');
    }
};
