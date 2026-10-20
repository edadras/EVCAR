<?php

namespace App\Services\App;

use App\Models\AuditLog;
use App\Models\ChargingSession;
use App\Models\QrToken;
use App\Models\RfidCard;
use App\Models\Station;
use App\Models\SystemSetting;
use App\Models\Tariff;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WalletTransaction;
use App\Services\Charging\CloudChargingSessionService;
use App\Services\Charging\ConnectorAvailabilityService;
use App\Support\Geo;
use App\Support\Persian;
use Illuminate\Support\Collection;

class MobileAppPresenter
{
    public function __construct(
        private ConnectorAvailabilityService $availability,
        private CloudChargingSessionService $charging,
    ) {}

    public function initials(User $user): string
    {
        $parts = preg_split('/\s+/u', trim($user->name), -1, PREG_SPLIT_NO_EMPTY);

        return mb_substr($parts[0] ?? '؟', 0, 1).mb_substr($parts[1] ?? '', 0, 1);
    }

    public function formatPhone(?string $phone): string
    {
        if (! $phone) {
            return '—';
        }
        $digits = preg_replace('/\D+/', '', $phone);
        if (strlen($digits) === 11) {
            return Persian::digits(substr($digits, 0, 4).' '.substr($digits, 4, 3).' '.substr($digits, 7));
        }

        return Persian::digits($phone);
    }

    public function memberSince(User $user): string
    {
        return 'عضو از '.Persian::digits($user->created_at?->translatedFormat('F Y') ?? '—');
    }

    public function firstName(User $user): string
    {
        return explode(' ', $user->name)[0] ?? $user->name;
    }

    public function headerDate(?string $city = null): string
    {
        $line = now()->translatedFormat('l j F');
        if ($city) {
            $line .= ' · '.$city;
        }

        return $line;
    }

    /** @return array<int, array<string, mixed>> */
    public function stationCards(Collection $stations, ?float $refLat = null, ?float $refLng = null): array
    {
        $ref = Geo::defaultRef();
        $refLat ??= $ref['lat'];
        $refLng ??= $ref['lng'];

        return $stations->map(function (Station $st) use ($refLat, $refLng) {
            $connectors = $st->chargers->flatMap(fn ($ch) => $ch->connectors);
            $total = $connectors->count();
            $free = $connectors->filter(fn ($c) => $this->availability->snapshot($c)['reservable'])->count();
            $maxKw = (int) $st->chargers->max('max_power_kw');
            $isFast = $maxKw >= 50;
            $busy = $free === 0;
            $cat = $isFast ? 'fast' : 'public';
            $km = ($st->latitude && $st->longitude)
                ? Geo::haversineKm($refLat, $refLng, (float) $st->latitude, (float) $st->longitude)
                : null;

            return [
                'code' => $st->code,
                'name' => $st->name,
                'distance' => Geo::formatDistance($km) ?: ($st->city ?? '—'),
                'distance_km' => $km,
                'lat' => $st->latitude ? (float) $st->latitude : null,
                'lng' => $st->longitude ? (float) $st->longitude : null,
                'type' => ($isFast ? 'DC سریع' : 'AC').' · '.($st->chargers->pluck('connector_type')->filter()->unique()->sort()->implode(' / ') ?: 'Type2'),
                'free' => Persian::digits($free).' از '.Persian::digits($total).' آزاد',
                'free_count' => $free,
                'price' => $this->defaultTariffToman() !== null
                    ? Persian::digits($this->defaultTariffToman())
                    : '—',
                'status' => $busy ? 'پرتراکم' : 'آزاد',
                'status_pill' => $busy ? 'pill-amber' : 'pill-green',
                'cat' => $cat,
                'has_free' => $free > 0,
                'url' => route('app.charger', ['station' => $st->code]),
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function mapStations(Collection $stations, ?float $refLat = null, ?float $refLng = null): array
    {
        return $this->stationCards($stations, $refLat, $refLng);
    }

    /** @return array<int, array<string, mixed>> */
    public function mapMarkers(Collection $stations): array
    {
        return $stations
            ->filter(fn (Station $s) => $s->latitude && $s->longitude)
            ->map(function (Station $st) {
                $free = $this->stationFreeCount($st);
                $busy = $free === 0;

                return [
                    'id' => $st->id,
                    'name' => $st->name,
                    'lat' => (float) $st->latitude,
                    'lng' => (float) $st->longitude,
                    'free' => $free,
                    'color' => $busy ? 'amber' : 'green',
                    'url' => route('app.charger', ['station' => $st->code]),
                ];
            })
            ->values()
            ->all();
    }

    public function mapStats(Collection $stations): array
    {
        $connectors = $stations->flatMap(fn ($s) => $s->chargers->flatMap(fn ($ch) => $ch->connectors));
        $free = $connectors->filter(fn ($c) => $this->availability->snapshot($c)['reservable'])->count();

        return [
            'total' => $stations->count(),
            'free' => $free,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function walletTransactions(Collection $transactions): array
    {
        return $transactions->map(function (WalletTransaction $t) {
            $amount = (int) round((float) $t->amount / 10);
            $isIn = in_array($t->type, ['topup', 'credit', 'refund', 'bonus'], true);

            return [
                't' => $t->description ?: $t->typeLabel(),
                'd' => $t->occurred_at ? Persian::digits($t->occurred_at->format('Y/m/d H:i')) : '—',
                'a' => ($isIn ? '+' : '−').Persian::money(abs($amount)),
                'pos' => $isIn,
                'ic' => $isIn ? 'plus' : 'bolt',
                'c' => $isIn ? 'ic-green' : 'ic-blue',
                'ty' => $isIn ? 'in' : 'out',
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function historySessions(Collection $sessions): array
    {
        return $sessions->map(function (ChargingSession $s) {
            $ok = in_array($s->status, ['completed', 'stopped'], true) || $s->energy_kwh > 0;
            $auth = match ($s->auth_method) {
                'rfid' => 'کارت RFID',
                'qr' => 'QR موبایل',
                'wallet' => 'کیف پول',
                default => 'کیف پول',
            };

            return [
                'n' => $s->station?->name ?? '—',
                'date' => $s->started_at ? Persian::digits($s->started_at->format('Y/m/d H:i')) : '—',
                'en' => Persian::kwh($s->energy_kwh).' kWh',
                'dur' => $s->duration_seconds ? Persian::digits((int) ceil($s->duration_seconds / 60)).' دقیقه' : '—',
                'amt' => Persian::money((int) round((float) $s->cost_amount / 10)),
                'm' => $auth,
                'st' => [$ok ? 'تکمیل' : 'ناموفق', $ok ? 'pill-green' : 'pill-red'],
            ];
        })->values()->all();
    }

    public function monthStats(User $user): array
    {
        $start = now()->startOfMonth();
        $sessions = ChargingSession::where('user_id', $user->id)
            ->where('started_at', '>=', $start)
            ->get();
        $energy = (float) $sessions->sum('energy_kwh');
        $cost = (int) round($sessions->sum('cost_amount') / 10);

        return [
            'sessions' => $sessions->count(),
            'energy' => Persian::kwh($energy),
            'cost' => Persian::money($cost / 1_000_000).' م',
            'co2_kg' => Persian::digits((int) round($energy * 0.68)),
            'trees' => Persian::digits(max(1, (int) round($energy * 0.03))),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function chargerConnectors(Station $station): array
    {
        $letters = ['A', 'B', 'C', 'D'];
        $rows = [];
        foreach ($station->chargers as $ci => $charger) {
            foreach ($charger->connectors as $cn) {
                $snap = $this->availability->snapshot($cn);
                $busy = ! $snap['reservable'] || $cn->ocpp_status === 'Charging';
                $label = ($letters[$ci] ?? 'C').$cn->connector_number;
                $rows[] = [
                    'id' => $label,
                    'uuid' => $cn->uuid,
                    'db_id' => $cn->id,
                    't' => $charger->connector_type ?? 'Type2',
                    'pw' => Persian::digits((int) ($cn->max_power_kw ?: $charger->max_power_kw)).' kW',
                    'st' => $busy ? 'busy' : 'free',
                    'state' => $snap['state'],
                    'reason' => $snap['reason'],
                    'ic' => $busy ? 'ic-orange' : 'ic-green',
                    'name' => $label.' · '.($charger->connector_type ?? 'Type2').' · '.Persian::digits((int) ($cn->max_power_kw ?: $charger->max_power_kw)).' kW',
                ];
            }
        }

        return $rows;
    }

    public function stationFreeCount(Station $station): int
    {
        return $station->chargers->flatMap(fn ($ch) => $ch->connectors)
            ->filter(fn ($c) => $this->availability->snapshot($c)['reservable'])
            ->count();
    }

    public function defaultTariff(): ?Tariff
    {
        return Tariff::where('is_default', true)->where('status', 'active')->first()
            ?? Tariff::where('status', 'active')->first();
    }

    public function defaultTariffToman(): ?int
    {
        $tariff = $this->defaultTariff();

        if (! $tariff) {
            return null;
        }

        $price = $tariff->pricing_model === 'per_minute'
            ? $tariff->price_per_minute
            : $tariff->price_per_kwh;

        if (! $price || (float) $price <= 0) {
            return null;
        }

        return (int) round((float) $price / 10);
    }

    /** @return array{energy:int,idle:int,offpeak:int,offpeak_hours:string} */
    public function tariffBreakdown(): array
    {
        $tariff = $this->defaultTariff();
        $energy = $this->defaultTariffToman() ?? 0;
        if (! $tariff) {
            return [
                'energy' => 0,
                'idle' => 0,
                'offpeak' => 0,
                'offpeak_hours' => '—',
            ];
        }

        $idle = $tariff->price_per_minute
            ? (int) round((float) $tariff->price_per_minute / 10)
            : (int) round($energy * 0.15);

        $offpeakRule = $tariff->rules()->where('rule_type', 'off_peak')->first();
        $offpeak = $offpeakRule && $offpeakRule->price_per_kwh
            ? (int) round((float) $offpeakRule->price_per_kwh / 10)
            : (int) round($energy * 0.65);

        $hours = $tariff->meta['off_peak_hours'] ?? '00:00 تا 06:00';

        return [
            'energy' => $energy,
            'idle' => $idle,
            'offpeak' => $offpeak,
            'offpeak_hours' => Persian::digits($hours),
        ];
    }

    /** @return array{points_to_next:int,progress_pct:int,label:string} */
    public function loyaltyProgress(User $user): array
    {
        $completed = ChargingSession::where('user_id', $user->id)
            ->whereIn('status', ['completed', 'stopped'])
            ->count();
        $mod = $completed % 10;
        $pointsToNext = $mod === 0 ? 10 : 10 - $mod;
        $progressPct = (int) round(($mod / 10) * 100);

        return [
            'points_to_next' => $pointsToNext,
            'progress_pct' => $progressPct,
            'label' => Persian::digits($pointsToNext).' جلسه تا پاداش بعدی',
        ];
    }

    public function supportContact(): array
    {
        return [
            'phone' => SystemSetting::text('app', 'support_phone', '1570'),
            'faq' => json_decode(SystemSetting::text('app', 'support_faq', ''), true) ?: [
                ['q' => 'چگونه کیف پولم را شارژ کنم؟', 'a' => 'از صفحهٔ کیف پول، گزینهٔ «افزایش موجودی» را بزنید و مبلغ را از طریق درگاه بانکی پرداخت کنید.'],
                ['q' => 'شارژ خودکار با پلاک چگونه کار می‌کند؟', 'a' => 'پس از ثبت پلاک خودرو، با ورود به ایستگاه‌های مجهز به دوربین، شارژ خودکار آغاز و هزینه از کیف پول کسر می‌شود.'],
                ['q' => 'اگر شارژر خراب باشد چه کنم؟', 'a' => 'از همین صفحه درخواست پشتیبانی ثبت کنید یا با پشتیبانی تماس بگیرید.'],
                ['q' => 'آیا در حالت آفلاین می‌توانم شارژ کنم؟', 'a' => 'بله؛ با اتصال به WiFi ایستگاه می‌توانید شارژ کنید.'],
            ],
        ];
    }

    public function vehicleLine(Vehicle $v): string
    {
        $plate = trim("{$v->plate_two} {$v->plate_letter} {$v->plate_three} | {$v->plate_province}");

        $connector = $v->meta['connector_type'] ?? null;
        $line = trim("{$v->brand} {$v->model}");
        if ($connector) {
            $line .= ' · '.$connector;
        }
        $line .= ' · '.Persian::digits((int) $v->battery_kwh).' kWh · پلاک '.$plate;

        return $line;
    }

    public function rfidMasked(?RfidCard $card): string
    {
        if (! $card) {
            return '—';
        }

        return 'CN‑'.substr($card->id_tag, -4);
    }

    /** @return array<int, array<string, mixed>> */
    public function scanTargets(Collection $tokens): array
    {
        return $tokens->map(function (QrToken $token) {
            $connectorNo = $token->connector?->connector_number;
            $cp = $token->charger?->charge_point_id;

            return [
                'label' => ($cp ?? '').' / کانکتور '.($connectorNo ?? '—'),
                'url' => route('app.charger', [
                    'station' => $token->station?->code,
                    'connector' => $token->connector_id,
                    'qr' => $token->token,
                ]),
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function notificationsGrouped(Collection $notifications): array
    {
        return [
            'today' => $notifications->filter(fn ($n) => $n->sent_at?->isToday())->values(),
            'earlier' => $notifications->filter(fn ($n) => ! $n->sent_at?->isToday())->values(),
        ];
    }

    public function ticketStatusLabel(string $status): string
    {
        return match ($status) {
            'open' => 'باز',
            'in_review', 'in_progress' => 'در بررسی',
            'resolved' => 'حل‌شده',
            'closed' => 'بسته',
            default => $status,
        };
    }

    public function authMethodLabel(?string $method): string
    {
        return match ($method) {
            'rfid' => 'کارت RFID',
            'qr' => 'QR موبایل',
            'wallet' => 'کیف پول',
            'otp' => 'رمز یکبار',
            default => 'کیف پول',
        };
    }

    public function activeSessionGauge(?ChargingSession $session): int
    {
        if (! $session) {
            return 0;
        }

        return $this->charging->liveMetrics($session)['pct'];
    }

    public function sessionLivePayload(ChargingSession $session): array
    {
        $live = $this->charging->liveMetrics($session);

        return array_merge(['active' => true], $live);
    }

    /** @return list<array{title:string,detail:string,time:string,icon:string,color:string}> */
    public function auditRows(Collection $logs): array
    {
        return $logs->map(fn (AuditLog $log) => [
            'title' => $this->auditActionTitle($log->action),
            'detail' => $this->auditDetail($log),
            'time' => Persian::digits($log->created_at?->locale('fa')->diffForHumans() ?? '—'),
            'icon' => $this->auditIcon($log->action),
            'color' => $this->auditColor($log->action),
        ])->all();
    }

    public function auditActionTitle(string $action): string
    {
        return match ($action) {
            'vehicle.created' => 'ثبت خودرو',
            'vehicle.updated' => 'ویرایش خودرو',
            'vehicle.deleted' => 'حذف خودرو',
            'vehicle.default_set' => 'تنظیم خودروی پیش‌فرض',
            'vehicle.plate_updated' => 'تغییر پلاک',
            'rfid.created' => 'ثبت کارت RFID',
            'rfid.updated' => 'ویرایش کارت RFID',
            'rfid.blocked' => 'مسدودسازی کارت',
            'rfid.activated' => 'فعال‌سازی کارت',
            'rfid.deleted' => 'حذف کارت RFID',
            default => $action,
        };
    }

    private function auditIcon(string $action): string
    {
        return match (true) {
            str_contains($action, 'deleted') => 'x',
            str_contains($action, 'blocked') => 'shield',
            str_contains($action, 'created') => 'plus',
            str_contains($action, 'plate') => 'scan',
            str_contains($action, 'rfid') => 'chip',
            default => 'car',
        };
    }

    private function auditColor(string $action): string
    {
        return match (true) {
            str_contains($action, 'deleted'), str_contains($action, 'blocked') => 'hist-warn',
            str_contains($action, 'created'), str_contains($action, 'activated') => 'hist-ok',
            default => 'hist-info',
        };
    }

    private function auditDetail(AuditLog $log): string
    {
        $labels = [
            'brand' => 'برند',
            'model' => 'مدل',
            'plate_two' => 'پلاک',
            'plate_letter' => 'حرف',
            'plate_three' => 'پلاک',
            'plate_province' => 'استان',
            'battery_kwh' => 'باتری',
            'is_default' => 'پیش‌فرض',
            'id_tag' => 'شماره کارت',
            'card_label' => 'نام کارت',
            'status' => 'وضعیت',
        ];

        if ($log->old_values || $log->new_values) {
            $parts = [];
            $keys = array_unique(array_merge(
                array_keys($log->old_values ?? []),
                array_keys($log->new_values ?? [])
            ));
            foreach ($keys as $key) {
                $old = $log->old_values[$key] ?? null;
                $new = $log->new_values[$key] ?? null;
                if ($old == $new) {
                    continue;
                }
                $label = $labels[$key] ?? $key;
                $fmt = fn ($v) => is_bool($v) ? ($v ? 'بله' : 'خیر') : (string) ($v ?? '—');
                if ($old === null) {
                    $parts[] = "{$label}: {$fmt($new)}";
                } elseif ($new === null) {
                    $parts[] = "{$label}: {$fmt($old)}";
                } else {
                    $parts[] = "{$label}: {$fmt($old)} ← {$fmt($new)}";
                }
            }
            if ($parts !== []) {
                return implode(' · ', $parts);
            }
        }

        if ($log->action === 'vehicle.deleted' || $log->action === 'rfid.deleted') {
            return 'حذف منطقی — سوابق مالی حفظ می‌شود';
        }

        $actor = $log->actor?->name;

        return $actor ? "توسط {$actor}" : '—';
    }

    public function formatRfidTag(?string $tag): string
    {
        if (! $tag) {
            return '—';
        }
        $clean = preg_replace('/\s+/', '', $tag);

        return 'CN '.implode(' ', str_split($clean, 4));
    }

    public function rfidStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'فعال',
            'blocked' => 'مسدود',
            'expired' => 'منقضی',
            default => $status,
        };
    }
}
