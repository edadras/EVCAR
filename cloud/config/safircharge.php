<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Edge probe URLs (comma-separated in env)
  |--------------------------------------------------------------------------
  | Browser-reachable bases for local edge controllers (e.g. http://127.0.0.1:8182).
  */
    'edge' => [
        'sync_token' => env('EDGE_CLOUD_SYNC_TOKEN', 'dev-sync-token'),
    ],

    'connectivity' => [
        'edge_internal_url' => env('EDGE_INTERNAL_URL'),
        'edge_probe_urls' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('SC_EDGE_PROBE_URLS', 'http://127.0.0.1:8182'))
        ))),
        'ask_when_multiple' => env('SC_CONNECTIVITY_ASK_MULTIPLE', true),
        'probe_timeout_ms' => (int) env('SC_CONNECTIVITY_TIMEOUT_MS', 2500),
    ],

    'auth' => [
        'test_otp_codes' => env('SC_TEST_OTP_CODES', '111111,222222,333333'),
    ],

    'payment' => [
        'gateways' => ['mellat', 'saman', 'zarin'],
        'gateway_aliases' => [
            'mellat' => 'mellat',
            'saman' => 'saman',
            'zarin' => 'zarinpal',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | ANPR — PLPR Lite روی Edge (مرجع: persian-license-plate-recognition/lite)
    |--------------------------------------------------------------------------
    */
    'anpr' => [
        'plpr_url' => rtrim((string) env('ANPR_PLPR_URL', 'http://127.0.0.1:8502'), '/'),
        'min_confidence' => (float) env('ANPR_MIN_CONFIDENCE', 0.92),
    ],

    /*
    |--------------------------------------------------------------------------
    | EV Navigator — مسیریابی هوشمند (فقط مرکز ابری)
    |--------------------------------------------------------------------------
    | وقتی api_url تنظیم شود، Laravel به FastAPI پروکسی می‌کند.
    | در غیر این صورت حالت lite (OSRM + Open-Meteo + ایستگاه‌های SafirCharge) فعال است.
    */
    'ev_navigator' => [
        'api_url' => rtrim((string) env('EV_NAVIGATOR_API_URL', ''), '/'),
        'timeout_seconds' => (int) env('EV_NAVIGATOR_TIMEOUT', 30),
        'popular_routes' => [
            ['label' => 'تهران → قم', 'origin' => ['lat' => 35.689, 'lng' => 51.389, 'label' => 'تهران'], 'destination' => ['lat' => 34.641, 'lng' => 50.874, 'label' => 'قم']],
            ['label' => 'تهران → اصفهان', 'origin' => ['lat' => 35.689, 'lng' => 51.389, 'label' => 'تهران'], 'destination' => ['lat' => 32.661, 'lng' => 51.676, 'label' => 'اصفهان']],
            ['label' => 'تهران → رشت', 'origin' => ['lat' => 35.689, 'lng' => 51.389, 'label' => 'تهران'], 'destination' => ['lat' => 37.268, 'lng' => 49.589, 'label' => 'رشت']],
            ['label' => 'تهران → ساری', 'origin' => ['lat' => 35.689, 'lng' => 51.389, 'label' => 'تهران'], 'destination' => ['lat' => 36.565, 'lng' => 53.058, 'label' => 'ساری']],
            ['label' => 'تهران → تبریز', 'origin' => ['lat' => 35.689, 'lng' => 51.389, 'label' => 'تهران'], 'destination' => ['lat' => 38.066, 'lng' => 46.291, 'label' => 'تبریز']],
            ['label' => 'تهران → مشهد', 'origin' => ['lat' => 35.689, 'lng' => 51.389, 'label' => 'تهران'], 'destination' => ['lat' => 36.297, 'lng' => 59.606, 'label' => 'مشهد']],
            ['label' => 'اصفهان → تهران', 'origin' => ['lat' => 32.661, 'lng' => 51.676, 'label' => 'اصفهان'], 'destination' => ['lat' => 35.689, 'lng' => 51.389, 'label' => 'تهران']],
            ['label' => 'تهران → شیراز', 'origin' => ['lat' => 35.689, 'lng' => 51.389, 'label' => 'تهران'], 'destination' => ['lat' => 29.591, 'lng' => 52.583, 'label' => 'شیراز']],
        ],
    ],

];
