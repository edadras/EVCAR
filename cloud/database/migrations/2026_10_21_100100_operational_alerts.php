<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operational_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40);          // charger_offline, connector_faulted, edge_offline, ...
            $table->string('subject_key', 100);  // CP-1001 / CP-1001#2 / EDGE-AZADI-01
            $table->string('severity', 16)->default('warning'); // info, warning, critical
            $table->string('message', 500);
            $table->foreignId('station_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->default('open'); // open, resolved
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('opened_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'subject_key', 'status']);
            $table->index(['status', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operational_alerts');
    }
};
