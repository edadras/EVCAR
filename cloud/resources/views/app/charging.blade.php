@extends('app.layouts.mobile', ['tab' => 'map', 'noTab' => true, 'darkStatus' => true, 'phoneClass' => 'charging-phone'])

@section('title', 'SafirCharge — شارژ در حال انجام')

@section('phone')
@php use App\Support\Persian; @endphp
@php $edgeMode = !$session && request()->boolean('edge'); @endphp
@if(!$session && !$edgeMode)
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.home') }}" class="ab-btn" style="background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.18);color:#fff"><span data-ic="back"></span></a>
    <div style="flex:1;text-align:center;color:#fff"><div class="fw8" style="font-size:16px">شارژ فعال نیست</div></div>
    <span style="width:42px"></span>
</div>
<div class="screen" style="padding-bottom:40px">
    <div style="text-align:center;padding:48px 24px;color:#9fb4c8">
        <div style="font-size:15px;line-height:1.8">جلسهٔ شارژ فعالی ندارید.</div>
        <a href="{{ route('app.home') }}" class="btn btn-orange mt20" style="display:inline-flex;height:48px;padding:0 28px">بازگشت به خانه</a>
    </div>
</div>
@else
@php
    if ($edgeMode) {
        $goalPct = (int) (($user->meta['app_prefs']['charge_target_soc'] ?? null) ?: 80);
        $powerKw = 0;
        $secs = 0;
        $costToman = 0;
        $walletBalance = (int) round((float) ($wallet?->balance ?? 0) / 10);
        $gaugePct = 0;
        $connectorLabel = 'کانکتور محلی';
    } else {
        $connectorLabel = $session->connector
            ? 'کانکتور '.Persian::digits($session->connector->connector_number)
            : ($session->charger?->charge_point_id ?? '—');
        $live = app(\App\Services\Charging\CloudChargingSessionService::class)->liveMetrics($session);
        $goalPct = (int) $live['goal'];
        $powerKw = (int) $live['power'];
        $secs = (int) $live['secs'];
        $costToman = (int) $live['cost'];
        $gaugePct = (int) $live['pct'];
        $walletBalance = (int) round((float) ($wallet?->balance ?? 0) / 10);
    }
@endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.home') }}" class="ab-btn" style="background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.18);color:#fff"><span data-ic="back"></span></a>
    <div style="flex:1;text-align:center;color:#fff">
        <div style="font-size:12px;opacity:.8">در حال شارژ</div>
        <div class="fw8" style="font-size:16px">@if($edgeMode)شارژ محلی ایستگاه@else{{ $session->station?->name }} · {{ $connectorLabel }}@endif</div>
    </div>
    <a href="{{ route('app.support') }}" class="ab-btn" style="background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.18);color:#fff"><span data-ic="headset"></span></a>
</div>

<div class="screen" style="padding-bottom:200px">
    <div class="gauge-big">
        <svg width="260" height="260" viewBox="0 0 260 260" id="bigGauge"></svg>
        <div class="gb-center">
            <div class="gb-bolt" data-ic="bolt"></div>
            <div class="gb-pct tnum" id="pct">@if($edgeMode)…@else{{ Persian::digits($gaugePct) }}٪@endif</div>
            <div class="gb-lbl">شارژ باتری</div>
            <div class="gb-add tnum" id="added">+{{ Persian::kwh($edgeMode ? 0 : (float) $session->energy_kwh, (float) $session?->energy_kwh < 10 ? 2 : 1) }} kWh افزوده شد</div>
        </div>
    </div>

    <div class="flow-note">
        <span class="flow-dot"></span><span class="flow-dot d2"></span><span class="flow-dot d3"></span>
        <span style="color:#cfe0f0;font-size:13px;font-weight:600">انرژی در حال انتقال به خودرو…</span>
    </div>

    <div class="stat-grid">
        <div class="stat"><div class="s-ic" data-ic="bolt"></div><div class="s-v tnum" id="power">{{ $powerKw ? Persian::digits($powerKw).' ' : '— ' }}<small>kW</small></div><div class="s-l">توان لحظه‌ای</div></div>
        <div class="stat"><div class="s-ic" data-ic="clock"></div><div class="s-v tnum" id="dur">—</div><div class="s-l">مدت شارژ</div></div>
        <div class="stat"><div class="s-ic" data-ic="coins"></div><div class="s-v tnum" id="cost">{{ Persian::money($costToman) }}</div><div class="s-l">هزینه (تومان)</div></div>
        <div class="stat"><div class="s-ic" data-ic="clock"></div><div class="s-v tnum" id="eta">— <small>دق</small></div><div class="s-l">تا شارژ کامل</div></div>
    </div>

    <div class="goal">
        <div class="row between" style="color:#cfe0f0;font-size:13px;font-weight:600">
            <span>هدف شارژ: {{ Persian::digits($goalPct) }}٪</span>
            <span class="tnum" id="goalLeft">{{ Persian::digits(max(0, $goalPct - $gaugePct)) }}٪ مانده</span>
        </div>
        <div class="goal-bar mt8"><i id="goalFill" style="width:{{ min($gaugePct, 100) }}%"></i><span class="goal-mark" style="inset-inline-start:{{ $goalPct }}%"></span></div>
    </div>
</div>

<div class="stopbar">
    <div class="row between mb">
        <span style="color:#9fb4c8;font-size:12px">پرداخت از: کیف پول SafirCharge</span>
        <span style="color:#fff;font-weight:700;font-size:12px" class="tnum">موجودی: {{ Persian::money($walletBalance) }}</span>
    </div>
    <form method="POST" action="{{ route('app.charging.stop') }}" id="stopForm" style="margin:0">
        @csrf
        <button class="btn btn-red" id="stopBtn" type="submit"><span data-ic="bolt"></span> توقف شارژ</button>
    </form>
</div>
@endif
@endsection

@push('styles')
<style>
.charging-phone{background:linear-gradient(180deg,#0d2438,#0a1826)}
html[data-theme="dark"] .charging-phone{background:linear-gradient(180deg,#0b1d2e,#060e18)}
.charging-phone .appbar .ab-btn svg{width:20px;height:20px}
.gauge-big{position:relative;display:grid;place-items:center;margin:14px auto 0;width:260px;height:260px}
.gb-center{position:absolute;text-align:center;color:#fff}
.gb-bolt{width:34px;height:34px;margin:0 auto 4px;color:#7ee2a6}
.gb-bolt svg{width:34px;height:34px;fill:#7ee2a6;stroke:#7ee2a6}
.gb-pct{font-size:56px;font-weight:800;line-height:1;letter-spacing:-.03em}
.gb-lbl{font-size:14px;color:#9fb4c8;margin-top:2px}
.gb-add{font-size:12.5px;color:#7ee2a6;font-weight:700;margin-top:8px;background:rgba(126,226,166,.12);display:inline-block;padding:4px 11px;border-radius:20px}
.flow-note{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:14px}
.flow-dot{width:7px;height:7px;border-radius:50%;background:#7ee2a6;opacity:.4;animation:flow 1.4s infinite}
.flow-dot.d2{animation-delay:.2s}.flow-dot.d3{animation-delay:.4s}
@keyframes flow{0%,100%{opacity:.3;transform:scale(.8)}50%{opacity:1;transform:scale(1.2)}}
.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:24px}
.stat{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:15px}
.s-ic{width:36px;height:36px;border-radius:11px;background:rgba(255,255,255,.1);color:#7fc8ff;display:grid;place-items:center;margin-bottom:10px}
.s-ic svg{width:19px;height:19px}
.s-v{font-size:22px;font-weight:800;color:#fff}.s-v small{font-size:12px;font-weight:600;color:#9fb4c8}
.s-l{font-size:11.5px;color:#9fb4c8;margin-top:2px}
.goal{margin-top:18px;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.09);border-radius:16px;padding:15px}
.goal-bar{height:9px;border-radius:20px;background:rgba(255,255,255,.12);position:relative;overflow:visible}
.goal-bar>i{display:block;height:100%;border-radius:20px;background:linear-gradient(90deg,#16A34A,#7ee2a6);transition:width 1s}
.goal-mark{position:absolute;top:-3px;width:3px;height:15px;background:#fff;border-radius:2px;opacity:.7}
.stopbar{position:absolute;bottom:0;left:0;right:0;background:rgba(8,18,28,.85);backdrop-filter:blur(14px);border-top:1px solid rgba(255,255,255,.08);padding:14px 20px calc(20px + env(safe-area-inset-bottom,0));z-index:20}
.stopbar .mb{margin-bottom:11px}
.stopbar .btn svg{width:18px;height:18px}
</style>
@endpush

@if(($session ?? null) || ($edgeMode ?? false))
@php
$chargingState = [
    'pct' => $gaugePct,
    'energy' => (float) ($edgeMode ? 0 : ($live['energy'] ?? $session->energy_kwh)),
    'eta' => $edgeMode ? null : ($live['eta'] ?? null),
    'state' => $edgeMode ? null : ($live['state'] ?? null),
    'secs' => $secs,
    'cost' => $costToman,
    'goal' => $goalPct,
    'power' => $powerKw,
    'edgeMode' => (bool) ($edgeMode ?? false),
];
@endphp
@push('scripts')
<script>
(function(){
  var R=110,C=130,sw=18,circ=2*Math.PI*R;
  var gauge=document.getElementById('bigGauge');
  var statusCloud=@json(route('app.api.charging.status'));
  var statusEdge=(window.SC_API&&window.SC_API.chargingEdge)||'/api/v1/app/charging/status';
  var state=@json($chargingState);
  var pct=state.pct, energy=state.energy, secs=state.secs;

  function fa(n){return Math.round(n).toLocaleString('fa-IR');}
  function faF(n){var d=Number(n)<10?2:1;return Number(n).toLocaleString('fa-IR',{minimumFractionDigits:d,maximumFractionDigits:d});}
  function fmtDur(s){
    var m=Math.floor(s/60),ss=s%60;
    return (m<10?'۰'+fa(m):fa(m))+':'+(ss<10?'۰'+fa(ss):fa(ss));
  }

  function render(){
    var len=circ*Math.min(pct,100)/100;
    gauge.innerHTML=
      '<defs><linearGradient id="gg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#16A34A"/><stop offset="1" stop-color="#7ee2a6"/></linearGradient></defs>'+
      '<circle cx="130" cy="130" r="110" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="18"/>'+
      '<circle cx="130" cy="130" r="110" fill="none" stroke="url(#gg)" stroke-width="18" stroke-linecap="round" stroke-dasharray="'+len+' '+(circ-len)+'" transform="rotate(-90 130 130)" style="transition:stroke-dasharray 1s"/>';
    document.getElementById('pct').textContent=(state.edgeMode&&pct<=0)?'…':fa(pct)+'٪';
    document.getElementById('added').textContent='+'+faF(energy)+' kWh افزوده شد';
    document.getElementById('cost').textContent=fa(state.cost);
    document.getElementById('goalFill').style.width=Math.min(pct,100)+'%';
    document.getElementById('dur').textContent=fmtDur(secs);
    var left=Math.max(0,state.goal-pct);
    document.getElementById('goalLeft').textContent=fa(left)+'٪ مانده';
    // زمان باقی‌مانده را سرور با ظرفیت باتری و توان واقعی محاسبه می‌کند.
    document.getElementById('eta').innerHTML=(state.eta?'~'+fa(state.eta):'—')+' <small>دق</small>';
    if(state.power)document.getElementById('power').innerHTML=fa(state.power)+' <small>kW</small>';
    var btn=document.getElementById('stopBtn');
    if(btn&&state.state==='stop_requested'){btn.disabled=true;btn.textContent='در حال توقف شارژ…';}
  }
  render();

  function poll(){
    var edgeFetcher=window.scFetch
      ? window.scFetch(statusCloud,statusEdge)
      : fetch(statusCloud,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});
    Promise.resolve(edgeFetcher).then(function(r){return r.json();})
      .then(function(d){
        if(d.active){
          pct=d.pct; energy=d.energy; secs=d.secs; state.cost=d.cost; state.goal=d.goal; state.power=d.power;
          state.eta=d.eta; state.state=d.state;
          render();
          return;
        }
        if(!state.edgeMode){window.location.href=@json(route('app.history'));return;}
        fetch(statusCloud,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}})
          .then(function(r){return r.json();})
          .then(function(cloud){
            if(cloud.active){
              pct=cloud.pct; energy=cloud.energy; secs=cloud.secs; state.cost=cloud.cost;
              state.goal=cloud.goal; state.power=cloud.power; render();
              return;
            }
            window.location.href=@json(route('app.history'));
          }).catch(function(){});
      }).catch(function(){});
  }
  setInterval(poll,5000);
  (window.scEnsureBackend?window.scEnsureBackend():Promise.resolve()).then(poll);

  var stopForm=document.getElementById('stopForm');
  if(stopForm){
    stopForm.addEventListener('submit',function(e){
      var backend=window.SC_ACTIVE_BACKEND;
      if(!backend||backend.kind!=='edge'||!window.scPost)return;
      e.preventDefault();
      var btn=document.getElementById('stopBtn');
      btn.disabled=true;
      window.scPost(@json(route('app.charging.stop')), (window.SC_API&&window.SC_API.chargingStopEdge)||'/api/v1/app/charging/stop', {})
        .then(function(r){return r.json().then(function(d){return {ok:r.ok,d:d};});})
        .then(function(res){
          if(!res.ok)throw new Error(res.d.message||'خطا در توقف');
          window.location.href=@json(route('app.home'));
        })
        .catch(function(err){alert(err.message||'خطا');btn.disabled=false;});
    });
  }
})();
</script>
@endpush
@endif
