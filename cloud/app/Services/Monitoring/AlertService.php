<?php

namespace App\Services\Monitoring;

use App\Enums\UserRole;
use App\Models\EdgeStation;
use App\Models\OperationalAlert;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\Log;

/**
 * هشدارهای عملیاتی برای اپراتور/پشتیبانی (شارژر قطع، کانکتور خراب، Edge آفلاین).
 * هر موضوع فقط یک هشدار باز دارد؛ تکرار، شمارنده را بالا می‌برد.
 */
class AlertService
{
    public function raise(string $type, string $key, string $severity, string $message, ?int $stationId = null): OperationalAlert
    {
        $alert = OperationalAlert::where('type', $type)->where('subject_key', $key)->where('status', 'open')->first();
        if ($alert) {
            $alert->update(['last_seen_at' => now(), 'occurrences' => $alert->occurrences + 1, 'message' => $message]);

            return $alert;
        }

        $alert = OperationalAlert::create([
            'type' => $type,
            'subject_key' => $key,
            'severity' => $severity,
            'message' => $message,
            'station_id' => $stationId,
            'opened_at' => now(),
            'last_seen_at' => now(),
        ]);

        $this->notifyStaff($alert);

        return $alert;
    }

    public function resolve(string $type, string $key): void
    {
        OperationalAlert::where('type', $type)->where('subject_key', $key)->where('status', 'open')
            ->update(['status' => 'resolved', 'resolved_at' => now()]);
    }

    /** Edgeهایی که بیش از N دقیقه سینک نکرده‌اند. */
    public function checkEdges(int $minutes = 10): int
    {
        $count = 0;
        foreach (EdgeStation::with('station')->get() as $edge) {
            $last = $edge->last_sync_at ?? $edge->last_heartbeat_at;
            if ($last && $last->lt(now()->subMinutes($minutes))) {
                $this->raise('edge_offline', $edge->edge_code, 'critical', 'کنترلر ایستگاه '.($edge->station?->name ?? $edge->edge_code).' از '.$last->diffForHumans().' با مرکز سینک نکرده است.', $edge->station_id);
                $count++;
            } elseif ($last) {
                $this->resolve('edge_offline', $edge->edge_code);
            }
        }

        return $count;
    }

    private function notifyStaff(OperationalAlert $alert): void
    {
        Log::channel()->warning('alert.'.$alert->type, ['key' => $alert->subject_key, 'message' => $alert->message]);

        if ($alert->severity === 'info') {
            return;
        }

        $staff = User::query()->whereIn('role', [UserRole::SuperAdmin->value, UserRole::Support->value, UserRole::StationOperator->value])->limit(50)->get();
        foreach ($staff as $user) {
            try {
                UserNotification::create([
                    'user_id' => $user->id,
                    'title' => $alert->severity === 'critical' ? 'هشدار بحرانی' : 'هشدار',
                    'body' => $alert->message,
                    'type' => 'alert',
                    'channel' => 'in_app',
                    'sent_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::info('alert.notify_skipped', ['error' => $e->getMessage()]);

                break;
            }
        }
        $alert->update(['notified_at' => now()]);
    }
}
