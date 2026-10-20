@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — تأیید پرداخت')

@section('phone')
@php
use App\Support\Persian;
$presenter = app(\App\Services\App\MobileAppPresenter::class);
$amount = (int) request('amount', old('amount', 200000));
$amountFa = Persian::money($amount);
$phoneFormatted = $presenter->formatPhone($user->phone);
@endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.wallet') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">تأیید پرداخت</div></div>
</div>

<form method="POST" action="{{ route('app.topup.process') }}" id="topupForm">
@csrf
<input type="hidden" name="amount" id="amountInput" value="{{ $amount }}">
<input type="hidden" name="gateway" id="gatewayInput" value="mellat">

<div class="screen">
    @if($errors->any())
    <div class="card-lite" style="color:var(--red);margin-bottom:12px">{{ $errors->first() }}</div>
    @endif

    <div class="amt-hero rise">
        <div class="ah-l">مبلغ افزایش موجودی</div>
        <div class="ah-v tnum" id="amtBig">{{ $amountFa }} <small>تومان</small></div>
        <div class="ah-words" id="amtWords"></div>
    </div>

    <div class="card card-p mt16">
        <div class="srow"><span class="muted">مبلغ شارژ</span><b class="tnum" id="r1">{{ $amountFa }} تومان</b></div>
        <div class="srow"><span class="muted">کارمزد تراکنش</span><b class="tnum" style="color:var(--green)">رایگان</b></div>
        <div class="srow"><span class="muted">کیف پول مقصد</span><b>{{ $phoneFormatted }}</b></div>
        @if($rfidMasked && $rfidMasked !== '—')
        <div class="srow"><span class="muted">کارت RFID</span><b>{{ $rfidMasked }}</b></div>
        @endif
        <div class="srow total"><span>مبلغ قابل پرداخت</span><b class="tnum" id="r2">{{ $amountFa }} تومان</b></div>
    </div>

    <div class="sec-title">درگاه پرداخت</div>
    <div class="gateways" id="gws">
        <button type="button" class="gw active" data-g="mellat"><span class="gw-logo" style="background:#D6212A">به</span><span class="fw7 fs13">به‌پرداخت ملت</span><span class="gw-radio on"></span></button>
        <button type="button" class="gw" data-g="saman"><span class="gw-logo" style="background:#0a5">سپ</span><span class="fw7 fs13">سامان (سپ)</span><span class="gw-radio"></span></button>
        <button type="button" class="gw" data-g="zarin"><span class="gw-logo" style="background:#ffc107;color:#5a4500">زر</span><span class="fw7 fs13">زرین‌پال</span><span class="gw-radio"></span></button>
    </div>

    <div class="secure-note">
        <span data-ic="shield"></span>
        <span>اتصال شما رمزنگاری‌شده است. پس از تأیید، به درگاه بانکی منتقل می‌شوید.</span>
    </div>
</div>

<div class="savebar">
    <button type="submit" class="btn btn-orange" id="payBtn" style="height:52px;width:100%;font-size:15px"><span data-ic="card"></span> انتقال به درگاه پرداخت</button>
</div>
</form>
@endsection

@push('overlays')
<div class="redirect" id="redirect">
    <div class="rd-spinner"></div>
    <div class="fw7 mt16" style="font-size:15px">در حال انتقال به درگاه بانکی…</div>
    <div class="fs12 muted mt8">لطفاً صفحه را نبندید</div>
</div>
@endpush

@push('styles')
<style>
.amt-hero{text-align:center;padding:26px 20px;border-radius:22px;color:#fff;background:linear-gradient(140deg,var(--accent),color-mix(in srgb,var(--accent) 45%,var(--orange)));box-shadow:0 16px 34px rgba(var(--accent-rgb),.36);position:relative;overflow:hidden}
.amt-hero::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 85% 0%,rgba(255,255,255,.22),transparent 48%)}
.ah-l{font-size:13px;opacity:.9;position:relative;z-index:1}
.ah-v{font-size:34px;font-weight:800;margin:8px 0 4px;position:relative;z-index:1}.ah-v small{font-size:15px;font-weight:600;opacity:.9}
.ah-words{font-size:12.5px;opacity:.85;position:relative;z-index:1}
.srow{display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:9px 0;border-bottom:1px solid var(--border-2)}
.srow:last-child{border-bottom:none}
.srow.total{font-size:15px;font-weight:800;margin-top:4px;padding-top:13px}
.gateways{display:flex;flex-direction:column;gap:10px}
.gw{display:flex;align-items:center;gap:12px;padding:13px 14px;border:1.5px solid var(--border);border-radius:14px;background:var(--surface);font-family:inherit;width:100%;text-align:inherit}
.gw.active{border-color:var(--accent);background:var(--accent-soft)}
.gw-logo{width:40px;height:40px;border-radius:11px;display:grid;place-items:center;color:#fff;font-weight:800;font-size:14px;flex:none}
.gw-radio{margin-inline-start:auto;width:22px;height:22px;border-radius:50%;border:2px solid var(--border);flex:none;display:grid;place-items:center}
.gw-radio.on{border-color:var(--accent)}.gw-radio.on::after{content:"";width:11px;height:11px;border-radius:50%;background:var(--accent)}
.secure-note{display:flex;align-items:flex-start;gap:10px;margin-top:16px;padding:13px;border-radius:14px;background:var(--surface-2);border:1px solid var(--border-2);font-size:12px;color:var(--text-2);line-height:1.7}
.secure-note svg{width:19px;height:19px;color:var(--green);flex:none;margin-top:1px}
.savebar{position:absolute;bottom:0;left:0;right:0;background:color-mix(in srgb,var(--surface) 94%,transparent);backdrop-filter:blur(14px);border-top:1px solid var(--border);padding:14px 20px calc(20px + env(safe-area-inset-bottom,0));z-index:20}
.savebar .btn svg{width:18px;height:18px}
.redirect{position:absolute;inset:0;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;opacity:0;visibility:hidden;transition:opacity .25s;z-index:80;text-align:center;padding:30px}
.redirect.show{opacity:1;visibility:visible}
.rd-spinner{width:52px;height:52px;border-radius:50%;border:4px solid var(--surface-3);border-top-color:var(--accent);animation:spin .8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var FA='۰۱۲۳۴۵۶۷۸۹';
  function toFa(s){return String(s).replace(/[0-9]/g,function(d){return FA[d];});}
  function group(n){return String(n).replace(/\B(?=(\d{3})+(?!\d))/g,'٬');}
  function words(num){var n=parseInt(num,10);if(!n)return '';var parts=[];var mil=Math.floor(n/1000000),th=Math.floor((n%1000000)/1000),rest=n%1000;if(mil)parts.push((mil>1?toFa(mil)+' ':'')+'میلیون');if(th)parts.push((th>1?toFa(th)+' ':'')+'هزار');if(rest)parts.push(toFa(rest));return parts.join(' و ')+' تومان';}

  var amt=parseInt(document.getElementById('amountInput').value,10)||200000;
  try{var s=localStorage.getItem('sc-topup');if(s&&parseInt(s,10))amt=parseInt(s,10);}catch(e){}
  document.getElementById('amountInput').value=amt;
  var fa=toFa(group(amt));
  document.getElementById('amtBig').innerHTML=fa+' <small>تومان</small>';
  document.getElementById('amtWords').textContent=words(amt);
  document.getElementById('r1').textContent=fa+' تومان';
  document.getElementById('r2').textContent=fa+' تومان';

  document.querySelectorAll('#gws .gw').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#gws .gw').forEach(function(x){x.classList.remove('active');x.querySelector('.gw-radio').classList.remove('on');});
      b.classList.add('active');b.querySelector('.gw-radio').classList.add('on');
      document.getElementById('gatewayInput').value=b.getAttribute('data-g');
    });
  });

  document.getElementById('topupForm').addEventListener('submit',function(){
    document.getElementById('redirect').classList.add('show');
  });
})();
</script>
@endpush
