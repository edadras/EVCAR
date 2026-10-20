<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ev_catalog_vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->string('brand', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->decimal('battery_kwh', 6, 2);
            $table->unsignedSmallInteger('mass_kg')->default(1750);
            $table->decimal('regen_efficiency', 4, 3)->default(0.65);
            $table->decimal('cd', 4, 3)->nullable();
            $table->decimal('frontal_area_m2', 4, 2)->nullable();
            $table->json('consumption');
            $table->json('aliases')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ev_catalog_vehicles');
    }
};
