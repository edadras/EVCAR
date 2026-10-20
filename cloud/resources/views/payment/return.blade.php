@php use App\Support\Persian; $ok = $payment->status === 'paid'; @endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SafirCharge — نتیجهٔ پرداخت</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F3F6FA;font-family:Vazirmatn,system-ui,sans-serif;color:#0F1E2E}
        .card{background:#fff;border-radius:24px;padding:32px 24px;max-width:360px;width:calc(100% - 32px);text-align:center;box-shadow:0 16px 40px rgba(15,30,46,.12)}
        .ic{width:84px;height:84px;border-radius:50%;margin:0 auto 18px;display:grid;place-items:center;color:#fff;font-size:44px;background:{{ $ok ? '#16A34A' : '#DC2626' }}}
        h1{font-size:21px;margin:0 0 8px} p{color:#43566B;line-height:1.9;font-size:14px;margin:0 0 20px}
        a{display:block;background:#F97316;color:#fff;text-decoration:none;font-weight:800;border-radius:16px;padding:15px}
    </style>
</head>
<body>
<div class="card">
    <div class="ic">{{ $ok ? '✓' : '✕' }}</div>
    <h1>{{ $ok ? 'پرداخت موفق بود' : 'پرداخت انجام نشد' }}</h1>
    <p>
        @if($ok)
            {{ Persian::money((int) round((float) $payment->amount / 10)) }} تومان به کیف پول شما اضافه شد.<br>شمارهٔ پیگیری: {{ Persian::digits($payment->gateway_reference) }}
        @else
            مبلغی به کیف پول اضافه نشد. اگر از حساب شما کسر شده، حداکثر تا ۷۲ ساعت بازگردانده می‌شود.
        @endif
    </p>
    <a href="safircharge://payment?uuid={{ $payment->uuid }}">بازگشت به اپ SafirCharge</a>
</div>
</body>
</html>
