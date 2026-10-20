<?php

namespace App\Observers;

use App\Models\ChargingSession;
use App\Services\Notifications\UserNotifier;

/** پایان هر جلسهٔ شارژ (ابری، OCPP مستقیم یا همگام‌شده از Edge) به مشتری اعلام می‌شود. */
class ChargingSessionObserver
{
    public bool $afterCommit = true;

    public function updated(ChargingSession $session): void
    {
        if ($session->wasChanged('status') && $session->getOriginal('status') === 'active'
            && in_array($session->status, ['stopped', 'completed'], true)) {
            app(UserNotifier::class)->sessionFinished($session);
        }
    }
}
