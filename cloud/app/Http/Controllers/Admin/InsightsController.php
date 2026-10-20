<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChargingSession;
use App\Models\Settlement;
use App\Models\Station;
use App\Services\Admin\InsightsService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InsightsController extends Controller
{
    public function __construct(private InsightsService $insights) {}

    public function index(Request $request): View
    {
        $stationId = $request->integer('station_id') ?: null;

        return view('admin.insights', [
            'performance' => $this->insights->stationPerformance(),
            'forecast' => $this->insights->busyHoursForecast($stationId),
            'maintenance' => $this->insights->maintenanceCandidates(),
            'stations' => Station::orderBy('name')->get(['id', 'name']),
            'stationId' => $stationId,
            'from' => now()->subMonth()->startOfMonth()->toDateString(),
            'to' => now()->subMonth()->endOfMonth()->toDateString(),
        ]);
    }

    /** خروجی CSV (سازگار با Excel — UTF-8 با BOM). */
    public function export(Request $request, string $type): StreamedResponse
    {
        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $from = ($data['from'] ?? now()->subDays(30)->toDateString()).' 00:00:00';
        $to = ($data['to'] ?? now()->toDateString()).' 23:59:59';
        $vat = (float) config('safircharge.finance.vat_percent', 10);

        return response()->streamDownload(function () use ($type, $from, $to, $vat) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            match ($type) {
                'sessions' => $this->sessionsCsv($out, $from, $to),
                'revenue' => $this->revenueCsv($out, $from, $to),
                'tax' => $this->taxCsv($out, $from, $to, $vat),
                'settlements' => $this->settlementsCsv($out, $from, $to),
            };
            fclose($out);
        }, "safircharge-{$type}-".substr($from, 0, 10).'-'.substr($to, 0, 10).'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @param resource $out */
    private function sessionsCsv($out, string $from, string $to): void
    {
        fputcsv($out, ['session_uuid', 'station', 'charge_point', 'connector', 'user', 'auth', 'status', 'started_at', 'stopped_at', 'duration_min', 'energy_kwh', 'cost_rial', 'stop_reason', 'ocpp_transaction_id']);
        ChargingSession::with(['station', 'charger', 'connector', 'user'])
            ->whereBetween('started_at', [$from, $to])
            ->orderBy('id')
            ->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $s) {
                    fputcsv($out, [
                        $s->uuid, $s->station?->name, $s->charger?->charge_point_id, $s->connector?->connector_number,
                        $s->user?->name, $s->auth_method, $s->status, $s->started_at?->toDateTimeString(), $s->stopped_at?->toDateTimeString(),
                        (int) round($s->duration_seconds / 60), (float) $s->energy_kwh, (float) $s->cost_amount, $s->stop_reason, $s->ocpp_transaction_id,
                    ]);
                }
            });
    }

    /** @param resource $out */
    private function revenueCsv($out, string $from, string $to): void
    {
        fputcsv($out, ['date', 'station', 'sessions', 'energy_kwh', 'revenue_rial']);
        ChargingSession::query()
            ->whereIn('status', InsightsService::FINISHED)
            ->whereBetween('started_at', [$from, $to])
            ->selectRaw('DATE(started_at) as d, station_id, COUNT(*) as c, SUM(energy_kwh) as e, SUM(cost_amount) as r')
            ->groupBy('d', 'station_id')
            ->orderBy('d')
            ->get()
            ->each(function ($row) use ($out) {
                fputcsv($out, [$row->d, Station::find($row->station_id)?->name, (int) $row->c, round((float) $row->e, 3), (float) $row->r]);
            });
    }

    /** گزارش مالیات بر ارزش افزوده: مبالغ دریافتی شامل مالیات فرض می‌شوند. @param resource $out */
    private function taxCsv($out, string $from, string $to, float $vat): void
    {
        fputcsv($out, ['month', 'sessions', 'energy_kwh', 'gross_rial', 'net_rial', 'vat_percent', 'vat_rial']);
        ChargingSession::query()
            ->whereIn('status', InsightsService::FINISHED)
            ->whereBetween('started_at', [$from, $to])
            ->get(['started_at', 'energy_kwh', 'cost_amount'])
            ->groupBy(fn ($s) => $s->started_at->format('Y-m'))
            ->each(function ($list, $month) use ($out, $vat) {
                $gross = (float) $list->sum('cost_amount');
                $net = round($gross / (1 + $vat / 100), 2);
                fputcsv($out, [$month, $list->count(), round((float) $list->sum('energy_kwh'), 3), $gross, $net, $vat, round($gross - $net, 2)]);
            });
    }

    /** @param resource $out */
    private function settlementsCsv($out, string $from, string $to): void
    {
        fputcsv($out, ['settlement_number', 'partner', 'period_start', 'period_end', 'status', 'sessions', 'energy_kwh', 'gross_rial', 'platform_fee_rial', 'partner_share_rial', 'iban']);
        Settlement::with('partner')
            ->whereBetween('period_start', [substr($from, 0, 10), substr($to, 0, 10)])
            ->orderBy('period_start')
            ->get()
            ->each(fn ($s) => fputcsv($out, [
                $s->settlement_number, $s->partner?->name, $s->period_start?->toDateString(), $s->period_end?->toDateString(), $s->status,
                $s->session_count, (float) $s->energy_kwh, (float) $s->gross_revenue, (float) $s->platform_fee, (float) $s->partner_share, $s->partner?->bank_iban,
            ]));
    }
}
