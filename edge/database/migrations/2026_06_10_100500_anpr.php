<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anpr_detections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('plate_normalized', 16)->nullable()->index();
            $table->string('plate_two', 2)->nullable();
            $table->string('plate_letter', 4)->nullable();
            $table->string('plate_three', 3)->nullable();
            $table->string('plate_province', 2)->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->uuid('cached_user_uuid')->nullable()->index();
            $table->uuid('cached_vehicle_uuid')->nullable();
            $table->uuid('charging_session_uuid')->nullable()->index();
            $table->string('camera_source')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamp('detected_at');
            $table->boolean('pending_sync')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->json('raw_result')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anpr_detections');
    }
};
