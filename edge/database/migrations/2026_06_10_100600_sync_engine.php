<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_outbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('event_type', 64);
            $table->string('aggregate_type', 64)->nullable();
            $table->uuid('aggregate_uuid')->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('acked_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
        });

        Schema::create('sync_inbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('event_type', 64);
            $table->string('aggregate_type', 64)->nullable();
            $table->uuid('aggregate_uuid')->nullable();
            $table->json('payload');
            $table->unsignedInteger('cloud_version')->default(0);
            $table->timestamp('occurred_at');
            $table->timestamp('received_at');
            $table->string('status', 20)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('stream', 32); // cloud_pull, cloud_push_ack
            $table->unsignedBigInteger('cursor_position')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique('stream');
        });

        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
        Schema::dropIfExists('sync_cursors');
        Schema::dropIfExists('sync_inbox');
        Schema::dropIfExists('sync_outbox');
    }
};
