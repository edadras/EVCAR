<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anpr_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('station_id')->constrained()->cascadeOnDelete();
            $table->string('edge_detection_uuid')->unique();
            $table->string('plate_normalized', 16)->nullable()->index();
            $table->string('plate_two', 2)->nullable();
            $table->string('plate_letter', 4)->nullable();
            $table->string('plate_three', 3)->nullable();
            $table->string('plate_province', 2)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('charging_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('image_path')->nullable();
            $table->timestamp('detected_at');
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_inbox', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_uuid')->unique();
            $table->string('edge_station_uuid')->index();
            $table->string('event_type', 64);
            $table->string('aggregate_type', 64)->nullable();
            $table->uuid('aggregate_uuid')->nullable();
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('received_at');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'received_at']);
        });

        Schema::create('sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('peer_type', 32); // edge_station
            $table->string('peer_uuid')->index();
            $table->string('stream', 32); // pull, push
            $table->unsignedBigInteger('cursor_position')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['peer_type', 'peer_uuid', 'stream']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 64);
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 32);
            $table->string('key', 64);
            $table->json('value')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('sync_cursors');
        Schema::dropIfExists('sync_inbox');
        Schema::dropIfExists('anpr_events');
    }
};
