@extends('app.layouts.mobile', ['tab' => 'account'])

@section('title', 'SafirCharge — حساب کاربری')

@section('phone')
@php use App\Support\Persian; @endphp
<div class="appbar" style="padding-top:2px">
    <div style="flex:1"><div class="ab-title">حساب کاربری</div></div>
    <a href="{{ route('app.settings') }}" class="ab-btn"><span data-ic="settings"></span></a>
</div>

<div class="screen">
    <a class="profile rise" href="{{ route('app.profile') }}">
        <div class="pf-av">{{ $initials }}</div>
        <div style="flex:1">
            <div class="fw8" style="font-size:18px">{{ $user->name }}</div>
            <div class="fs13 muted">{{ $phoneFormatted }}</div>
            <span class="pill pill-accent mt8">{{ $memberSince }}</span>
        </div>
        <span class="ab-btn"><span data-ic="chevron"></span></span>
    </a>

    <div class="sec-title">خودروهای من <a href="{{ route('app.vehicle') }}">مدیریت</a></div>
    <div class="card card-p">
        @forelse($vehicles as $v)
        <div class="row veh-line">
            <div class="l-ic {{ $v->is_default ? 'ic-blue' : 'ic-green' }}" data-ic="car"></div>
            <div style="flex:1">
                <div class="fw7">{{ $v->brand }} {{ $v->model }}</div>
                <div class="fs12 muted mt4">CCS2 · {{ Persian::digits((int) $v->battery_kwh) }} kWh · پلاک {{ Persian::digits($v->plate_two) }}{{ $v->plate_letter }}{{ Persian::digits($v->plate_three) }}‑{{ Persian::digits($v->plate_province) }}</div>
            </div>
            @if($v->is_default)
                <span class="pill pill-accent">فعال</span>
            @else
                <a class="btn-sm btn-soft btn" style="width:auto" href="{{ route('app.vehicle') }}">انتخاب</a>
            @endif
        </div>
        @empty
        <div class="muted fs13" style="padding:8px 0">خودرویی ثبت نشده است.</div>
        @endforelse
        <a class="add-line" href="{{ route('app.vehicle') }}"><span data-ic="plus"></span> افزودن خودروی جدید</a>
        <div class="auto-row" id="autoRow">
            <div style="flex:1">
                <div class="row" style="gap:7px"><span class="ar-ic" data-ic="scan"></span><span class="fw7 fs13">شارژ خودکار با پلاک</span></div>
                <div class="fs12 muted mt4" style="line-height:1.6">با ورود به ایستگاه مجهز به دوربین، شارژ به‌صورت خودکار آغاز و هزینه از کیف پول کسر می‌شود.</div>
            </div>
            <span class="switch on" data-sw id="autoSw"><i></i></span>
        </div>
    </div>

    <div class="sec-title">مسیریابی</div>
    <div class="card card-p" style="margin-bottom:16px">
        <a class="lrow" style="padding-top:0;padding-bottom:0" href="{{ route('app.navigator') }}">
            <div class="l-ic ic-blue" data-ic="nav"></div>
            <div class="l-main">
                <div class="l-title">مسیریاب برقی</div>
                <div class="l-sub">برنامه‌ریزی مسیر، مصرف انرژی و توقف شارژ</div>
            </div>
            <span class="chev" data-ic="chevron"></span>
        </a>
    </div>

    <div class="sec-title">روش‌های شروع شارژ</div>
    <div class="card card-p">
        <a class="lrow" style="padding-top:0" href="{{ route('app.scan') }}"><div class="l-ic ic-blue" data-ic="qr"></div><div class="l-main"><div class="l-title">بارکد / QR جایگاه</div><div class="l-sub">اسکن کد روی شارژر</div></div><span class="tag">فعال</span></a>
        <a class="lrow" href="{{ route('app.rfid') }}"><div class="l-ic ic-green" data-ic="chip"></div><div class="l-main"><div class="l-title">کارت RFID</div><div class="l-sub">{{ $rfidMasked }} · {{ $rfidCard ? 'فعال' : 'غیرفعال' }}</div></div><span class="chev" data-ic="chevron"></span></a>
        <a class="lrow" style="padding-bottom:0" href="{{ route('app.plate') }}"><div class="l-ic ic-violet" data-ic="scan"></div><div class="l-main"><div class="l-title">تشخیص پلاک خودرو</div><div class="l-sub">شارژ خودکار با دوربین</div></div><span class="tag">فعال</span></a>
    </div>

    <div class="sec-title">تنظیمات</div>
    <div class="card card-p">
        <div class="srow"><span class="sr-ic" data-ic="bell"></span><span class="l-title" style="flex:1">اعلان‌ها</span><span class="switch on" data-sw><i></i></span></div>
        <div class="srow"><span class="sr-ic" data-ic="eye"></span><span class="l-title" style="flex:1">حالت تیره</span><span class="switch" data-sw id="darkSw"><i></i></span></div>
        <a class="srow" href="{{ route('app.privacy') }}"><span class="sr-ic" data-ic="shield"></span><span class="l-title" style="flex:1">حریم خصوصی و امنیت</span><span class="chev" data-ic="chevron"></span></a>
        <a class="srow" href="{{ route('app.password') }}"><span class="sr-ic" data-ic="key"></span><span class="l-title" style="flex:1">تغییر رمز عبور</span><span class="chev" data-ic="chevron"></span></a>
        <a class="srow" href="{{ route('app.support') }}"><span class="sr-ic" data-ic="headset"></span><span class="l-title" style="flex:1">پشتیبانی و تماس با ما</span><span class="chev" data-ic="chevron"></span></a>
        <a class="srow" href="{{ route('app.terms') }}" style="border-bottom:none"><span class="sr-ic" data-ic="receipt"></span><span class="l-title" style="flex:1">قوانین و مقررات</span><span class="chev" data-ic="chevron"></span></a>
    </div>

    <form method="POST" action="{{ route('app.logout') }}" class="mt20">
        @csrf
        <button type="submit" class="logout-btn" style="width:100%;border:none;cursor:pointer;font-family:inherit"><span data-ic="logout"></span> خروج از حساب</button>
    </form>
    <div class="center-col mt16 mb"><span class="fs12 muted">SafirCharge · نسخهٔ {{ Persian::digits('2.4.1') }}</span></div>
</div>
@endsection

@push('styles')
<style>
.profile{display:flex;align-items:center;gap:14px;padding:18px;border-radius:20px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-sm)}
.pf-av{width:60px;height:60px;border-radius:18px;background:linear-gradient(135deg,var(--accent),var(--orange));color:#fff;display:grid;place-items:center;font-weight:800;font-size:19px;flex:none}
.profile .ab-btn svg{width:18px;height:18px}

.srow{display:flex;align-items:center;gap:13px;padding:13px 0;border-bottom:1px solid var(--border-2)}
.sr-ic{width:38px;height:38px;border-radius:11px;background:var(--surface-3);color:var(--text-2);display:grid;place-items:center;flex:none}
.sr-ic svg{width:19px;height:19px}
.chev{display:inline-grid;width:18px;height:18px;color:var(--muted)}.chev svg{width:18px;height:18px}

.switch{width:46px;height:26px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;cursor:pointer;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .18s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-20px)}

.logout-btn{display:flex;align-items:center;justify-content:center;gap:8px;height:52px;border-radius:15px;background:var(--red-soft);color:var(--red);font-weight:700;font-size:14.5px;border:1px solid color-mix(in srgb,var(--red) 18%,transparent)}
.logout-btn svg{width:19px;height:19px}
.mb{margin-bottom:8px}
.plate-row{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:14px;padding-top:14px;border-top:1px solid var(--border-2)}
.auto-row{display:flex;align-items:flex-start;gap:12px;margin-top:14px;padding:13px;border-radius:14px;background:var(--accent-soft)}
.auto-row .ar-ic{display:inline-grid;width:17px;height:17px;color:var(--accent-strong)}.auto-row .ar-ic svg{width:17px;height:17px}
.veh-line{padding:11px 0;border-bottom:1px solid var(--border-2)}
.veh-line:first-child{padding-top:0}
.add-line{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:12px;margin-top:4px;border:1.5px dashed var(--border);border-radius:13px;background:var(--surface-2);color:var(--accent);font-weight:700;font-size:13px;text-decoration:none}
.add-line svg{width:17px;height:17px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  if(document.documentElement.getAttribute('data-theme')==='dark'){
    document.getElementById('darkSw').classList.add('on');
  }
  document.querySelectorAll('[data-sw]').forEach(function(s){
    s.addEventListener('click',function(){
      s.classList.toggle('on');
      if(s.id==='darkSw'){
        var dark=s.classList.contains('on');
        document.documentElement.setAttribute('data-theme',dark?'dark':'light');
        localStorage.setItem('cn-theme',dark?'dark':'light');
      }
    });
  });
})();
</script>
@endpush
