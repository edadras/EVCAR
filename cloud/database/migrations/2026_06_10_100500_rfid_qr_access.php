<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfid_cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('id_tag', 64)->unique(); // OCPP idTag
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->string('card_label')->nullable();
            $table->string('status', 20)->default('active'); // active, blocked, expired
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->foreignId('charger_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('connector_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 128)->unique();
            $table->string('deep_link')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('offline_capable')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_tokens');
        Schema::dropIfExists('rfid_cards');
    }
};
