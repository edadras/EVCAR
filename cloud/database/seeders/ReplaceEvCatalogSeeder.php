<?php

namespace Database\Seeders;

use App\Models\EvCatalogVehicle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** کاتالوگ فعلی (حتی رکوردهای حذف‌شده) را کامل پاک می‌کند و از data/ev_catalog_vehicles.php از نو می‌سازد. */
class ReplaceEvCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $removed = EvCatalogVehicle::query()->withTrashed()->count();
            EvCatalogVehicle::query()->withTrashed()->forceDelete();
            $added = EvCatalogVehicleSeeder::insertAll();

            $this->command?->info("EV catalog: removed {$removed}, added {$added}");
        });
    }
}
