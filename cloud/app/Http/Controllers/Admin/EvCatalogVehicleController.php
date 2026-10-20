<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvCatalogVehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EvCatalogVehicleController extends Controller
{
    public function index(): View
    {
        $vehicles = EvCatalogVehicle::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.ev-catalog.index', [
            'vehicles' => $vehicles,
            'fromDatabase' => $vehicles->isNotEmpty(),
        ]);
    }

    public function create(): View
    {
        return view('admin.ev-catalog.form', ['vehicle' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        EvCatalogVehicle::create($data);

        return redirect()
            ->route('admin.ev-catalog.index')
            ->with('success', 'مدل خودرو به کاتالوگ اضافه شد.');
    }

    public function edit(EvCatalogVehicle $evCatalog): View
    {
        return view('admin.ev-catalog.form', ['vehicle' => $evCatalog]);
    }

    public function update(Request $request, EvCatalogVehicle $evCatalog): RedirectResponse
    {
        $evCatalog->update($this->validated($request, $evCatalog));

        return redirect()
            ->route('admin.ev-catalog.index')
            ->with('success', 'مدل خودرو به‌روزرسانی شد.');
    }

    public function destroy(EvCatalogVehicle $evCatalog): RedirectResponse
    {
        $evCatalog->delete();

        return redirect()
            ->route('admin.ev-catalog.index')
            ->with('success', 'مدل خودرو از کاتالوگ حذف شد.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?EvCatalogVehicle $vehicle = null): array
    {
        $rules = [
            'slug' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('ev_catalog_vehicles', 'slug')->ignore($vehicle?->id),
            ],
            'name' => ['required', 'string', 'max:120'],
            'brand' => ['nullable', 'string', 'max:80'],
            'model' => ['nullable', 'string', 'max:80'],
            'battery_kwh' => ['required', 'numeric', 'min:10', 'max:200'],
            'mass_kg' => ['required', 'integer', 'min:800', 'max:3500'],
            'regen_efficiency' => ['required', 'numeric', 'min:0.3', 'max:0.95'],
            'cd' => ['nullable', 'numeric', 'min:0.15', 'max:0.5'],
            'frontal_area_m2' => ['nullable', 'numeric', 'min:1.5', 'max:4'],
            'consumption_base' => ['nullable', 'numeric', 'min:8', 'max:45'],
            'range_real_max_km' => ['required', 'integer', 'min:50', 'max:1200'],
            'consumption_worst' => ['nullable', 'numeric', 'min:8', 'max:50'],
            'range_wltp_km' => ['nullable', 'integer', 'min:50', 'max:1200'],
            'range_real_min_km' => ['nullable', 'integer', 'min:50', 'max:1200'],
            'range_real_max_km' => ['nullable', 'integer', 'min:50', 'max:1200'],
            'range_worst_km' => ['nullable', 'integer', 'min:50', 'max:1200'],
            'aliases' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['nullable', 'boolean'],
        ];

        $data = $request->validate($rules);

        $aliases = collect(preg_split('/[\r\n,]+/u', $data['aliases'] ?? '') ?: [])
            ->map(fn (string $a) => trim($a))
            ->filter()
            ->values()
            ->all();

        $batteryKwh = (float) $data['battery_kwh'];
        $rangeRealMax = isset($data['range_real_max_km']) ? (int) $data['range_real_max_km'] : 0;
        $consumptionBase = $rangeRealMax > 0
            ? EvCatalogVehicle::consumptionBaseFromRange($batteryKwh, $rangeRealMax)
            : (float) $data['consumption_base'];

        $consumption = ['base' => $consumptionBase];
        if (! empty($data['consumption_worst'])) {
            $consumption['worst'] = (float) $data['consumption_worst'];
        }

        return [
            'uuid' => $vehicle?->uuid ?? (string) Str::uuid(),
            'slug' => Str::lower($data['slug']),
            'name' => $data['name'],
            'brand' => $data['brand'] ?: null,
            'model' => $data['model'] ?: null,
            'battery_kwh' => $data['battery_kwh'],
            'mass_kg' => (int) $data['mass_kg'],
            'regen_efficiency' => $data['regen_efficiency'],
            'cd' => $data['cd'] ?? null,
            'frontal_area_m2' => $data['frontal_area_m2'] ?? null,
            'consumption_base' => $consumptionBase,
            'range_wltp_km' => $data['range_wltp_km'] ?? null,
            'range_real_min_km' => $data['range_real_min_km'] ?? null,
            'range_real_max_km' => $data['range_real_max_km'] ?? null,
            'range_worst_km' => $data['range_worst_km'] ?? null,
            'consumption' => $consumption,
            'aliases' => $aliases,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
