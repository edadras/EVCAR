<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charger;
use App\Models\Partner;
use App\Models\Station;
use App\Models\Tariff;
use App\Services\Admin\AdminMetricsService;
use App\Services\Admin\AdminViewDataService;
use App\Services\Stations\StationAnprService;
use App\Support\Persian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StationController extends Controller
{
    private const IMAGE_RULE = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'];

    private const TARIFF_KEEP = 'keep';

    /** @return array<string, array<int, mixed>> */
    private function profileRules(): array
    {
        return [
            'hours_preset' => ['nullable', 'in:'.implode(',', array_keys(Station::HOURS_PRESETS)).',custom'],
            'hours_open' => ['nullable', 'required_if:hours_preset,custom', 'date_format:H:i'],
            'hours_close' => ['nullable', 'required_if:hours_preset,custom', 'date_format:H:i'],
            'tariff_id' => ['nullable', 'string'],
            'amenities' => ['nullable', 'array'],
            'amenities.*' => ['string', 'in:'.implode(',', array_keys(Station::AMENITIES))],
        ];
    }

    /**
     * ساعات کاری و امکانات را روی $data می‌نویسد و کلیدهای فرم را حذف می‌کند.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyProfile(array $data, array $meta): array
    {
        $preset = $data['hours_preset'] ?? '24h';
        if ($preset === 'custom') {
            $open = $data['hours_open'];
            $close = $data['hours_close'];
            $data['operating_hours'] = [
                'preset' => 'custom',
                'label' => Persian::digits($open).' تا '.Persian::digits($close),
                'open' => $open,
                'close' => $close,
            ];
        } else {
            $p = Station::HOURS_PRESETS[$preset] ?? Station::HOURS_PRESETS['24h'];
            $data['operating_hours'] = ['preset' => $preset] + $p;
        }

        $amenities = array_values(array_intersect(array_keys(Station::AMENITIES), $data['amenities'] ?? []));
        if ($amenities) {
            $meta['amenities'] = $amenities;
        } else {
            unset($meta['amenities']);
        }
        $data['meta'] = $meta;

        unset($data['hours_preset'], $data['hours_open'], $data['hours_close'], $data['amenities'], $data['tariff_id']);

        return $data;
    }

    private function syncTariff(Station $station, ?string $choice): void
    {
        if ($choice === self::TARIFF_KEEP) {
            return;
        }
        $id = $choice !== null && $choice !== '' ? (int) $choice : null;
        if ($id && ! Tariff::whereKey($id)->where('status', 'active')->exists()) {
            return;
        }
        $station->tariffs()->sync($id ? [$id] : []);
    }

    public function __construct(
        private AdminMetricsService $metrics,
        private StationAnprService $anpr,
    ) {}

    public function index(): View
    {
        return view('admin.stations.index', array_merge(
            $this->metrics->stationsIndex(),
            [
                'kpis' => $this->metrics->networkKpis(),
                'stationsJs' => app(AdminViewDataService::class)->stationsPageJs(),
            ]
        ));
    }

    public function create(): View
    {
        return view('admin.stations.create', [
            'partners' => Partner::orderBy('name')->get(),
            'tariffs' => Tariff::where('status', 'active')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:32', 'unique:stations,code'],
            'city' => ['nullable', 'string', 'max:64'],
            'province' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:public,private,semi_public'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'wifi_ssid' => ['nullable', 'string', 'max:64'],
            'local_hostname' => ['nullable', 'string', 'max:128'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'grid_limit_kw' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'image' => self::IMAGE_RULE,
        ] + $this->profileRules());
        $gridLimit = $data['grid_limit_kw'] ?? null;
        $tariffChoice = $data['tariff_id'] ?? null;
        unset($data['grid_limit_kw'], $data['image']);

        if (empty($data['code'])) {
            $data['code'] = 'ST-'.Str::upper(Str::random(6));
        }

        $data = $this->applyProfile($data, array_filter([
            'grid_limit_kw' => $gridLimit !== null ? (float) $gridLimit : null,
            'image' => $this->storeImage($request, $data['code']),
        ], fn ($v) => $v !== null));
        $station = Station::create($data);
        $this->syncTariff($station, $tariffChoice);

        return redirect()
            ->route('admin.stations.show', $station)
            ->with('success', 'ایستگاه با موفقیت ایجاد شد.');
    }

    public function show(Station $station): View
    {
        $station->load(['chargers.connectors', 'edgeStation', 'partner']);

        return view('admin.stations.show', compact('station'));
    }

    public function edit(Station $station): View
    {
        $station->load(['edgeStation', 'chargers.connectors', 'tariffs']);

        return view('admin.stations.create', [
            'station' => $station,
            'partners' => Partner::orderBy('name')->get(),
            'tariffs' => Tariff::where('status', 'active')->orderBy('name')->get(),
            'anprCameras' => $station->edgeStation
                ? $this->anpr->camerasFor($station->edgeStation)
                : $this->anpr->defaultCameras($station->name),
            'anprEnabled' => $this->anpr->isEnabled($station->edgeStation),
        ]);
    }

    public function update(Request $request, Station $station): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:32', 'unique:stations,code,'.$station->id],
            'city' => ['nullable', 'string', 'max:64'],
            'province' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:500'],
            'type' => ['required', 'in:public,private,semi_public'],
            'status' => ['required', 'in:active,inactive,maintenance'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'wifi_ssid' => ['nullable', 'string', 'max:64'],
            'local_hostname' => ['nullable', 'string', 'max:128'],
            'partner_id' => ['nullable', 'exists:partners,id'],
            'grid_limit_kw' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'charger_types' => ['nullable', 'array'],
            'charger_types.*' => ['nullable', 'string', 'max:32'],
            'charger_power' => ['nullable', 'array'],
            'charger_power.*' => ['nullable', 'integer', 'min:1', 'max:500'],
            'image' => self::IMAGE_RULE,
            'remove_image' => ['nullable', 'boolean'],
        ] + $this->profileRules());
        $tariffChoice = $data['tariff_id'] ?? null;
        $gridLimit = $data['grid_limit_kw'] ?? null;
        $chargerTypes = $data['charger_types'] ?? [];
        $chargerPower = $data['charger_power'] ?? [];
        unset($data['grid_limit_kw'], $data['charger_types'], $data['charger_power'], $data['image'], $data['remove_image']);

        foreach ($station->chargers as $charger) {
            $changes = [];
            $type = $chargerTypes[$charger->id] ?? null;
            if ($type !== null && $type !== $charger->connector_type && array_key_exists($type, Charger::CONNECTOR_TYPES)) {
                $changes['connector_type'] = $type;
            }
            $power = $chargerPower[$charger->id] ?? null;
            if ($power !== null && (int) $power !== (int) $charger->max_power_kw) {
                $changes['max_power_kw'] = (int) $power;
            }
            if ($changes) {
                $charger->update($changes);
            }
        }

        $meta = $station->meta ?? [];
        if ($gridLimit !== null && (float) $gridLimit > 0) {
            $meta['grid_limit_kw'] = (float) $gridLimit;
        } else {
            unset($meta['grid_limit_kw']);
        }
        if ($image = $this->storeImage($request, $data['code'])) {
            $this->deleteImage($meta['image'] ?? null);
            $meta['image'] = $image;
        } elseif ($request->boolean('remove_image')) {
            $this->deleteImage($meta['image'] ?? null);
            unset($meta['image']);
        }
        $station->update($this->applyProfile($data, $meta));
        $this->syncTariff($station, $tariffChoice);

        if ($request->has('anpr_enabled')) {
            $cameras = $request->input('anpr_cameras', []);
            $this->anpr->saveConfig(
                $station->fresh(),
                $request->boolean('anpr_enabled'),
                is_array($cameras) ? $cameras : [],
            );
        }

        return redirect()
            ->route('admin.stations.show', $station)
            ->with('success', 'ایستگاه به‌روزرسانی شد.');
    }

    private function storeImage(Request $request, string $code): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }
        $dir = public_path(Station::IMAGE_DIR);
        File::ensureDirectoryExists($dir);
        $file = $request->file('image');
        $name = Str::slug($code).'-'.now()->format('YmdHis').'.'.$file->extension();
        $file->move($dir, $name);

        return '/'.Station::IMAGE_DIR.'/'.$name;
    }

    private function deleteImage(?string $path): void
    {
        if ($path && str_starts_with($path, '/'.Station::IMAGE_DIR.'/')) {
            File::delete(public_path(ltrim($path, '/')));
        }
    }
}
