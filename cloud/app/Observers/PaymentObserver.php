<?php

namespace App\Observers;

use App\Models\Payment;
use App\Services\Notifications\UserNotifier;

class PaymentObserver
{
    public bool $afterCommit = true;

    public function updated(Payment $payment): void
    {
        if ($payment->wasChanged('status') && $payment->status === 'paid') {
            app(UserNotifier::class)->paymentPaid($payment);
        }
    }
}
