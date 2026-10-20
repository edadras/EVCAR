<?php

return [

    'auth' => [
        'test_otp_codes' => env('SC_TEST_OTP_CODES', '111111,222222,333333'),
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
    | OCPP Gateway (Charger) — فقط محلی در ایستگاه
    |--------------------------------------------------------------------------
    | هر Edge فقط به WebSocket/OCPP Gateway خودش وصل می‌شود؛ ابر مستقیم به شارژر
    | دسترسی ندارد. مرجع: C:\Project\Charger\docs\API.md
    */
    'ocpp' => [
        'api_url' => rtrim((string) env('OCPP_API_URL', ''), '/'),
        'email' => env('OCPP_API_EMAIL', 'admin@ocpp.local'),
        'password' => env('OCPP_API_PASSWORD', 'Admin@123456'),
    ],

];
