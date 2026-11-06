<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ev_operators', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('network', 120)->nullable();
            $table->string('logo_path', 255)->nullable();
            $table->string('color', 9)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('app_name', 120)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('external_stations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ev_operator_id')->constrained('ev_operators')->cascadeOnDelete();
            $table->string('name', 160);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('city', 80)->nullable();
            $table->string('address', 500)->nullable();
            $table->json('dc_connectors')->nullable();
            $table->json('ac_connectors')->nullable();
            $table->decimal('dc_max_kw', 6, 1)->nullable();
            $table->decimal('ac_max_kw', 6, 1)->nullable();
            $table->unsignedSmallInteger('ports')->nullable();
            $table->string('hours', 120)->nullable();
            $table->string('facilities', 500)->nullable();
            $table->unsignedInteger('tariff_toman')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('image_url', 500)->nullable();
            $table->string('notes', 500)->nullable();
            $table->string('source', 40)->default('manual');
            $table->string('source_ref', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['source', 'source_ref']);
            $table->index(['is_active', 'ev_operator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_stations');
        Schema::dropIfExists('ev_operators');
    }
};
