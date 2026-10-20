<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('panel.dashboard');
});

Route::get('/api', function () {
    return response()->json([
        'app' => config('app.name'),
        'role' => 'edge',
        'panel' => route('panel.dashboard'),
        'health' => url('/api/v1/health'),
    ]);
});

require __DIR__.'/panel.php';
