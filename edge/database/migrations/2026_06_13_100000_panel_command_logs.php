<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('panel_command_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('connector_id')->nullable()->index();
            $table->string('command_type', 40);
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('completed');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->boolean('simulated')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('panel_command_logs');
    }
};
