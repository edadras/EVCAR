<?php

namespace App\Services\Ocpp;

use App\Models\OcppCall;
use Illuminate\Support\Str;

/**
 * صف دستورهای خروجی به شارژر. وب‌اپ رکورد می‌سازد و پروسهٔ ocpp:serve آن را
 * روی WebSocket ارسال کرده و پاسخ شارژر را در همان رکورد ذخیره می‌کند.
 */
class OcppCallQueue
{
    /** @param array<string, mixed> $payload */
    public function enqueue(string $chargePointId, string $action, array $payload = []): OcppCall
    {
        return OcppCall::create([
            'uuid' => (string) Str::uuid(),
            'charge_point_id' => $chargePointId,
            'action' => $action,
            'payload' => $payload,
            'status' => 'pending',
        ]);
    }

    public function await(OcppCall $call, float $timeoutSeconds): OcppCall
    {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            $call->refresh();
            if ($call->isFinished()) {
                return $call;
            }
            usleep(150_000);
        } while (microtime(true) < $deadline);

        // پاسخ در زمان مقرر نیامد؛ اگر هنوز ارسال نشده، لغوش می‌کنیم تا دیرهنگام اجرا نشود.
        OcppCall::where('id', $call->id)->where('status', 'pending')->update([
            'status' => 'timeout',
            'error' => 'No answer from charger within '.$timeoutSeconds.'s',
        ]);

        return $call->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> پاسخ شارژر (CALLRESULT)
     */
    public function call(string $chargePointId, string $action, array $payload, float $timeoutSeconds): array
    {
        $call = $this->await($this->enqueue($chargePointId, $action, $payload), $timeoutSeconds);

        return match ($call->status) {
            'answered' => $call->response ?? [],
            'error' => throw new \RuntimeException("دستور {$action} روی شارژر {$chargePointId} ناموفق بود: ".($call->error ?: 'خطای نامشخص')),
            default => throw new \RuntimeException("شارژر {$chargePointId} به دستور {$action} پاسخ نداد."),
        };
    }
}
