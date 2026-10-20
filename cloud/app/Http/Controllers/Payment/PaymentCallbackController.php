<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Finance\PaymentGatewayService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** بازگشت کاربر از صفحهٔ بانک (زرین‌پال: ?Authority=...&Status=OK|NOK). */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, Payment $payment, PaymentGatewayService $payments): Response
    {
        $payment = $payments->verify($payment, $request->query('Authority'), $request->query('Status'));

        // پرداخت از اپ موبایل: صفحهٔ ساده با دکمهٔ بازگشت به اپ (اپ وضعیت را خودش از API می‌خواند).
        if (($payment->gateway_payload['source'] ?? 'web') === 'app') {
            return response()->view('payment.return', ['payment' => $payment]);
        }

        return redirect()->route('app.topup.result', ['payment' => $payment->uuid]);
    }
}
