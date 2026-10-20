@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — پرداخت موفق')

@section('phone')
@php
use App\Support\Persian;
$amount = (int) ($topupAmount ?? request('amount', 0));
$balanceToman = (int) round(($wallet?->balance ?? 0) / 10);
$ref = $paymentRef ?? request('payment', '—');
@endphp
@if(($payment ?? null) && $payment->status !== 'paid')
<div class="screen" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:30px">
    <div class="ok-circle rise" style="background:linear-gradient(140deg,var(--red),#f87171)">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>
    </div>
    <h2 class="mt20" style="font-size:22px">{{ $payment->status === 'pending' ? 'در انتظار تأیید بانک' : 'پرداخت ناموفق بود' }}</h2>
    <p class="muted" style="margin-top:8px;font-size:13.5px;line-height:1.8">مبلغی از کیف پول شما کسر یا به آن اضافه نشد. اگر از حساب بانکی شما برداشت شده، حداکثر تا ۷۲ ساعت بازگردانده می‌شود.</p>
</div>
<div class="savebar">
    <a href="{{ route('app.wallet') }}" class="btn btn-orange" style="flex:1;text-decoration:none">بازگشت به کیف پول</a>
</div>
@else
<div class="screen" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:30px">
    <div class="ok-circle rise">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
    </div>
    <h2 class="mt20" style="font-size:22px">پرداخت موفق بود</h2>
    <p class="muted" style="margin-top:8px;font-size:13.5px;line-height:1.8">موجودی کیف پول شما با موفقیت افزایش یافت.</p>

    <div class="rcpt">
        <div class="rcpt-amt tnum" id="amt">{{ Persian::money($amount) }} <small>تومان</small></div>
        <div class="rrow"><span class="muted">موجودی جدید</span><b class="tnum" id="newBal">{{ Persian::money($balanceToman) }} تومان</b></div>
        <div class="rrow"><span class="muted">شمارهٔ پیگیری</span><b class="mono">{{ $ref }}</b></div>
        <div class="rrow"><span class="muted">درگاه</span><b>{{ ['mellat' => 'به‌پرداخت ملت', 'saman' => 'سامان (سپ)', 'zarinpal' => 'زرین‌پال'][$payment?->gateway ?? 'mellat'] ?? $payment?->gateway }}</b></div>
        <div class="rrow"><span class="muted">تاریخ</span><b class="tnum">{{ Persian::digits(now()->format('Y/m/d')) }} — {{ Persian::digits(now()->format('H:i')) }}</b></div>
    </div>
</div>

<div class="savebar" style="display:flex;gap:11px">
    <a href="{{ route('app.wallet') }}" class="btn btn-orange" style="flex:1;height:50px;display:flex;align-items:center;justify-content:center">بازگشت به کیف پول</a>
    <button type="button" class="btn btn-ghost" style="flex:none;width:54px" title="رسید"><span data-ic="download"></span></button>
</div>
@endif
@endsection

@push('styles')
<style>
.ok-circle{width:96px;height:96px;border-radius:50%;background:linear-gradient(140deg,var(--green),#4ade80);display:grid;place-items:center;box-shadow:0 16px 34px rgba(22,163,74,.4)}
.ok-circle svg{width:46px;height:46px}
.rcpt{width:100%;margin-top:26px;border:1px solid var(--border);border-radius:18px;padding:20px;background:var(--surface);box-shadow:var(--shadow-sm)}
.rcpt-amt{font-size:28px;font-weight:800;text-align:center;padding-bottom:16px;margin-bottom:8px;border-bottom:1px dashed var(--border)}
.rcpt-amt small{font-size:14px;font-weight:600;color:var(--text-2)}
.rrow{display:flex;justify-content:space-between;font-size:13px;padding:7px 0}
.savebar{position:absolute;bottom:0;left:0;right:0;background:var(--surface);border-top:1px solid var(--border);padding:14px 20px calc(20px + env(safe-area-inset-bottom,0));z-index:20}
.savebar .btn svg{width:18px;height:18px}
</style>
@endpush
