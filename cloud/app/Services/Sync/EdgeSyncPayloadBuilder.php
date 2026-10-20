<?php

namespace App\Services\Sync;

use App\Enums\UserRole;
use App\Models\Charger;
use App\Models\EdgeStation;
use App\Models\QrToken;
use App\Models\RfidCard;
use App\Models\Station;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EdgeSyncPayloadBuilder
{
    /** @return array<string, mixed> */
    public function buildSnapshot(EdgeStation $edge): array
    {
        $station = $edge->station()->with(['chargers.connectors'])->firstOrFail();

        $users = User::query()
            ->where('role', UserRole::Customer)
            ->where('status', 'active')
            ->with(['wallet', 'vehicles', 'rfidCards'])
            ->orderBy('id')
            ->get();

        return $this->wrapPayload($edge, $station, $users, $this->tariffs(), $this->qrTokens($station));
    }

    /** @return array<string, mixed> */
    public function buildDelta(EdgeStation $edge, Carbon $since): array
    {
        $station = $edge->station()->with(['chargers.connectors'])->firstOrFail();

        $userIds = collect()
            ->merge(User::query()->where('role', UserRole::Customer)->where('updated_at', '>', $since)->pluck('id'))
            ->merge(Wallet::query()->where('updated_at', '>', $since)->pluck('user_id'))
            ->merge(Vehicle::withTrashed()->where('updated_at', '>', $since)->pluck('user_id'))
            ->merge(RfidCard::withTrashed()->where('updated_at', '>', $since)->pluck('user_id'))
            ->unique()
            ->filter();

        $users = $userIds->isEmpty()
            ? collect()
            : User::query()
                ->whereIn('id', $userIds)
                ->with(['wallet', 'vehicles', 'rfidCards'])
                ->get();

        $chargersChanged = $station->chargers->filter(
            fn ($ch) => $ch->updated_at > $since || $ch->connectors->contains(fn ($cn) => $cn->updated_at > $since)
        );

        $tariffs = Tariff::query()
            ->where('status', 'active')
            ->where('updated_at', '>', $since)
            ->get();

        $qrTokens = QrToken::query()
            ->where('station_id', $station->id)
            ->where('updated_at', '>', $since)
            ->with(['charger', 'connector'])
            ->get();

        $payload = $this->wrapPayload($edge, $station, $users, $tariffs, $qrTokens);

        if ($chargersChanged->isNotEmpty()) {
            $payload['chargers'] = $this->serializeChargers($chargersChanged);
        } else {
            $payload['chargers'] = [];
        }

        $payload['removed'] = [
            'vehicles' => Vehicle::onlyTrashed()->where('deleted_at', '>', $since)->pluck('uuid')->values()->all(),
            'rfid_cards' => RfidCard::onlyTrashed()->where('deleted_at', '>', $since)->pluck('uuid')->values()->all(),
            'tariffs' => Tariff::withTrashed()
                ->where('updated_at', '>', $since)
                ->where(fn ($q) => $q->where('status', '!=', 'active')->orWhereNotNull('deleted_at'))
                ->pluck('uuid')->values()->all(),
        ];

        $payload['mode'] = 'delta';
        $payload['since'] = $since->toIso8601String();

        if ($edge->updated_at > $since) {
            $payload['edge_config'] = $this->edgeConfig($edge);
        }

        return $payload;
    }

    /** @return array<string, mixed> */
    private function edgeConfig(EdgeStation $edge): array
    {
        $meta = $edge->meta ?? [];

        return [
            'anpr' => $meta['anpr'] ?? null,
            'anpr_cameras' => $meta['anpr_cameras'] ?? null,
            'capabilities' => $edge->capabilities ?? [],
        ];
    }

    /** @param  Collection<int, User>  $users */
    /** @param  Collection<int, Tariff>  $tariffs */
    /** @param  Collection<int, QrToken>  $qrTokens */
    private function wrapPayload(
        EdgeStation $edge,
        Station $station,
        Collection $users,
        Collection $tariffs,
        Collection $qrTokens,
    ): array {
        $assignedTariffIds = DB::table('station_tariffs')
            ->where('station_id', $station->id)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->pluck('tariff_id')
            ->all();

        return [
            'mode' => 'snapshot',
            'station' => [
                'uuid' => $station->uuid,
                'code' => $station->code,
                'name' => $station->name,
                'wifi_ssid' => $station->wifi_ssid,
                'local_hostname' => $station->local_hostname,
                'grid_limit_kw' => isset($station->meta['grid_limit_kw']) ? (float) $station->meta['grid_limit_kw'] : null,
            ],
            'edge' => [
                'uuid' => $edge->uuid,
                'edge_code' => $edge->edge_code,
            ],
            'edge_config' => $this->edgeConfig($edge),
            'users' => $users->map(fn (User $u) => $this->serializeUser($u))->values()->all(),
            'chargers' => $this->serializeChargers($station->chargers),
            'tariffs' => $tariffs->map(fn ($t) => $this->serializeTariff($t, $assignedTariffIds))->values()->all(),
            'qr_tokens' => $qrTokens->map(fn ($qr) => $this->serializeQrToken($qr))->values()->all(),
            'synced_at' => now()->toIso8601String(),
            'cursor' => now()->toIso8601String(),
        ];
    }

    /** @return Collection<int, Tariff> */
    private function tariffs(): Collection
    {
        return Tariff::query()->where('status', 'active')->get();
    }

    /** @return Collection<int, QrToken> */
    private function qrTokens(Station $station): Collection
    {
        return QrToken::query()
            ->where('station_id', $station->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->with(['charger', 'connector'])
            ->get();
    }

    /** @return array<string, mixed> */
    private function serializeUser(User $u): array
    {
        return [
            'uuid' => $u->uuid,
            'phone' => $u->phone,
            'name' => $u->name,
            'status' => $u->status,
            'wallet' => $u->wallet ? [
                'uuid' => $u->wallet->uuid,
                'balance' => (float) $u->wallet->balance,
                'credit_limit' => (float) $u->wallet->credit_limit,
                'bonus_balance' => (float) ($u->wallet->bonus_balance ?? 0),
                'currency' => $u->wallet->currency ?? 'IRR',
            ] : null,
            'vehicles' => $u->vehicles->map(fn ($v) => $this->serializeVehicle($v))->values()->all(),
            'rfid_cards' => $u->rfidCards->map(fn ($c) => $this->serializeRfid($c))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeVehicle(Vehicle $v): array
    {
        return [
            'uuid' => $v->uuid,
            'plate_two' => $v->plate_two,
            'plate_letter' => $v->plate_letter,
            'plate_three' => $v->plate_three,
            'plate_province' => $v->plate_province,
            'plate_normalized' => $v->plate_normalized,
            'brand' => $v->brand,
            'model' => $v->model,
            'battery_kwh' => $v->battery_kwh,
            'is_default' => $v->is_default,
            'deleted_at' => $v->deleted_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeRfid(RfidCard $c): array
    {
        return [
            'uuid' => $c->uuid,
            'id_tag' => $c->id_tag,
            'card_label' => $c->card_label,
            'status' => $c->status,
            'deleted_at' => $c->deleted_at?->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    /** @param  list<int>  $assignedTariffIds */
    private function serializeTariff(Tariff $t, array $assignedTariffIds = []): array
    {
        return [
            'is_default' => (bool) $t->is_default,
            'station_assigned' => in_array($t->id, $assignedTariffIds, true),
            'uuid' => $t->uuid,
            'name' => $t->name,
            'pricing_model' => $t->pricing_model,
            'price_per_kwh' => $t->price_per_kwh,
            'price_per_minute' => $t->price_per_minute,
            'session_fee' => $t->session_fee,
            'meta' => array_intersect_key($t->meta ?? [], array_flip(['idle_fee_per_minute', 'idle_grace_minutes'])),
            'rules' => $t->rules()->get()->map->only([
                'rule_type', 'day_of_week', 'start_time', 'end_time', 'price_per_kwh', 'price_per_minute', 'multiplier', 'priority',
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function serializeQrToken(QrToken $qr): array
    {
        return [
            'uuid' => $qr->uuid,
            'token' => $qr->token,
            'status' => $qr->status,
            'offline_capable' => (bool) $qr->offline_capable,
            'expires_at' => $qr->expires_at?->toIso8601String(),
            'cloud_charger_uuid' => $qr->charger?->uuid,
            'cloud_connector_uuid' => $qr->connector?->uuid,
            'connector_number' => $qr->connector?->connector_number,
            'deep_link' => $qr->deep_link,
        ];
    }

    /** @param  Collection<int, Charger>  $chargers */
    private function serializeChargers(Collection $chargers): array
    {
        return $chargers->map(fn ($ch) => [
            'uuid' => $ch->uuid,
            'charge_point_id' => $ch->charge_point_id,
            'max_power_kw' => $ch->max_power_kw,
            'is_online' => $ch->is_online,
            'connector_type' => $ch->connector_type,
            // رمز Basic Auth شارژر (OCPP Security Profile 1) برای بررسی روی Edge
            'ocpp_password' => $ch->meta['ocpp_password'] ?? null,
            'connectors' => $ch->connectors->map(fn ($cn) => [
                'uuid' => $cn->uuid,
                'connector_number' => $cn->connector_number,
                'ocpp_status' => $cn->ocpp_status,
                'max_power_kw' => $cn->max_power_kw,
            ])->values()->all(),
        ])->values()->all();
    }
}
