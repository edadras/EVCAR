<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cached_vehicles', function (Blueprint $table) {
            $table->string('brand')->nullable()->after('plate_province');
            $table->string('model')->nullable()->after('brand');
            $table->decimal('battery_kwh', 6, 1)->nullable()->after('model');
            $table->boolean('is_default')->default(false)->after('battery_kwh');
        });
    }

    public function down(): void
    {
        Schema::table('cached_vehicles', function (Blueprint $table) {
            $table->dropColumn(['brand', 'model', 'battery_kwh', 'is_default']);
        });
    }
};
