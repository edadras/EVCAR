<?php

return [

    // ساعت محلی برای قوانین تعرفه (اوج/کم‌باری) — ساعت سرور UTC است.
    'timezone' => env('SC_LOCAL_TIMEZONE', 'Asia/Tehran'),

    /*
  |--------------------------------------------------------------------------
  | Edge probe URLs (comma-separated in env)
  |--------------------------------------------------------------------------
  | Browser-reachable bases for local edge controllers (e.g. http://127.0.0.1:8182).
  */
    'edge' => [
        'sync_token' => env('EDGE_CLOUD_SYNC_TOKEN', 'dev-sync-token'),
    ],

    /*
    |--------------------------------------------------------------------------
    | شارژ از طریق اپ آنلاین
    |--------------------------------------------------------------------------
    | ابر مستقیم به شارژر وصل نمی‌شود: شروع/توقف به‌صورت دستور remote_start/remote_stop
    | برای Edge همان ایستگاه صف می‌شود و Edge آن را با OCPP روی شارژر اجرا می‌کند.
    | allow_without_edge فقط برای دمو/توسعه است (جلسهٔ شبیه‌سازی‌شده بدون شارژر واقعی).
    */
    /*
    |--------------------------------------------------------------------------
    | OCPP 1.6J ابری — برای ایستگاه‌های بدون کنترلر محلی (Edge)
    |--------------------------------------------------------------------------
    | php artisan ocpp:serve — شارژر به ws://<cloud-host>:9100/ocpp/<ChargePointId> وصل می‌شود.
    | شارژرهای ایستگاه‌های دارای Edge باید به Edge همان ایستگاه وصل شوند.
    */
    'ocpp' => [
        'listen_host' => env('OCPP_LISTEN_HOST', '0.0.0.0'),
        'listen_port' => (int) env('OCPP_LISTEN_PORT', 9100),
        'heartbeat_interval' => (int) env('OCPP_HEARTBEAT_INTERVAL', 60),
        'require_basic_auth' => (bool) env('OCPP_REQUIRE_BASIC_AUTH', false),
        'call_timeout' => (int) env('OCPP_CALL_TIMEOUT', 30),
        'remote_start_wait' => (int) env('OCPP_REMOTE_START_WAIT', 20),
        'remote_stop_wait' => (int) env('OCPP_REMOTE_STOP_WAIT', 10),
        'start_timeout_minutes' => (int) env('OCPP_START_TIMEOUT_MINUTES', 3),
        'min_wallet_balance' => (float) env('SC_MIN_WALLET_BALANCE', 10000),
        'log_messages' => (bool) env('OCPP_LOG_MESSAGES', true),
        'log_retention_days' => (int) env('OCPP_LOG_RETENTION_DAYS', 14),
    ],

    'charging' => [
        'allow_without_edge' => (bool) env('SC_ALLOW_CHARGING_WITHOUT_EDGE', env('APP_ENV', 'production') !== 'production'),
        'remote_start_timeout_minutes' => (int) env('SC_REMOTE_START_TIMEOUT_MINUTES', 3),
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
        // کدهای ثابت تست فقط خارج از محیط production فعال‌اند.
        'test_otp_codes' => env('SC_TEST_OTP_CODES', env('APP_ENV', 'production') === 'production' ? '' : '111111,222222,333333'),
    ],

    // OCPI 2.2.1 برای رومینگ (توکن‌های شرکای رومینگ، جداشده با کاما)
    'ocpi' => [
        'tokens' => env('OCPI_TOKENS', ''),
        'country_code' => env('OCPI_COUNTRY_CODE', 'IR'),
        'party_id' => env('OCPI_PARTY_ID', 'SFC'),
    ],

    'finance' => [
        // نرخ مالیات بر ارزش افزوده برای گزارش مالیاتی (مبالغ شامل مالیات فرض می‌شوند)
        'vat_percent' => (float) env('SC_VAT_PERCENT', 10),
    ],

    'payment' => [
        // simulated: پرداخت آزمایشی بدون درگاه (در production غیرفعال است) · zarinpal: درگاه واقعی
        'driver' => env('PAYMENT_DRIVER', 'simulated'),
        'zarinpal' => [
            'merchant_id' => env('ZARINPAL_MERCHANT_ID', ''),
            'sandbox' => (bool) env('ZARINPAL_SANDBOX', false),
        ],
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
