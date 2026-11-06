<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charger;
use App\Services\Audit\AuditService;
use App\Services\Ocpp\CloudOcppCommander;
use App\Support\OcppConfigCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** خواندن و تغییر تنظیمات OCPP دستگاه (GetConfiguration / ChangeConfiguration). */
class ChargerConfigController extends Controller
{
    public function __construct(private CloudOcppCommander $commander) {}

    public function show(Charger $charger): View
    {
        $charger->load('station');

        return view('admin.chargers.config', [
            'charger' => $charger,
            'rows' => $this->rows($charger),
            'connected' => $this->commander->isConnected($charger),
        ]);
    }

    public function refresh(Charger $charger): JsonResponse
    {
        try {
            $response = $this->commander->call($charger->charge_point_id, 'GetConfiguration', [], 25);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        if (! is_array($response['configurationKey'] ?? null)) {
            return response()->json(['ok' => false, 'message' => 'دستگاه فهرست تنظیمات را برنگرداند.'], 422);
        }

        $config = [];
        foreach ($response['configurationKey'] as $item) {
            if (is_array($item) && isset($item['key'])) {
                $config[(string) $item['key']] = ['value' => $item['value'] ?? null, 'readonly' => (bool) ($item['readonly'] ?? false)];
            }
        }
        $charger->update(['meta' => array_merge($charger->meta ?? [], ['ocpp_config' => $config, 'ocpp_config_at' => now()->toIso8601String()])]);

        return response()->json(['ok' => true, 'rows' => $this->rows($charger->fresh()), 'at' => now()->format('H:i:s')]);
    }

    public function update(Request $request, Charger $charger, AuditService $audit): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_.\-]+$/'],
            'value' => ['present', 'nullable', 'string', 'max:500'],
        ]);
        $key = $data['key'];
        $value = trim((string) ($data['value'] ?? ''));
        $stored = $charger->meta['ocpp_config'][$key] ?? null;

        if ($stored['readonly'] ?? false) {
            return response()->json(['ok' => false, 'message' => 'این تنظیم روی دستگاه فقط‌خواندنی است.'], 422);
        }
        $info = OcppConfigCatalog::describe($key, $stored['value'] ?? null);
        if ($info['type'] === 'bool') {
            $value = strtolower($value);
            if (! in_array($value, ['true', 'false'], true)) {
                return response()->json(['ok' => false, 'message' => 'مقدار باید true یا false باشد.'], 422);
            }
        } elseif ($info['type'] === 'int') {
            if (! preg_match('/^\d+$/', $value) || ($info['min'] !== null && (int) $value < $info['min'])) {
                return response()->json(['ok' => false, 'message' => 'مقدار باید عدد صحیح'.($info['min'] !== null ? ' و حداقل '.$info['min'] : '').' باشد.'], 422);
            }
        }

        try {
            $response = $this->commander->call($charger->charge_point_id, 'ChangeConfiguration', ['key' => $key, 'value' => $value], 20);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
        $status = (string) ($response['status'] ?? 'NoResponse');

        if (in_array($status, ['Accepted', 'RebootRequired'], true)) {
            $config = $charger->meta['ocpp_config'] ?? [];
            $config[$key] = ['value' => $value, 'readonly' => false];
            $charger->update(['meta' => array_merge($charger->meta ?? [], ['ocpp_config' => $config])]);
        }
        $audit->record('charger.ocpp_config', $charger, [$key => $stored['value'] ?? null], [$key => $value, 'status' => $status]);

        $messages = [
            'Accepted' => 'دستگاه تنظیم را پذیرفت.',
            'RebootRequired' => 'ذخیره شد؛ برای اعمال، دستگاه باید راه‌اندازی مجدد شود.',
            'Rejected' => 'دستگاه این مقدار را رد کرد.',
            'NotSupported' => 'دستگاه این تنظیم را پشتیبانی نمی‌کند.',
        ];

        return response()->json([
            'ok' => in_array($status, ['Accepted', 'RebootRequired'], true),
            'status' => $status,
            'message' => $messages[$status] ?? 'پاسخی از دستگاه دریافت نشد.',
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function rows(Charger $charger): array
    {
        $rows = [];
        foreach ($charger->meta['ocpp_config'] ?? [] as $key => $item) {
            $info = OcppConfigCatalog::describe((string) $key, $item['value'] ?? null);
            $rows[] = [
                'key' => (string) $key,
                'value' => $item['value'] ?? null,
                'readonly' => (bool) ($item['readonly'] ?? false),
                'known' => isset(OcppConfigCatalog::KEYS[$key]),
                'risky' => OcppConfigCatalog::risky((string) $key),
            ] + $info;
        }
        usort($rows, fn ($a, $b) => [$a['readonly'], ! $a['known'], $a['key']] <=> [$b['readonly'], ! $b['known'], $b['key']]);

        return $rows;
    }
}
