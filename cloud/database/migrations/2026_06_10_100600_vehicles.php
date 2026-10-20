<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('plate_two', 2)->nullable();
            $table->string('plate_letter', 4)->nullable();
            $table->string('plate_three', 3)->nullable();
            $table->string('plate_province', 2)->nullable();
            $table->string('plate_normalized', 16)->nullable()->index();
            $table->string('brand', 64)->nullable();
            $table->string('model', 64)->nullable();
            $table->string('color', 32)->nullable();
            $table->unsignedSmallInteger('battery_kwh')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
