<?php

namespace Database\Seeders;

use App\Models\EvOperator;
use App\Models\ExternalStation;
use Illuminate\Database\Seeder;

class ExternalStationsSeeder extends Seeder
{
    /** @var array<string, array<string, mixed>> */
    public const OPERATORS = [
        'mapna' => [
            'name' => 'مپنا',
            'network' => 'شبکه شارژ شارینت (مکو)',
            'logo_path' => '/assets/operators/mapna.png',
            'color' => '#0a5ea8',
            'website' => 'https://www.emapna.com',
            'phone' => '026-35701001',
            'app_name' => 'شارینت',
            'description' => 'مرکز کسب‌وکار برقی‌سازی مپنا؛ شبکهٔ سراسری ایستگاه‌های شارژ شارینت.',
            'sort_order' => 1,
        ],
        'nebka' => [
            'name' => 'نبکا (کرمان موتور)',
            'network' => 'شبکه شارینت',
            'logo_path' => '/assets/operators/nebka.png',
            'color' => '#3f3f46',
            'website' => 'https://kermanmotor.com',
            'description' => 'ایستگاه‌های شارژ نمایندگی‌ها و مجتمع‌های همکار کرمان موتور (نبکا).',
            'sort_order' => 2,
        ],
        'bahman' => [
            'name' => 'بهمن موتور',
            'network' => 'شبکه شارینت',
            'logo_path' => '/assets/operators/bahman.png',
            'color' => '#1f2937',
            'website' => 'https://bahman.ir',
            'description' => 'ایستگاه‌های شارژ عاملیت‌های گروه بهمن.',
            'sort_order' => 3,
        ],
        'ikco' => [
            'name' => 'ایران‌خودرو',
            'network' => 'شبکه شارینت',
            'logo_path' => '/assets/operators/ikco.png',
            'color' => '#1d4f91',
            'website' => 'https://www.ikco.ir',
            'description' => 'ایستگاه‌های شارژ امداد خودرو و نمایندگی‌های ایران‌خودرو.',
            'sort_order' => 4,
        ],
        'farhan' => [
            'name' => 'فرهان',
            'network' => 'شبکه شارینت',
            'logo_path' => '/assets/operators/farhan.png',
            'color' => '#16965a',
            'description' => 'ایستگاه‌های شارژ فرهان در اصفهان.',
            'sort_order' => 5,
        ],
        'hyperme' => [
            'name' => 'هایپرمی',
            'network' => 'شبکه شارینت',
            'logo_path' => '/assets/operators/hyperme.png',
            'color' => '#f59e0b',
            'website' => 'https://hyperme.ir',
            'description' => 'ایستگاه‌های شارژ فروشگاه‌های هایپرمی.',
            'sort_order' => 6,
        ],
    ];

    public function run(): void
    {
        $operators = [];
        foreach (self::OPERATORS as $slug => $attrs) {
            $operators[$slug] = EvOperator::firstOrCreate(['slug' => $slug], $attrs + ['is_active' => true]);
        }

        $rows = require __DIR__.'/data/external_stations_mapna.php';
        $created = $updated = 0;
        foreach ($rows as $row) {
            $row['ev_operator_id'] = $operators[$row['operator']]->id;
            unset($row['operator']);
            $station = ExternalStation::where('source', 'emapna')->where('source_ref', $row['source_ref'])->first();
            if (! $station) {
                ExternalStation::create($row + ['source' => 'emapna', 'is_active' => true]);
                $created++;
            } elseif ($this->untouched($station)) {
                $station->timestamps = false;
                $station->fill($row)->save();
                $updated++;
            }
        }

        $stale = ExternalStation::where('source', 'emapna')
            ->whereNotIn('source_ref', array_column($rows, 'source_ref'))
            ->get()
            ->filter(fn (ExternalStation $s) => $this->untouched($s));
        $stale->each->delete();

        $this->command?->info("External stations: {$created} new, {$updated} synced, {$stale->count()} removed, ".count($rows).' in source.');
    }

    /** Imported rows the admin never edited keep updated_at equal to created_at. */
    private function untouched(ExternalStation $station): bool
    {
        return $station->updated_at && $station->created_at && $station->updated_at->eq($station->created_at);
    }
}
