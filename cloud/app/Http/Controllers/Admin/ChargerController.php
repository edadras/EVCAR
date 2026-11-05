<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charger;
use App\Models\Connector;
use App\Models\QrToken;
use App\Models\Station;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChargerController extends Controller
{
    public const CONNECTOR_STATUSES = ['active' => 'فعال', 'inactive' => 'غیرفعال', 'maintenance' => 'تعمیرات'];

    public function create(Station $station): View
    {
        return view('admin.chargers.create', ['station' => $station, 'charger' => null]);
    }

    public function store(Request $request, Station $station): RedirectResponse
    {
        $data = $this->validated($request);

        $charger = DB::transaction(function () use ($data, $station) {
            $charger = $station->chargers()->create([
                'charge_point_id' => $data['charge_point_id'],
                'vendor' => $data['vendor'] ?? null,
                'model' => $data['model'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'max_power_kw' => $data['max_power_kw'] ?? null,
                'connector_type' => $data['connector_type'] ?? null,
                // وضعیت اداری فعال؛ آنلاین بودن را خود اتصال OCPP تعیین می‌کند.
                'status' => 'active',
                'is_online' => false,
                'connection_mode' => $data['connection_mode'] ?? 'auto',
                'meta' => array_filter([
                    'name' => $data['name'] ?? null,
                    'ocpp_password' => $data['ocpp_password'] ?? null,
                ]),
            ]);
            $this->addConnectors($charger, 1, (int) $data['connector_count']);

            return $charger;
        });

        return redirect()
            ->route('admin.chargers.show', $charger)
            ->with('success', 'شارژر و کانکتورها ثبت شدند.');
    }

    public function show(Charger $charger): View
    {
        $charger->load(['station', 'connectors']);
        $qr = QrToken::whereIn('connector_id', $charger->connectors->pluck('id'))->where('status', 'active')->get()->keyBy('connector_id');

        return view('admin.chargers.show', compact('charger', 'qr'));
    }

    public function edit(Charger $charger): View
    {
        $charger->load(['station', 'connectors']);

        return view('admin.chargers.create', ['station' => $charger->station, 'charger' => $charger]);
    }

    public function update(Request $request, Charger $charger): RedirectResponse
    {
        $charger->load('connectors');
        $data = $this->validated($request, $charger);

        DB::transaction(function () use ($data, $charger) {
            $meta = $charger->meta ?? [];
            $meta['name'] = $data['name'] ?? null;
            if (! empty($data['ocpp_password'])) {
                $meta['ocpp_password'] = $data['ocpp_password'];
            } elseif (! empty($data['clear_ocpp_password'])) {
                unset($meta['ocpp_password']);
            }

            $charger->update([
                'charge_point_id' => $data['charge_point_id'],
                'vendor' => $data['vendor'] ?? null,
                'model' => $data['model'] ?? null,
                'serial_number' => $data['serial_number'] ?? null,
                'max_power_kw' => $data['max_power_kw'] ?? null,
                'connector_type' => $data['connector_type'] ?? $charger->connector_type,
                'status' => $data['status'] ?? $charger->status,
                'connection_mode' => $data['connection_mode'] ?? $charger->connection_mode,
                'meta' => array_filter($meta, fn ($v) => $v !== null && $v !== ''),
            ]);

            foreach ($charger->connectors as $cn) {
                $row = $data['connectors'][$cn->id] ?? null;
                if (! $row) {
                    continue;
                }
                $cmeta = $cn->meta ?? [];
                $cmeta['name'] = $row['name'] ?? null;
                $cn->update([
                    'max_power_kw' => $row['max_power_kw'] ?? null,
                    'operational_status' => $row['operational_status'] ?? $cn->operational_status,
                    'meta' => array_filter($cmeta, fn ($v) => $v !== null && $v !== ''),
                ]);
            }

            $current = (int) $charger->connectors->max('connector_number');
            if ((int) $data['connector_count'] > $current) {
                $this->addConnectors($charger, $current + 1, (int) $data['connector_count']);
            }
        });

        return redirect()
            ->route('admin.chargers.show', $charger)
            ->with('success', 'تنظیمات شارژر ذخیره شد.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Charger $charger = null): array
    {
        $minCount = $charger ? max(1, (int) $charger->connectors->max('connector_number')) : 1;

        return $request->validate([
            'charge_point_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._\-]+$/',
                Rule::unique('chargers', 'charge_point_id')->ignore($charger?->id)],
            'name' => ['nullable', 'string', 'max:80'],
            'vendor' => ['nullable', 'string', 'max:128'],
            'model' => ['nullable', 'string', 'max:128'],
            'serial_number' => ['nullable', 'string', 'max:128'],
            'max_power_kw' => ['nullable', 'integer', 'min:1', 'max:500'],
            'connector_type' => ['nullable', 'in:'.implode(',', array_keys(Charger::CONNECTOR_TYPES))],
            'connector_count' => ['required', 'integer', 'min:'.$minCount, 'max:4'],
            'connection_mode' => ['nullable', 'in:auto,edge,direct'],
            'status' => ['nullable', 'in:active,inactive,maintenance'],
            'ocpp_password' => ['nullable', 'string', 'min:8', 'max:40'],
            'clear_ocpp_password' => ['nullable', 'boolean'],
            'connectors' => ['nullable', 'array'],
            'connectors.*.name' => ['nullable', 'string', 'max:40'],
            'connectors.*.max_power_kw' => ['nullable', 'integer', 'min:1', 'max:500'],
            'connectors.*.operational_status' => ['nullable', 'in:'.implode(',', array_keys(self::CONNECTOR_STATUSES))],
        ], [
            'connector_count.min' => 'تعداد کانکتور را نمی‌توان کم کرد؛ کانکتور اضافه را «غیرفعال» کنید.',
            'charge_point_id.regex' => 'شناسه فقط حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط.',
        ]);
    }

    private function addConnectors(Charger $charger, int $from, int $to): void
    {
        for ($i = $from; $i <= $to; $i++) {
            $cn = Connector::create([
                'charger_id' => $charger->id,
                'connector_number' => $i,
                'ocpp_status' => 'Unavailable',
                'operational_status' => 'active',
                'max_power_kw' => $charger->max_power_kw,
            ]);
            $qr = QrToken::create([
                'station_id' => $charger->station_id,
                'charger_id' => $charger->id,
                'connector_id' => $cn->id,
                'offline_capable' => true,
                'status' => 'active',
            ]);
            $cn->update(['qr_token_uuid' => $qr->uuid]);
        }
    }
}
