<?php

namespace Database\Seeders;

use App\Models\StationIdentity;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        StationIdentity::updateOrCreate(
            ['edge_code' => env('EDGE_CODE', 'EDGE-AZADI-01')],
            [
                'uuid' => StationIdentity::where('edge_code', env('EDGE_CODE', 'EDGE-AZADI-01'))->value('uuid') ?? (string) Str::uuid(),
                'name' => env('EDGE_STATION_NAME', 'ایستگاه میدان آزادی (Edge)'),
                'cloud_api_url' => env('CLOUD_API_URL', 'http://cloud-nginx'),
                'cloud_sync_token' => env('CLOUD_SYNC_TOKEN', 'dev-sync-token'),
                'local_hostname' => env('EDGE_LOCAL_HOSTNAME', 'station.local'),
                'wifi_ssid' => env('EDGE_WIFI_SSID', 'SafirCharge-Azadi'),
                'offline_mode_enabled' => true,
                'software_version' => '1.0.0-phase3',
            ]
        );

        $email = env('PANEL_OPERATOR_EMAIL', 'operator@station.local');
        User::updateOrCreate(
            ['email' => $email],
            [
                'uuid' => User::where('email', $email)->value('uuid') ?? (string) Str::uuid(),
                'name' => env('PANEL_OPERATOR_NAME', 'اپراتور ایستگاه'),
                'password' => Hash::make(env('PANEL_OPERATOR_PASSWORD', 'Station@123456')),
                'role' => 'operator',
                'phone' => env('PANEL_OPERATOR_PHONE'),
                'status' => 'active',
            ]
        );
    }
}
