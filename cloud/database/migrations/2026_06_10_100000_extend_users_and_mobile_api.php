<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');
            $table->string('phone', 20)->nullable()->unique()->after('email');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->string('role', 32)->default('customer')->after('phone_verified_at');
            $table->string('status', 20)->default('active')->after('role');
            $table->string('national_code', 20)->nullable()->after('status');
            $table->string('avatar_path')->nullable()->after('national_code');
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
            $table->string('preferred_locale', 10)->default('fa')->after('last_login_at');
            $table->json('meta')->nullable()->after('preferred_locale');

            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });

        DB::table('users')->orderBy('id')->lazyById()->each(function ($user) {
            DB::table('users')->where('id', $user->id)->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->string('device_name')->nullable();
            $table->string('platform', 20)->nullable(); // web, android, ios
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('platform', 20); // android, ios
            $table->string('device_id')->nullable();
            $table->string('push_token')->nullable();
            $table->string('app_version', 32)->nullable();
            $table->string('os_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'device_id', 'platform']);
        });

        // OTP آنلاین (پشتیبان) — احراز اصلی مشتری روی Edge با GSM است
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('phone', 20)->index();
            $table->string('purpose', 32); // login, register, reset
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('channel', 20)->default('sms'); // sms, edge_gsm
            $table->string('edge_station_uuid')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
        Schema::dropIfExists('mobile_devices');
        Schema::dropIfExists('personal_access_tokens');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'uuid', 'phone', 'phone_verified_at', 'role', 'status',
                'national_code', 'avatar_path', 'last_login_at', 'preferred_locale', 'meta',
            ]);
        });
    }
};
