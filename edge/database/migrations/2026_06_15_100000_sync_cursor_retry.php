<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sync_cursors', function (Blueprint $table) {
            $table->unsignedTinyInteger('consecutive_failures')->default(0)->after('last_synced_at');
            $table->timestamp('next_retry_at')->nullable()->after('consecutive_failures');
            $table->text('last_error')->nullable()->after('next_retry_at');
        });
    }

    public function down(): void
    {
        Schema::table('sync_cursors', function (Blueprint $table) {
            $table->dropColumn(['consecutive_failures', 'next_retry_at', 'last_error']);
        });
    }
};
