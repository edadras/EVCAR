<?php

namespace App\Services\App;

use App\Models\ChargingSession;
use App\Models\SessionMeterValue;
use App\Models\Station;
use App\Models\StationReview;
use App\Models\StationWatch;
use App\Models\SupportTicket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\Charging\ConnectorAvailabilityService;
use App\Services\Charging\TariffCalculator;
use App\Services\Notifications\UserNotifier;
use App\Support\Persian;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * امکانات تعاملی مشتری: رسید جلسه، امتیازدهی ایستگاه، گزارش خرابی،
 * اعلان آزاد شدن ایستگاه و تحلیل هزینه‌ها — مشترک بین وب‌اپ و API موبایل.
 */
class CustomerEngagementService
{
    public const REVIEW_TAGS = [
        'fast' => 'شارژ سریع', 'clean' => 'تمیز و مرتب', 'easy_access' => 'دسترسی آسان', 'safe' => 'امن',
        'slow' => 'کندتر از انتظار', 'broken' => 'خرابی دستگاه', 'parking' => 'مشکل پارکینگ', 'cable' => 'مشکل کابل',
    ];

    public const PROBLEMS = [
        'not_starting' => 'شارژ شروع نمی‌شود',
        'stopped' => 'شارژ وسط کار قطع شد',
        'slow' => 'توان شارژ پایین است',
        'damaged' => 'آسیب فیزیکی کابل یا دستگاه',
        'blocked' => 'جای پارک اشغال است',
        'payment' => 'مشکل پرداخت در ایستگاه',
        'other' => 'سایر',
    ];

    /** مشکلات ایمنی و از کار افتادن کامل اولویت بالا می‌گیرند. */
    private const URGENT_PROBLEMS = ['not_starting', 'damaged'];

    public const WATCH_HOURS = 3;

    public function __construct(
        private ConnectorAvailabilityService $availability,
        private TariffCalculator $tariffs,
        private UserNotifier $notifier,
    ) {}

    // ───────────────────────── رسید جلسه ─────────────────────────

    /** @return array<string, mixed> */
    public function receipt(User $user, ChargingSession $session): array
    {
        abort_unless($session->user_id === $user->id, 404);
        $session->loadMissing(['station', 'charger', 'connector', 'tariff', 'vehicle']);

        $cost = (float) $session->cost_amount;
        $secs = (int) ($session->duration_seconds ?: ($session->started_at && $session->stopped_at ? $session->started_at->diffInSeconds($session->stopped_at) : 0));
        $b = $this->tariffs->breakdown($session->tariff, (float) $session->energy_kwh, $secs, $session->started_at, (int) ($session->meta['idle_seconds'] ?? 0));
        $lines = [];
        foreach (['energy' => 'هزینهٔ انرژی', 'time' => 'هزینهٔ زمانی', 'idle' => 'هزینهٔ توقف پس از شارژ', 'fee' => 'هزینهٔ ثابت جلسه'] as $k => $label) {
            if ($b[$k] > 0) {
                $lines[] = ['label' => $label, 'amount' => $this->toman($b[$k])];
            }
        }
        // اختلاف محاسبه با مبلغ کسرشده (تخفیف سازمانی، کمپین یا گردکردن) شفاف نمایش داده می‌شود
        $diff = $this->toman($b['total']) - $this->toman($cost);
        if ($lines && $diff > 0) {
            $lines[] = ['label' => 'تخفیف', 'amount' => -$diff];
        } elseif (! $lines || $diff < 0) {
            $lines = [['label' => 'هزینهٔ جلسه', 'amount' => $this->toman($cost)]];
        }

        $review = StationReview::where('charging_session_id', $session->id)->first();
        $done = in_array($session->status, ['completed', 'stopped'], true);

        return [
            'uuid' => $session->uuid,
            'status' => $session->status,
            'done' => $done,
            'station' => ['code' => $session->station?->code, 'name' => $session->station?->name ?? '—', 'address' => trim(($session->station?->city ?? '').'، '.($session->station?->address ?? ''), '، ')],
            'charger' => $session->charger?->charge_point_id,
            'connector' => $session->connector?->connector_number,
            'vehicle' => $session->vehicle ? trim($session->vehicle->brand.' '.$session->vehicle->model) : null,
            'started_at' => $session->started_at?->toIso8601String(),
            'started_label' => $session->started_at ? Persian::digits($session->started_at->format('Y/m/d H:i')) : '—',
            'stopped_label' => $session->stopped_at ? Persian::digits($session->stopped_at->format('H:i')) : '—',
            'duration_min' => (int) ceil($secs / 60),
            'energy_kwh' => round((float) $session->energy_kwh, 2),
            'avg_power_kw' => $secs > 0 ? round((float) $session->energy_kwh / ($secs / 3600), 1) : null,
            'price_per_kwh' => $this->toman($b['price_per_kwh']),
            'rule' => $b['rule'],
            'lines' => $lines,
            'total' => $this->toman($cost),
            'auth_method' => $session->auth_method,
            'stop_reason' => $session->stop_reason,
            'co2_kg' => round((float) $session->energy_kwh * 0.68, 1),
            'curve' => $this->powerCurve($session),
            'review' => $review ? $this->reviewPayload($review, true) : null,
            'can_review' => $done && ! $review && (float) $session->energy_kwh > 0 && $session->station_id,
            'tags' => self::REVIEW_TAGS,
        ];
    }

    /** نمودار توان جلسه (حداکثر ۴۰ نقطه) از MeterValues. @return list<array{t:int,kw:float,soc:?int}> */
    private function powerCurve(ChargingSession $session): array
    {
        $rows = SessionMeterValue::where('charging_session_id', $session->id)->whereNotNull('power_w')
            ->orderBy('sampled_at')->get(['sampled_at', 'power_w', 'soc_percent']);
        if ($rows->isEmpty() || ! $session->started_at) {
            return [];
        }
        $step = max(1, (int) ceil($rows->count() / 40));

        return $rows->values()->filter(fn ($r, $i) => $i % $step === 0)->map(fn ($r) => [
            't' => (int) max(0, round($session->started_at->diffInSeconds($r->sampled_at) / 60)),
            'kw' => round((float) $r->power_w / 1000, 1),
            'soc' => $r->soc_percent !== null ? (int) $r->soc_percent : null,
        ])->values()->all();
    }

    // ───────────────────────── امتیاز و نظر ─────────────────────────

    /** @param array{rating:int,comment?:?string,tags?:?array<int,string>} $data */
    public function review(User $user, ChargingSession $session, array $data): StationReview
    {
        abort_unless($session->user_id === $user->id && $session->station_id, 404);
        abort_unless(in_array($session->status, ['completed', 'stopped'], true), 422, 'فقط جلسه‌های پایان‌یافته قابل امتیازدهی‌اند.');

        $review = StationReview::updateOrCreate(['charging_session_id' => $session->id], [
            'user_id' => $user->id,
            'station_id' => $session->station_id,
            'rating' => max(1, min(5, (int) $data['rating'])),
            'comment' => isset($data['comment']) ? Str::limit(trim((string) $data['comment']), 600, '') ?: null : null,
            'tags' => array_values(array_intersect(array_keys(self::REVIEW_TAGS), (array) ($data['tags'] ?? []))),
        ]);
        $this->refreshStationRating($session->station_id);

        // امتیاز پایین با برچسب خرابی → تیکت خودکار برای تیم بهره‌برداری
        if ($review->rating <= 2 && array_intersect($review->tags ?? [], ['broken', 'cable'])) {
            $this->reportProblem($user, $session->station, ['problem' => 'damaged', 'description' => $review->comment ?? 'ثبت از امتیاز پایین جلسه', 'session_uuid' => $session->uuid], 'review');
        }

        return $review;
    }

    public function refreshStationRating(int $stationId): void
    {
        $agg = StationReview::where('station_id', $stationId)->where('is_hidden', false)
            ->selectRaw('COUNT(*) as c, AVG(rating) as a')->first();
        $station = Station::find($stationId);
        if (! $station) {
            return;
        }
        $meta = $station->meta ?? [];
        $meta['rating'] = $agg && $agg->c ? round((float) $agg->a, 1) : null;
        $meta['reviews'] = (int) ($agg->c ?? 0);
        $station->update(['meta' => $meta]);
    }

    /** @return array<string, mixed> */
    public function stationReviews(Station $station, int $limit = 5): array
    {
        $base = StationReview::where('station_id', $station->id)->where('is_hidden', false);
        $dist = (clone $base)->selectRaw('rating, COUNT(*) as c')->groupBy('rating')->pluck('c', 'rating');
        $tags = (clone $base)->whereNotNull('tags')->pluck('tags')->flatten()->countBy()->sortDesc()->take(4);

        return [
            'rating' => $station->meta['rating'] ?? null,
            'count' => (int) ($station->meta['reviews'] ?? $dist->sum()),
            'distribution' => collect(range(5, 1))->mapWithKeys(fn ($r) => [$r => (int) ($dist[$r] ?? 0)])->all(),
            'top_tags' => $tags->map(fn ($c, $k) => ['tag' => self::REVIEW_TAGS[$k] ?? $k, 'count' => $c])->values()->all(),
            'items' => (clone $base)->with('user')->latest()->limit($limit)->get()->map(fn ($r) => $this->reviewPayload($r))->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function reviewPayload(StationReview $r, bool $own = false): array
    {
        // نام کامل دیگران نمایش داده نمی‌شود: «سارا م.»
        $parts = preg_split('/\s+/u', trim($r->user?->name ?? 'کاربر')) ?: ['کاربر'];
        $short = $parts[0].(isset($parts[1]) ? ' '.mb_substr($parts[1], 0, 1).'.' : '');

        return [
            'id' => $r->id,
            'author' => $own ? ($r->user?->name ?? 'شما') : $short,
            'rating' => $r->rating,
            'comment' => $r->comment,
            'tags' => array_values(array_map(fn ($t) => self::REVIEW_TAGS[$t] ?? $t, $r->tags ?? [])),
            'date' => Persian::digits($r->created_at?->format('Y/m/d') ?? ''),
            'reply' => $r->reply,
        ];
    }

    // ───────────────────────── گزارش خرابی ─────────────────────────

    /** @param array{problem:string,description?:?string,connector?:?string,session_uuid?:?string} $data */
    public function reportProblem(User $user, ?Station $station, array $data, string $source = 'app'): SupportTicket
    {
        abort_unless($station, 404);
        $problem = array_key_exists($data['problem'], self::PROBLEMS) ? $data['problem'] : 'other';
        $label = self::PROBLEMS[$problem];
        $connector = trim((string) ($data['connector'] ?? ''));

        // گزارش تکراری همان مشتری برای همان ایستگاه در ۲ ساعت اخیر به تیکت قبلی اضافه می‌شود
        $existing = SupportTicket::where('user_id', $user->id)->where('station_id', $station->id)
            ->whereIn('status', ['open', 'in_progress'])->where('created_at', '>=', now()->subHours(2))->latest()->first();

        $body = "گزارش خرابی: {$label}\nایستگاه: {$station->name} ({$station->code})"
            .($connector !== '' ? "\nکانکتور: {$connector}" : '')
            .(! empty($data['session_uuid']) ? "\nجلسه: {$data['session_uuid']}" : '')
            .(! empty($data['description']) ? "\n\nتوضیحات مشتری:\n".Str::limit((string) $data['description'], 1500) : '');

        return DB::transaction(function () use ($existing, $user, $station, $problem, $label, $body, $source) {
            $ticket = $existing ?? SupportTicket::create([
                'uuid' => (string) Str::uuid(),
                'ticket_number' => 'TK-'.random_int(10000, 99999),
                'user_id' => $user->id,
                'station_id' => $station->id,
                'category' => 'station',
                'priority' => in_array($problem, self::URGENT_PROBLEMS, true) ? 'high' : 'normal',
                'source' => $source,
                'status' => 'open',
                'subject' => "{$label} — {$station->name}",
                'body' => $body,
            ]);
            TicketMessage::create(['support_ticket_id' => $ticket->id, 'user_id' => $user->id, 'message' => $body, 'is_staff_reply' => false]);
            if ($existing) {
                $existing->touch();
            }

            return $ticket;
        });
    }

    // ───────────────────────── اعلان آزاد شدن ─────────────────────────

    /** @return array{watching:bool,expires_at:?string,free:int} */
    public function toggleWatch(User $user, Station $station): array
    {
        $watch = StationWatch::where('user_id', $user->id)->where('station_id', $station->id)->first();
        if ($watch && ! $watch->notified_at && $watch->expires_at->isFuture()) {
            $watch->delete();

            return ['watching' => false, 'expires_at' => null, 'free' => $this->freeCount($station)];
        }
        $watch = StationWatch::updateOrCreate(['user_id' => $user->id, 'station_id' => $station->id], [
            'expires_at' => now()->addHours(self::WATCH_HOURS), 'notified_at' => null,
        ]);

        return ['watching' => true, 'expires_at' => $watch->expires_at->toIso8601String(), 'free' => $this->freeCount($station)];
    }

    public function isWatching(User $user, Station $station): bool
    {
        return StationWatch::where('user_id', $user->id)->where('station_id', $station->id)
            ->whereNull('notified_at')->where('expires_at', '>', now())->exists();
    }

    /** ایستگاه‌های تحت نظر را بررسی و به منتظران اعلان می‌دهد (زمان‌بندی هر دقیقه + رویداد کانکتور). */
    public function notifyWatchers(?int $stationId = null): int
    {
        $sent = 0;
        $watches = StationWatch::with(['user', 'station.chargers.connectors'])->whereNull('notified_at')->where('expires_at', '>', now())
            ->when($stationId, fn ($q) => $q->where('station_id', $stationId))->get()->groupBy('station_id');

        foreach ($watches as $group) {
            $station = $group->first()->station;
            $free = $station ? $this->freeCount($station) : 0;
            if ($free < 1) {
                continue;
            }
            foreach ($group as $w) {
                // قفل خوش‌بینانه تا اعلان تکراری از دو مسیر هم‌زمان ارسال نشود
                if (! StationWatch::whereKey($w->id)->whereNull('notified_at')->update(['notified_at' => now()]) || ! $w->user) {
                    continue;
                }
                $this->notifier->notify($w->user, 'station_free', 'ایستگاه آزاد شد ⚡',
                    "{$station->name}: ".Persian::digits($free).' کانکتور الان آزاد است. برای رزرو یا شروع شارژ بزنید.',
                    ['station' => $station->code]);
                $sent++;
            }
        }
        StationWatch::where('expires_at', '<', now()->subDay())->delete();

        return $sent;
    }

    public function freeCount(Station $station): int
    {
        $station->loadMissing('chargers.connectors');
        $free = 0;
        foreach ($station->chargers as $charger) {
            foreach ($charger->connectors as $cn) {
                $snap = $this->availability->snapshot($cn);
                if ($snap['reservable'] && $cn->ocpp_status !== 'Charging') {
                    $free++;
                }
            }
        }

        return $free;
    }

    // ───────────────────────── تحلیل هزینه ─────────────────────────

    /** @return array<string, mixed> */
    public function insights(User $user, int $months = 6): array
    {
        $from = now()->startOfMonth()->subMonths($months - 1);
        $sessions = ChargingSession::with('station')->where('user_id', $user->id)->where('started_at', '>=', $from)
            ->whereIn('status', ['completed', 'stopped'])->get(['id', 'station_id', 'started_at', 'energy_kwh', 'cost_amount', 'duration_seconds']);
        $faMonths = ['', 'ژانویه', 'فوریه', 'مارس', 'آوریل', 'مه', 'ژوئن', 'ژوئیه', 'اوت', 'سپتامبر', 'اکتبر', 'نوامبر', 'دسامبر'];

        $series = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $from->copy()->addMonths($i);
            $rows = $sessions->filter(fn ($s) => $s->started_at->format('Y-m') === $m->format('Y-m'));
            $series[] = [
                'key' => $m->format('Y-m'),
                'label' => $this->jalaliMonth($m) ?? $faMonths[(int) $m->format('n')],
                'sessions' => $rows->count(),
                'energy' => round((float) $rows->sum('energy_kwh'), 1),
                'cost' => $this->toman($rows->sum('cost_amount')),
            ];
        }

        $energy = (float) $sessions->sum('energy_kwh');
        $cost = $this->toman($sessions->sum('cost_amount'));
        $night = $sessions->filter(fn ($s) => (int) $s->started_at->copy()->setTimezone(config('safircharge.timezone', 'Asia/Tehran'))->format('G') >= 22
            || (int) $s->started_at->copy()->setTimezone(config('safircharge.timezone', 'Asia/Tehran'))->format('G') < 6)->count();
        $top = $sessions->groupBy('station_id')->sortByDesc(fn ($g) => $g->count())->first();
        $current = end($series);
        $previous = $series[count($series) - 2] ?? null;

        // صرفه‌جویی نسبت به خودروی بنزینی هم‌رده: ۱۵ کیلومتر بر کیلووات‌ساعت، ۸ لیتر/۱۰۰کیلومتر، بنزین ۳۵۰۰ تومان
        $km = $energy * 6.5;
        $fuelCost = (int) round($km / 100 * 8 * 3500);

        return [
            'months' => $series,
            'totals' => [
                'sessions' => $sessions->count(),
                'energy' => round($energy, 1),
                'cost' => $cost,
                'avg_per_kwh' => $energy > 0 ? (int) round($cost / $energy) : null,
                'avg_minutes' => $sessions->count() ? (int) round($sessions->avg('duration_seconds') / 60) : null,
                'co2_kg' => (int) round($energy * 0.68),
                'km' => (int) round($km),
                'fuel_saving' => max(0, $fuelCost - $cost),
                'night_share' => $sessions->count() ? (int) round(100 * $night / $sessions->count()) : 0,
            ],
            'month_change_pct' => $previous && $previous['cost'] > 0 ? (int) round(100 * ($current['cost'] - $previous['cost']) / $previous['cost']) : null,
            'top_station' => $top ? ['name' => $top->first()->station?->name, 'code' => $top->first()->station?->code, 'sessions' => $top->count()] : null,
            'tip' => $sessions->count() && $night * 2 < $sessions->count()
                ? 'بیشتر شارژهای شما در ساعات اوج است؛ شارژ شبانه (۲۲ تا ۶) در بسیاری از ایستگاه‌ها ارزان‌تر است.'
                : 'عالی! الگوی شارژ شما بهینه است. برای صرفه‌جویی بیشتر پیشنهادهای ویژه را دنبال کنید.',
        ];
    }

    private function jalaliMonth(CarbonInterface $date): ?string
    {
        if (! class_exists(\IntlDateFormatter::class)) {
            return null;
        }
        $fmt = new \IntlDateFormatter('fa_IR@calendar=persian', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'Asia/Tehran', \IntlDateFormatter::TRADITIONAL, 'MMMM');
        $out = $fmt->format($date->copy()->day(15)->getTimestamp());

        return $out === false ? null : $out;
    }

    private function toman(mixed $rial): int
    {
        return (int) round((float) $rial / 10);
    }
}
