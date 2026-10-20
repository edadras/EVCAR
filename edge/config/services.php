<?php

return [

    'cloud' => [
        'api_url' => env('CLOUD_API_URL', 'http://localhost:8180'),
        'sync_token' => env('CLOUD_SYNC_TOKEN'),
        'app_key' => env('CLOUD_APP_KEY'),
    ],

    'gsm' => [
        'driver' => env('GSM_DRIVER', 'log'),
    ],

];
