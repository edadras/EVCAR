<?php

namespace App\Http\Controllers\Ocpi;

use App\Http\Controllers\Controller;
use App\Models\Charger;
use App\Models\ChargingSession;
use App\Models\Connector;
use App\Models\Station;
use App\Models\Tariff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OCPI 2.2.1 — نقش CPO، فقط خواندنی (Locations، Tariffs، Sessions) برای رومینگ با شبکه‌های دیگر.
 * احراز: هدر «Authorization: Token <توکن>» که در OCPI_TOKENS تعریف شده است.
 */
class OcpiController extends Controller
{
    private const VERSION = '2.2.1';

    public function versions(): JsonResponse
    {
        return $this->ok([['version' => self::VERSION, 'url' => url('/api/ocpi/'.self::VERSION)]]);
    }

    public function details(): JsonResponse
    {
        $base = url('/api/ocpi/'.self::VERSION);

        return $this->ok([
            'version' => self::VERSION,
            'endpoints' => [
                ['identifier' => 'locations', 'role' => 'SENDER', 'url' => $base.'/locations'],
                ['identifier' => 'tariffs', 'role' => 'SENDER', 'url' => $base.'/tariffs'],
                ['identifier' => 'sessions', 'role' => 'SENDER', 'url' => $base.'/sessions'],
            ],
        ]);
    }

    public function locations(Request $request): JsonResponse
    {
        [$offset, $limit] = $this->paging($request);
        $query = Station::with('chargers.connectors')->where('status', '!=', 'inactive')
            ->when($request->query('date_from'), fn ($q, $d) => $q->where('updated_at', '>=', $d))
            ->orderBy('id');
        $total = (clone $query)->count();

        return $this->ok($query->skip($offset)->take($limit)->get()->map(fn (Station $s) => $this->location($s))->values()->all(), $total, $limit);
    }

    public function showLocation(string $code): JsonResponse
    {
        $model = Station::with('chargers.connectors')->where('code', $code)->first();

        return $model ? $this->ok($this->location($model)) : $this->error(2003, 'Unknown location', 404);
    }

    /** @return array<string, mixed> */
    private function location(Station $station): array
    {
        return [
            'country_code' => config('safircharge.ocpi.country_code', 'IR'),
            'party_id' => config('safircharge.ocpi.party_id', 'SFC'),
            'id' => $station->code,
            'publish' => true,
            'name' => $station->name,
            'address' => (string) ($station->address ?? $station->name),
            'city' => (string) ($station->city ?? ''),
            'country' => 'IRN',
            'coordinates' => [
                'latitude' => number_format((float) $station->latitude, 6, '.', ''),
                'longitude' => number_format((float) $station->longitude, 6, '.', ''),
            ],
            'time_zone' => config('safircharge.timezone', 'Asia/Tehran'),
            'evses' => $station->chargers->flatMap(fn (Charger $ch) => $ch->connectors->map(fn (Connector $cn) => [
                'uid' => $ch->charge_point_id.'-'.$cn->connector_number,
                'evse_id' => 'IR*SFC*E'.preg_replace('/\W/', '', $ch->charge_point_id).'*'.$cn->connector_number,
                'status' => $this->evseStatus($cn->ocpp_status, (bool) $ch->is_online),
                'connectors' => [[
                    'id' => (string) $cn->connector_number,
                    'standard' => match (strtoupper((string) $ch->connector_type)) {
                        'CCS2', 'CCS' => 'IEC_62196_T2_COMBO',
                        'CHADEMO' => 'CHADEMO',
                        'GB/T', 'GBT' => 'GBT_DC',
                        default => 'IEC_62196_T2',
                    },
                    'format' => in_array(strtoupper((string) $ch->connector_type), ['CCS2', 'CCS', 'CHADEMO', 'GBT', 'GB/T'], true) ? 'CABLE' : 'SOCKET',
                    'power_type' => (int) $ch->max_power_kw >= 50 ? 'DC' : 'AC_3_PHASE',
                    'max_voltage' => (int) $ch->max_power_kw >= 50 ? 920 : 400,
                    'max_amperage' => (int) $ch->max_power_kw >= 50 ? 200 : 32,
                    'max_electric_power' => (int) (($cn->max_power_kw ?: $ch->max_power_kw ?: 22) * 1000),
                    'last_updated' => ($cn->updated_at ?? now())->toIso8601ZuluString(),
                ]],
                'last_updated' => ($cn->status_updated_at ?? $cn->updated_at ?? now())->toIso8601ZuluString(),
            ]))->values()->all(),
            'last_updated' => ($station->updated_at ?? now())->toIso8601ZuluString(),
        ];
    }

    public function tariffs(): JsonResponse
    {
        $tariffs = Tariff::where('status', 'active')->get()->map(fn (Tariff $t) => [
            'country_code' => config('safircharge.ocpi.country_code', 'IR'),
            'party_id' => config('safircharge.ocpi.party_id', 'SFC'),
            'id' => $t->code,
            'currency' => 'IRR',
            'elements' => [[
                'price_components' => array_values(array_filter([
                    (float) $t->price_per_kwh > 0 ? ['type' => 'ENERGY', 'price' => (float) $t->price_per_kwh, 'step_size' => 1] : null,
                    (float) $t->price_per_minute > 0 ? ['type' => 'TIME', 'price' => (float) $t->price_per_minute * 60, 'step_size' => 60] : null,
                    (float) $t->session_fee > 0 ? ['type' => 'FLAT', 'price' => (float) $t->session_fee, 'step_size' => 1] : null,
                    (float) ($t->meta['idle_fee_per_minute'] ?? 0) > 0 ? ['type' => 'PARKING_TIME', 'price' => (float) $t->meta['idle_fee_per_minute'] * 60, 'step_size' => 60] : null,
                ])),
            ]],
            'last_updated' => ($t->updated_at ?? now())->toIso8601ZuluString(),
        ])->values()->all();

        return $this->ok($tariffs);
    }

    public function sessions(Request $request): JsonResponse
    {
        $request->validate(['date_from' => ['required', 'date']]);
        [$offset, $limit] = $this->paging($request);
        $query = ChargingSession::with(['station', 'charger', 'connector'])
            ->where('updated_at', '>=', $request->query('date_from'))
            ->when($request->query('date_to'), fn ($q, $d) => $q->where('updated_at', '<', $d))
            ->orderBy('id');
        $total = (clone $query)->count();

        $rows = $query->skip($offset)->take($limit)->get()->map(fn (ChargingSession $s) => [
            'country_code' => config('safircharge.ocpi.country_code', 'IR'),
            'party_id' => config('safircharge.ocpi.party_id', 'SFC'),
            'id' => $s->uuid,
            'start_date_time' => $s->started_at?->toIso8601ZuluString(),
            'end_date_time' => $s->stopped_at?->toIso8601ZuluString(),
            'kwh' => (float) $s->energy_kwh,
            'auth_method' => $s->auth_method === 'rfid' ? 'WHITELIST' : 'COMMAND',
            'location_id' => $s->station?->code,
            'evse_uid' => $s->charger ? $s->charger->charge_point_id.'-'.($s->connector?->connector_number ?? 1) : null,
            'connector_id' => (string) ($s->connector?->connector_number ?? 1),
            'currency' => 'IRR',
            'total_cost' => ['excl_vat' => round((float) $s->cost_amount / (1 + config('safircharge.finance.vat_percent', 10) / 100), 2), 'incl_vat' => (float) $s->cost_amount],
            'status' => $s->status === 'active' ? 'ACTIVE' : ((float) $s->energy_kwh > 0 ? 'COMPLETED' : 'INVALID'),
            'last_updated' => ($s->updated_at ?? now())->toIso8601ZuluString(),
        ])->values()->all();

        return $this->ok($rows, $total, $limit);
    }

    private function evseStatus(?string $ocpp, bool $online): string
    {
        if (! $online) {
            return 'UNKNOWN';
        }

        return match ($ocpp) {
            'Available' => 'AVAILABLE',
            'Preparing', 'Charging', 'SuspendedEV', 'SuspendedEVSE', 'Finishing' => 'CHARGING',
            'Reserved' => 'RESERVED',
            'Faulted' => 'OUTOFORDER',
            'Unavailable' => 'INOPERATIVE',
            default => 'UNKNOWN',
        };
    }

    /** @return array{0:int,1:int} */
    private function paging(Request $request): array
    {
        return [max(0, (int) $request->query('offset', 0)), min(100, max(1, (int) $request->query('limit', 50)))];
    }

    private function ok(mixed $data, ?int $total = null, ?int $limit = null): JsonResponse
    {
        $response = response()->json([
            'data' => $data,
            'status_code' => 1000,
            'status_message' => 'Success',
            'timestamp' => now()->toIso8601ZuluString(),
        ]);
        if ($total !== null) {
            $response->headers->set('X-Total-Count', (string) $total);
            $response->headers->set('X-Limit', (string) $limit);
        }

        return $response;
    }

    private function error(int $code, string $message, int $http): JsonResponse
    {
        return response()->json(['status_code' => $code, 'status_message' => $message, 'timestamp' => now()->toIso8601ZuluString()], $http);
    }
}
