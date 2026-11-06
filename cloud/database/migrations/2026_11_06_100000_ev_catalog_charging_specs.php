<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ev_catalog_vehicles', function (Blueprint $table) {
            $table->unsignedSmallInteger('range_official_km')->nullable()->after('range_wltp_km');
            $table->string('range_standard', 8)->nullable()->after('range_official_km');
            $table->string('battery_chemistry', 16)->nullable()->after('battery_kwh');
            $table->unsignedSmallInteger('motor_kw')->nullable()->after('mass_kg');
            $table->string('drivetrain', 8)->nullable()->after('motor_kw');
            $table->string('dc_connector', 8)->nullable()->after('range_worst_km');
            $table->string('ac_connector', 8)->nullable()->after('dc_connector');
            $table->decimal('dc_max_kw', 6, 1)->nullable()->after('ac_connector');
            $table->decimal('ac_max_kw', 5, 1)->nullable()->after('dc_max_kw');
            $table->string('importer', 80)->nullable()->after('model');
            $table->string('notes', 500)->nullable()->after('aliases');
        });
    }

    public function down(): void
    {
        Schema::table('ev_catalog_vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'range_official_km', 'range_standard', 'battery_chemistry', 'motor_kw', 'drivetrain',
                'dc_connector', 'ac_connector', 'dc_max_kw', 'ac_max_kw', 'importer', 'notes',
            ]);
        });
    }
};
