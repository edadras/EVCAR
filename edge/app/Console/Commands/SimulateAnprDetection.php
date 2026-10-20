<?php

namespace App\Console\Commands;

use App\Models\AnprDetection;
use App\Services\CloudSyncClient;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SimulateAnprDetection extends Command
{
    protected $signature = 'anpr:simulate
        {--plate-two=12 : Two-digit plate part}
        {--letter=س : Plate letter}
        {--plate-three=345 : Three-digit plate part}
        {--province=11 : Province code}
        {--confidence=0.97 : Detection confidence}
        {--push : Push to Cloud immediately}';

    protected $description = 'Record a simulated ANPR detection on Edge (for demo/testing)';

    public function handle(CloudSyncClient $client): int
    {
        $two = (string) $this->option('plate-two');
        $letter = (string) $this->option('letter');
        $three = (string) $this->option('plate-three');
        $province = (string) $this->option('province');
        $normalized = $two.$letter.$three.$province;

        $detection = AnprDetection::create([
            'uuid' => (string) Str::uuid(),
            'plate_two' => $two,
            'plate_letter' => $letter,
            'plate_three' => $three,
            'plate_province' => $province,
            'plate_normalized' => $normalized,
            'confidence' => (float) $this->option('confidence'),
            'camera_source' => 'CAM-01',
            'detected_at' => now(),
            'pending_sync' => true,
            'raw_result' => ['source' => 'simulate'],
        ]);

        $this->info("ANPR detection recorded: {$normalized} ({$detection->uuid})");

        if ($this->option('push')) {
            $ok = $client->pushAnprDetection($detection);
            $this->info($ok ? 'Pushed to Cloud.' : 'Cloud push failed.');
        }

        return self::SUCCESS;
    }
}
