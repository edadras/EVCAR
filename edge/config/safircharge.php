<?php

return [

    'auth' => [
        // کدهای ثابت تست فقط خارج از محیط production فعال‌اند.
        'test_otp_codes' => env('SC_TEST_OTP_CODES', env('APP_ENV', 'production') === 'production' ? '' : '111111,222222,333333'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ANPR — PLPR Lite (persian-license-plate-recognition/lite)
    |--------------------------------------------------------------------------
    | Docker: پورت 8502 · POST /api/recognize · GET /health
    */
    'anpr' => [
        'plpr_url' => rtrim((string) env('ANPR_PLPR_URL', 'http://host.docker.internal:8502'), '/'),
        'min_confidence' => (float) env('ANPR_MIN_CONFIDENCE', 0.92),
        'default_camera' => env('ANPR_CAMERA_ID', 'CAM-01'),
        'auto_push_cloud' => env('ANPR_AUTO_PUSH', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | OCPP 1.6J — ارتباط با شارژرها (فقط محلی در ایستگاه)
    |--------------------------------------------------------------------------
    | driver:
    |   builtin   — سرور مرکزی OCPP داخلی Edge (php artisan ocpp:serve).
    |               شارژرها به ws://<edge-host>:9000/ocpp/<ChargePointId> وصل می‌شوند.
    |   gateway   — سرویس خارجی Charger (C:\Project\Charger) از طریق REST API.
    |   simulator — بدون سخت‌افزار؛ فقط برای توسعه و دمو (انرژی تخمینی).
    | ابر هرگز مستقیم به شارژر وصل نمی‌شود؛ دستورها از طریق Edge اجرا می‌شوند.
    */
    'ocpp' => [
        'driver' => env('OCPP_DRIVER', 'builtin'),

        // سرور داخلی (builtin)
        'listen_host' => env('OCPP_LISTEN_HOST', '0.0.0.0'),
        'listen_port' => (int) env('OCPP_LISTEN_PORT', 9000),
        'heartbeat_interval' => (int) env('OCPP_HEARTBEAT_INTERVAL', 60),
        'allow_unknown_chargers' => (bool) env('OCPP_ALLOW_UNKNOWN_CHARGERS', false),
        'require_basic_auth' => (bool) env('OCPP_REQUIRE_BASIC_AUTH', false),
        'call_timeout' => (int) env('OCPP_CALL_TIMEOUT', 30),
        'remote_start_wait' => (int) env('OCPP_REMOTE_START_WAIT', 20),
        'remote_stop_wait' => (int) env('OCPP_REMOTE_STOP_WAIT', 10),
        'start_timeout_minutes' => (int) env('OCPP_START_TIMEOUT_MINUTES', 3),
        'min_wallet_balance' => (float) env('SC_MIN_WALLET_BALANCE', 10000),
        'progress_sync_seconds' => (int) env('OCPP_PROGRESS_SYNC_SECONDS', 15),
        'log_messages' => (bool) env('OCPP_LOG_MESSAGES', true),
        'log_retention_days' => (int) env('OCPP_LOG_RETENTION_DAYS', 14),

        // سرویس خارجی (gateway) — مرجع: C:\Project\Charger\docs\API.md
        'api_url' => rtrim((string) env('OCPP_API_URL', env('OCPP_GATEWAY_URL', '')), '/'),
        'email' => env('OCPP_API_EMAIL', 'admin@ocpp.local'),
        'password' => env('OCPP_API_PASSWORD', 'Admin@123456'),
    ],

];
