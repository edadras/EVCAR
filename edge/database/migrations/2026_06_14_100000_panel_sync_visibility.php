<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panel_command_logs', function (Blueprint $table) {
            $table->string('source', 16)->default('panel')->after('uuid');
            $table->uuid('cloud_command_uuid')->nullable()->after('source');
            $table->index(['source', 'created_at']);
        });

        Schema::create('sync_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 32);
            $table->string('status', 20)->default('completed');
            $table->json('stats')->nullable();
            $table->text('message')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_activity_logs');

        Schema::table('panel_command_logs', function (Blueprint $table) {
            $table->dropIndex(['source', 'created_at']);
            $table->dropColumn(['source', 'cloud_command_uuid']);
        });
    }
};
