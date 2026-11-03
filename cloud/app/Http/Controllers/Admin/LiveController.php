<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminLiveService;
use Illuminate\Http\JsonResponse;

class LiveController extends Controller
{
    public function __construct(private AdminLiveService $live) {}

    public function show(string $section): JsonResponse
    {
        $data = match ($section) {
            'nav' => $this->live->nav(),
            'dashboard' => $this->live->dashboard(),
            'monitoring' => $this->live->monitoring(),
            'sessions' => $this->live->sessions(),
            'stations' => $this->live->stations(),
        };

        return response()->json($data)->header('Cache-Control', 'no-store');
    }
}
