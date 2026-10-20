<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('national_id', 20)->nullable();
            $table->string('economic_code', 20)->nullable();
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->text('address')->nullable();
            $table->string('city', 64)->nullable();
            $table->string('province', 64)->nullable();
            $table->decimal('revenue_share_percent', 5, 2)->default(70);
            $table->decimal('platform_fee_percent', 5, 2)->default(30);
            $table->string('settlement_cycle', 20)->default('monthly');
            $table->string('bank_iban', 34)->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('type', 32); // municipality, taxi, corporate
            $table->string('contact_phone', 20)->nullable();
            $table->string('contact_email')->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->decimal('consumption_cap_kwh', 12, 3)->nullable();
            $table->string('status', 20)->default('active');
            $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('partner_id')->nullable()->after('meta')->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->after('partner_id')->constrained()->nullOnDelete();
        });

        Schema::create('organization_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('member_role', 32)->default('driver');
            $table->decimal('consumption_cap_kwh', 12, 3)->nullable();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        Schema::create('partner_contracts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('partner_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->decimal('revenue_share_percent', 5, 2);
            $table->decimal('platform_fee_percent', 5, 2);
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_contracts');
        Schema::dropIfExists('organization_members');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropConstrainedForeignId('partner_id');
        });
        Schema::dropIfExists('organizations');
        Schema::dropIfExists('partners');
    }
};
