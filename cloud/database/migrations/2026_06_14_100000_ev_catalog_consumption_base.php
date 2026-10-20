<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ev_catalog_vehicles', function (Blueprint $table) {
            $table->decimal('consumption_base', 5, 2)->nullable()->after('frontal_area_m2');
            $table->unsignedSmallInteger('range_wltp_km')->nullable()->after('consumption_base');
            $table->unsignedSmallInteger('range_real_min_km')->nullable()->after('range_wltp_km');
            $table->unsignedSmallInteger('range_real_max_km')->nullable()->after('range_real_min_km');
            $table->unsignedSmallInteger('range_worst_km')->nullable()->after('range_real_max_km');
        });

        foreach (DB::table('ev_catalog_vehicles')->orderBy('id')->get() as $row) {
            $consumption = json_decode($row->consumption ?? '{}', true) ?: [];
            $base = $consumption['base']
                ?? $consumption['mixed']
                ?? $consumption['city']
                ?? 16.0;

            DB::table('ev_catalog_vehicles')->where('id', $row->id)->update([
                'consumption_base' => $base,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('ev_catalog_vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'consumption_base',
                'range_wltp_km',
                'range_real_min_km',
                'range_real_max_km',
                'range_worst_km',
            ]);
        });
    }
};
