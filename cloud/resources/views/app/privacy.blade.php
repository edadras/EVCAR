@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — حریم خصوصی و امنیت')

@section('phone')
@php $user = auth()->user(); @endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.settings') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">حریم خصوصی و امنیت</div></div>
</div>

<div class="screen">
    <div class="sec-title" style="margin-top:6px">امنیت ورود</div>
    <div class="card card-p">
        <a href="{{ route('app.password') }}" class="srow" style="text-decoration:none;color:inherit">
            <span class="sr-ic ic-blue" data-ic="shield"></span>
            <div class="l-main"><div class="l-title">رمز عبور / پین</div><div class="l-sub">تغییر رمز ورود به برنامه</div></div>
            <span class="chev" data-ic="chevron"></span>
        </a>
        <div class="srow"><span class="sr-ic ic-green" data-ic="check"></span><div class="l-main"><div class="l-title">ورود با اثر انگشت / چهره</div><div class="l-sub">احراز هویت بیومتریک</div></div><span class="switch on" data-sw><i></i></span></div>
        <div class="srow" style="border-bottom:none"><span class="sr-ic ic-violet" data-ic="bolt"></span><div class="l-main"><div class="l-title">تأیید دومرحله‌ای</div><div class="l-sub">کد پیامکی هنگام ورود جدید</div></div><span class="switch {{ $user->phone_verified_at ? 'on' : '' }}" data-sw><i></i></span></div>
    </div>

    <div class="sec-title">حریم خصوصی داده‌ها</div>
    <div class="card card-p">
        <div class="srow"><span class="sr-ic ic-orange" data-ic="pin"></span><div class="l-main"><div class="l-title">دسترسی به موقعیت مکانی</div><div class="l-sub">برای یافتن شارژر نزدیک</div></div><span class="switch on" data-sw><i></i></span></div>
        <div class="srow"><span class="sr-ic ic-blue" data-ic="car"></span><div class="l-main"><div class="l-title">اشتراک پلاک برای شارژ خودکار</div><div class="l-sub">تشخیص پلاک در ایستگاه‌ها</div></div><span class="switch on" data-sw><i></i></span></div>
        <div class="srow" style="border-bottom:none"><span class="sr-ic ic-violet" data-ic="bell"></span><div class="l-main"><div class="l-title">پیام‌های تبلیغاتی</div><div class="l-sub">دریافت پیشنهادها و تخفیف‌ها</div></div><span class="switch" data-sw><i></i></span></div>
    </div>

    <div class="sec-title">دستگاه‌های فعال</div>
    <div class="card card-p">
        <div class="srow"><span class="sr-ic ic-green" data-ic="check"></span><div class="l-main"><div class="l-title">این دستگاه · {{ $user->name }}</div><div class="l-sub">هم‌اکنون فعال</div></div><span class="pill pill-green">فعلی</span></div>
        <div class="srow" style="border-bottom:none"><span class="sr-ic" data-ic="eye" style="background:var(--surface-3);color:var(--text-2)"></span><div class="l-main"><div class="l-title">مرورگر · {{ $user->last_login_at ? $user->last_login_at->diffForHumans() : '—' }}</div><div class="l-sub">آخرین ورود</div></div><button type="button" class="mini-out">خروج</button></div>
    </div>

    @if($content)
    <div class="sec-title">سیاست حفظ حریم خصوصی</div>
    <div class="card card-p legal-content">{!! str_contains($content, '<') ? $content : nl2br(e($content)) !!}</div>
    @endif

    <button type="button" class="danger-btn mt20"><span data-ic="x"></span> حذف حساب کاربری و داده‌ها</button>
    <div class="center-col mt12 mb"><span class="fs12 muted">داده‌های شما طبق سیاست حفظ حریم خصوصی SafirCharge نگهداری می‌شود.</span></div>
</div>
@endsection

@push('styles')
<style>
.srow{display:flex;align-items:center;gap:13px;padding:13px 0;border-bottom:1px solid var(--border-2)}
.sr-ic{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;flex:none}.sr-ic svg{width:20px;height:20px}
.l-title{font-weight:700;font-size:13.5px}.l-sub{font-size:11.5px;color:var(--muted);margin-top:2px}
.chev{display:inline-grid;width:18px;height:18px;color:var(--muted)}.chev svg{width:18px;height:18px}
.switch{width:46px;height:26px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;cursor:pointer;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .18s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-20px)}
.mini-out{border:1px solid var(--border);background:var(--surface);color:var(--red);font-size:12px;font-weight:700;padding:7px 13px;border-radius:10px}
.danger-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;height:50px;border-radius:15px;background:var(--red-soft);color:var(--red);font-weight:700;font-size:14px;border:1px solid color-mix(in srgb,var(--red) 18%,transparent)}
.danger-btn svg{width:18px;height:18px}
.legal-content{font-size:13px;color:var(--text-2);line-height:1.9}
.mb{margin-bottom:8px}
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('[data-sw]').forEach(function(s){s.addEventListener('click',function(){s.classList.toggle('on');});});
</script>
@endpush
