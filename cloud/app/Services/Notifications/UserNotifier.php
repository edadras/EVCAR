<?php

namespace App\Services\Notifications;

use App\Models\ChargingSession;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\Persian;
use Illuminate\Support\Str;

/**
 * اعلان‌های رویدادی مشتری (پایان شارژ، کمبود موجودی، پرداخت موفق):
 * در صندوق اعلان‌های اپ/وب ثبت و در صورت پیکربندی FCM به گوشی هم پوش می‌شود.
 * تنظیمات کاربر (notify_push، notify_low_balance) رعایت می‌شود.
 */
class UserNotifier
{
    public const LOW_BALANCE_RIAL = 500_000; // ۵۰ هزار تومان

    public function __construct(private FcmPushService $push) {}

    /** @param  array<string, scalar>  $data */
    public function notify(User $user, string $type, string $title, string $body, array $data = []): UserNotification
    {
        $prefs = $user->meta['app_prefs'] ?? [];
        $notification = UserNotification::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'channel' => 'push',
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'sent_at' => now(),
        ]);

        if (($prefs['notify_push'] ?? true) !== false) {
            try {
                $this->push->sendToUser($user, $title, $body, ['type' => $type, 'notification_id' => $notification->id] + $data);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $notification;
    }

    public function sessionFinished(ChargingSession $session): void
    {
        $user = $session->user;
        if (! $user) {
            return;
        }
        $energy = (float) $session->energy_kwh;
        $cost = (int) round((float) $session->cost_amount / 10);
        $station = $session->station?->name ?? 'ایستگاه';

        if ($energy <= 0 && $cost <= 0) {
            $this->notify($user, 'session_failed', 'شارژ آغاز نشد', "جلسهٔ شارژ در {$station} بدون تحویل انرژی بسته شد و هزینه‌ای کسر نشد.", ['session' => $session->uuid]);
        } else {
            $this->notify($user, 'session_complete', 'شارژ با موفقیت پایان یافت',
                "{$station}: ".Persian::kwh($energy, 2).' کیلووات‌ساعت · '.Persian::money($cost).' تومان از کیف پول کسر شد.',
                ['session' => $session->uuid]);
        }

        $this->checkLowBalance($user);
    }

    public function paymentPaid(Payment $payment): void
    {
        if ($user = $payment->user) {
            $this->notify($user, 'wallet_topup', 'کیف پول شارژ شد',
                Persian::money((int) round((float) $payment->amount / 10)).' تومان به کیف پول شما اضافه شد.',
                ['payment' => $payment->uuid]);
        }
    }

    public function checkLowBalance(User $user): void
    {
        $prefs = $user->meta['app_prefs'] ?? [];
        $balance = (float) ($user->wallet()->first()?->balance ?? 0);
        if (($prefs['notify_low_balance'] ?? true) === false || $balance >= self::LOW_BALANCE_RIAL) {
            return;
        }
        // در هر ۲۴ ساعت فقط یک هشدار
        $recent = UserNotification::where('user_id', $user->id)->where('type', 'low_balance')->where('sent_at', '>=', now()->subDay())->exists();
        if (! $recent) {
            $this->notify($user, 'low_balance', 'موجودی کیف پول کم است',
                'موجودی شما '.Persian::money((int) round($balance / 10)).' تومان است؛ برای شارژ بعدی کیف پول را شارژ کنید.');
        }
    }
}
