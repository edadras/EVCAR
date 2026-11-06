<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EvOperator;
use App\Models\ExternalStation;
use App\Models\Station;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExternalStationController extends Controller
{
    public function index(Request $request): View
    {
        $operators = EvOperator::orderBy('sort_order')->orderBy('name')->get();
        $query = ExternalStation::with('operator')
            ->when($request->filled('operator'), fn ($q) => $q->where('ev_operator_id', $request->integer('operator')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q')->trim().'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('address', 'like', $term)->orWhere('city', 'like', $term));
            });

        $markers = (clone $query)->get()->map(fn (ExternalStation $s) => [
            'name' => $s->name,
            'lat' => $s->latitude,
            'lng' => $s->longitude,
            'logo' => $s->operator?->logoUrl(),
            'operator' => $s->operator?->name,
            'active' => $s->is_active,
            'url' => route('admin.external-stations.edit', $s),
        ])->values();

        return view('admin.external-stations.index', [
            'stations' => $query->orderBy('ev_operator_id')->orderBy('name')->paginate(40)->withQueryString(),
            'operators' => $operators,
            'markers' => $markers,
            'ourMarkers' => $this->ourStations(),
            'total' => ExternalStation::count(),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(null, $request->integer('operator') ?: null);
    }

    public function store(Request $request): RedirectResponse
    {
        ExternalStation::create($this->validated($request) + ['source' => 'manual']);

        return redirect()->route('admin.external-stations.index')->with('success', 'ایستگاه به نقشه اضافه شد.');
    }

    public function edit(ExternalStation $externalStation): View
    {
        return $this->form($externalStation);
    }

    public function update(Request $request, ExternalStation $externalStation): RedirectResponse
    {
        $externalStation->update($this->validated($request));

        return redirect()->route('admin.external-stations.index')->with('success', 'ایستگاه به‌روزرسانی شد.');
    }

    public function destroy(ExternalStation $externalStation): RedirectResponse
    {
        $externalStation->delete();

        return redirect()->route('admin.external-stations.index')->with('success', 'ایستگاه از نقشه حذف شد.');
    }

    private function form(?ExternalStation $station, ?int $operatorId = null): View
    {
        return view('admin.external-stations.form', [
            'station' => $station,
            'operators' => EvOperator::orderBy('sort_order')->orderBy('name')->get(),
            'selectedOperator' => $station?->ev_operator_id ?? $operatorId,
            'ourMarkers' => $this->ourStations(),
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function ourStations(): array
    {
        return Station::whereNotNull('latitude')->whereNotNull('longitude')
            ->get(['name', 'latitude', 'longitude'])
            ->map(fn ($s) => ['name' => $s->name, 'lat' => (float) $s->latitude, 'lng' => (float) $s->longitude])
            ->values()->all();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $connectors = array_keys(ExternalStation::CONNECTORS);
        $data = $request->validate([
            'ev_operator_id' => ['required', Rule::exists('ev_operators', 'id')],
            'name' => ['required', 'string', 'max:160'],
            'latitude' => ['required', 'numeric', 'between:24,40.5'],
            'longitude' => ['required', 'numeric', 'between:43,64'],
            'city' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'dc_connectors' => ['nullable', 'array'],
            'dc_connectors.*' => [Rule::in($connectors)],
            'ac_connectors' => ['nullable', 'array'],
            'ac_connectors.*' => [Rule::in($connectors)],
            'dc_max_kw' => ['nullable', 'numeric', 'min:1', 'max:600'],
            'ac_max_kw' => ['nullable', 'numeric', 'min:1', 'max:44'],
            'ports' => ['nullable', 'integer', 'min:1', 'max:200'],
            'hours' => ['nullable', 'string', 'max:120'],
            'facilities' => ['nullable', 'string', 'max:500'],
            'tariff_toman' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'latitude.between' => 'موقعیت باید داخل ایران باشد.',
            'longitude.between' => 'موقعیت باید داخل ایران باشد.',
        ]);
        $data['dc_connectors'] = array_values($data['dc_connectors'] ?? []) ?: null;
        $data['ac_connectors'] = array_values($data['ac_connectors'] ?? []) ?: null;
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
