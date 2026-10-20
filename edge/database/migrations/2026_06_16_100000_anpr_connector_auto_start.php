<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('anpr_detections', function (Blueprint $table) {
            if (! Schema::hasColumn('anpr_detections', 'connector_id')) {
                $table->foreignId('connector_id')->nullable()->after('camera_source')->constrained('connectors')->nullOnDelete();
            }
            if (! Schema::hasColumn('anpr_detections', 'auto_start_status')) {
                $table->string('auto_start_status', 20)->default('none')->after('charging_session_uuid');
            }
            if (! Schema::hasColumn('anpr_detections', 'auto_start_message')) {
                $table->text('auto_start_message')->nullable()->after('auto_start_status');
            }
            if (! Schema::hasColumn('anpr_detections', 'auto_start_at')) {
                $table->timestamp('auto_start_at')->nullable()->after('auto_start_message');
            }
        });

        Schema::table('anpr_detections', function (Blueprint $table) {
            $table->index(['connector_id', 'auto_start_status', 'detected_at'], 'anpr_det_conn_status_at');
        });
    }

    public function down(): void
    {
        Schema::table('anpr_detections', function (Blueprint $table) {
            $table->dropForeign(['connector_id']);
            $table->dropColumn(['connector_id', 'auto_start_status', 'auto_start_message', 'auto_start_at']);
        });
    }
};
