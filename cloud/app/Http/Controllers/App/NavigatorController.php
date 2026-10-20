<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\Navigator\EvNavigatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NavigatorController extends Controller
{
    public function __construct(private EvNavigatorService $navigator) {}

    public function index(): View
    {
        return view('app.navigator', $this->navigator->pageData(auth()->user()));
    }

    public function analyze(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin.lat' => ['required', 'numeric', 'between:-90,90'],
            'origin.lng' => ['required', 'numeric', 'between:-180,180'],
            'origin.label' => ['nullable', 'string', 'max:120'],
            'destination.lat' => ['required', 'numeric', 'between:-90,90'],
            'destination.lng' => ['required', 'numeric', 'between:-180,180'],
            'destination.label' => ['nullable', 'string', 'max:120'],
            'current_soc' => ['nullable', 'integer', 'min:5', 'max:100'],
            'target_soc' => ['nullable', 'integer', 'min:5', 'max:90'],
            'charge_to_soc' => ['nullable', 'integer', 'min:30', 'max:100'],
            'vehicle_preset' => ['nullable', 'string', 'max:64'],
            'vehicle_uuid' => ['nullable', 'string', 'max:64'],
            'battery_kwh' => ['nullable', 'numeric', 'min:10', 'max:200'],
            'consumption_kwh100' => ['nullable', 'numeric', 'min:8', 'max:40'],
            'mass_kg' => ['nullable', 'numeric', 'min:800', 'max:3500'],
            'cd' => ['nullable', 'numeric', 'min:0.15', 'max:0.5'],
            'frontal_area_m2' => ['nullable', 'numeric', 'min:1.5', 'max:4'],
        ]);

        try {
            $result = $this->navigator->analyze(auth()->user(), $data);

            return response()->json($result);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'origin.lat' => ['required', 'numeric', 'between:-90,90'],
            'origin.lng' => ['required', 'numeric', 'between:-180,180'],
            'destination.lat' => ['required', 'numeric', 'between:-90,90'],
            'destination.lng' => ['required', 'numeric', 'between:-180,180'],
            'current_soc' => ['nullable', 'integer', 'min:5', 'max:100'],
            'target_soc' => ['nullable', 'integer', 'min:5', 'max:90'],
            'charge_to_soc' => ['nullable', 'integer', 'min:30', 'max:100'],
            'vehicle_preset' => ['nullable', 'string', 'max:64'],
            'vehicle_uuid' => ['nullable', 'string', 'max:64'],
            'battery_kwh' => ['nullable', 'numeric', 'min:10', 'max:200'],
            'consumption_kwh100' => ['nullable', 'numeric', 'min:8', 'max:40'],
        ]);

        try {
            return response()->json($this->navigator->previewRoute(
                auth()->user(),
                ['lat' => (float) $data['origin']['lat'], 'lng' => (float) $data['origin']['lng']],
                ['lat' => (float) $data['destination']['lat'], 'lng' => (float) $data['destination']['lng']],
                $data,
            ));
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
