@extends('app.layouts.mobile', ['tab' => 'home'])

@section('title', 'QUAD EV — پیشخان')

@section('phone')
@php use App\Support\Persian; @endphp
<div class="appbar" style="padding-top:2px">
    <div style="flex:1">
        <div class="ab-sub">{{ $headerDate }}</div>
        <div class="ab-title">سلام، {{ $firstName }} 👋</div>
    </div>
    <a href="{{ route('app.notifications') }}" class="ab-btn"><span data-ic="bell"></span><span class="dot" data-unread-dot @if(!$unreadNotifications) hidden @endif></span></a>
</div>

<div class="screen">

    <a href="{{ route('app.map') }}" class="fx-banner home-hero" id="homeHero" @if($activeCard) hidden @endif>
        <img class="fx-kenburns" src="/assets/app-img/home-hero-sm.webp" srcset="/assets/app-img/home-hero-sm.webp 500w, /assets/app-img/home-hero.webp 1000w" sizes="430px" alt="">
        <div class="fb-body">
            <div class="fb-title">شارژ هوشمند،<br>سفر بی‌دغدغه</div>
            <div class="fb-sub">نزدیک‌ترین شارژر آزاد را پیدا کنید و با یک اسکن شارژ را شروع کنید.</div>
            <span class="fb-cta"><span data-ic="pin"></span> پیدا کردن ایستگاه</span>
        </div>
    </a>

    <a href="{{ route('app.charging') }}" class="active-card rise" id="activeCard" @if(!$activeCard) hidden @endif>
        <div class="row between">
            <span class="pill" style="background:rgba(255,255,255,.18);color:#fff"><span class="pdot" style="background:#7ee2a6"></span>در حال شارژ</span>
            <span class="ac-station" id="acStation"></span>
        </div>
        <div class="row mt16" style="gap:18px">
            <div class="ac-gauge">
                <svg width="92" height="92" viewBox="0 0 92 92" id="homeGauge"></svg>
                <div class="ac-gp tnum" id="acPct"></div>
            </div>
            <div style="flex:1">
                <div class="ac-stat"><span>انرژی تحویلی</span><b class="tnum" id="acEnergy"></b></div>
                <div class="ac-stat"><span>توان لحظه‌ای</span><b class="tnum" id="acPower"></b></div>
                <div class="ac-stat"><span>زمان باقی‌مانده</span><b class="tnum" id="acEta"></b></div>
            </div>
        </div>
        <div class="ac-foot">
            <span class="muted2">هزینهٔ تاکنون</span>
            <b class="tnum" style="font-size:17px" id="acCost"></b>
            <span class="ac-go">مشاهدهٔ جلسه <span data-ic="chevron" class="cv"></span></span>
        </div>
    </a>

    <a href="{{ route('app.wallet') }}" class="wstrip rise" style="animation-delay:.05s">
        <div class="ws-ic" data-ic="wallet"></div>
        <div style="flex:1">
            <div class="muted fs12">موجودی کیف پول</div>
            <div class="fw8 tnum" style="font-size:18px"><span id="homeBalance" data-countup>{{ Persian::money(($user->wallet?->balance ?? 0) / 10) }}</span> <span class="fs12 t2">تومان</span></div>
        </div>
        <span class="ws-add"><span data-ic="plus"></span> افزایش</span>
    </a>

    @if($featuredOffer)
    <a href="{{ route('app.offers') }}" class="offer-banner fx-sheen rise" style="animation-delay:.08s">
        <img class="ob-art fx-float" src="/assets/app-img/offers.webp" alt="" aria-hidden="true">
        <div style="flex:1">
            <div class="fw8" style="font-size:14px">{{ $featuredOffer->name }}@if($featuredOffer->discount_percent) — {{ Persian::digits($featuredOffer->discount_percent) }}٪@endif</div>
            <div class="fs12" style="opacity:.9">{{ Persian::digits($campaigns->count()) }} طرح جدید برای شما فعال است</div>
        </div>
        <span class="chev" data-ic="chevron"></span>
    </a>
    @endif

    <a href="{{ route('app.navigator') }}" class="fx-banner nav-banner mt16">
        <img class="fx-kenburns" src="/assets/app-img/navigator-sm.webp" srcset="/assets/app-img/navigator-sm.webp 500w, /assets/app-img/navigator.webp 1000w" sizes="430px" alt="">
        <div class="fb-body">
            <div class="fb-title">مسیریاب برقی</div>
            <div class="fb-sub">مسیر سفر، مصرف انرژی و توقف‌های شارژ را از قبل ببینید.</div>
            <span class="fb-cta"><span data-ic="nav"></span> برنامه‌ریزی سفر</span>
        </div>
    </a>

    <div class="qgrid mt16">
        <a class="qitem" href="{{ route('app.scan') }}"><span class="qic ic-blue" data-ic="qr"></span>اسکن QR</a>
        <a class="qitem" href="{{ route('app.reserve') }}"><span class="qic ic-green" data-ic="calendar"></span>رزرو شارژر</a>
        <a class="qitem" href="{{ route('app.offers') }}"><span class="qic ic-violet" data-ic="gift"></span>طرح‌ها</a>
        <a class="qitem" href="{{ route('app.history') }}"><span class="qic ic-orange" data-ic="receipt"></span>سوابق</a>
    </div>
    <a href="{{ route('app.assistant') }}" class="ev-nav-banner mt16">
        <span class="fx-art enb-art"><img class="fx-float" src="/assets/app-img/assistant.webp" alt=""></span>
        <div style="flex:1">
            <div class="fw8" style="font-size:14.5px">دستیار هوشمند پشتیبانی</div>
            <div class="fs12 muted">سؤال دارید؟ همین حالا بپرسید — پاسخ فوری</div>
        </div>
        <span class="chev" data-ic="chevron"></span>
    </a>

    <div class="sec-title">نزدیک‌ترین ایستگاه‌ها <a href="{{ route('app.map') }}">نقشه</a></div>
    <div class="seg" style="margin-bottom:14px" id="seg">
        <button class="active">همه</button>
        <button>شارژ سریع</button>
        <button>عمومی</button>
    </div>

    <div id="stations" style="display:flex;flex-direction:column;gap:12px"></div>

</div>
@endsection

@push('styles')
<style>
.active-card{
  display:block; border-radius:24px; padding:18px; color:#fff; position:relative; overflow:hidden;
  background:linear-gradient(140deg,var(--accent),color-mix(in srgb,var(--accent) 48%,var(--orange)));
  box-shadow:0 16px 34px rgba(var(--accent-rgb),.4);
}
.active-card::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 88% 6%,rgba(255,255,255,.22),transparent 42%)}
.active-card>*{position:relative;z-index:1}
.ac-station{font-size:12px;opacity:.9}
.ac-gauge{position:relative;display:grid;place-items:center;flex:none}
.ac-gp{position:absolute;font-size:22px;font-weight:800}
.ac-stat{display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:3px 0}
.ac-stat span{opacity:.85}
.ac-foot{display:flex;align-items:center;gap:8px;margin-top:14px;padding-top:14px;border-top:1px solid rgba(255,255,255,.2)}
.muted2{font-size:12px;opacity:.85}
.ac-go{margin-inline-start:auto;display:inline-flex;align-items:center;gap:3px;font-size:13px;font-weight:700;background:rgba(255,255,255,.2);padding:7px 12px;border-radius:11px}
.ac-go .cv{display:inline-grid;width:15px;height:15px}.ac-go .cv svg{width:15px;height:15px}

.wstrip{display:flex;align-items:center;gap:13px;margin-top:14px;padding:14px;border-radius:var(--r-lg);background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-sm)}
.ws-ic{width:46px;height:46px;border-radius:14px;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;flex:none}
.ws-ic svg{width:23px;height:23px}
.ws-add{display:inline-flex;align-items:center;gap:5px;background:var(--accent);color:#fff;font-size:12.5px;font-weight:700;padding:9px 13px;border-radius:12px}
.ws-add svg{width:15px;height:15px}
.ev-nav-banner{display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:var(--r-lg);background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-sm);text-decoration:none;color:inherit}
.enb-ic{width:44px;height:44px;border-radius:14px;background:linear-gradient(135deg,var(--accent),var(--orange));color:#fff;display:grid;place-items:center;flex:none}
.enb-ic svg{width:22px;height:22px}
.enb-art{width:52px;height:52px;flex:none;margin:-6px 0}
.home-hero{min-height:178px;display:flex;align-items:center}
.nav-banner{min-height:150px;display:flex;align-items:flex-end}
.nav-banner::after{background:linear-gradient(0deg,rgba(6,22,40,.85) 0%,rgba(6,22,40,.35) 60%,rgba(6,22,40,.05) 100%)}
.nav-banner .fb-body{max-width:100%}
.offer-banner{position:relative;overflow:hidden}
.ob-art{width:58px;height:58px;flex:none;margin:-8px 0;filter:drop-shadow(0 6px 10px rgba(0,0,0,.25))}
.station .st-img img{width:100%;height:100%;object-fit:cover}
</style>
@endpush

@push('scripts')
<script>
(function(){
  function esc(s){return String(s==null?'':s).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  var segIdx=0;

  function renderActive(a){
    var card=document.getElementById('activeCard');
    document.getElementById('homeHero').hidden=!!a;
    if(!a){card.hidden=true;return;}
    card.hidden=false;
    document.getElementById('acStation').textContent=a.station;
    document.getElementById('acPct').textContent=Number(a.pct).toLocaleString('fa-IR')+'٪';
    document.getElementById('acEnergy').textContent=a.energy+' kWh';
    document.getElementById('acPower').textContent=a.power;
    document.getElementById('acEta').textContent=a.eta;
    document.getElementById('acCost').textContent=a.cost+' تومان';
    var circ=2*Math.PI*40,len=circ*Math.max(0,Math.min(100,a.pct))/100;
    document.getElementById('homeGauge').innerHTML=
      '<circle cx="46" cy="46" r="40" fill="none" stroke="rgba(255,255,255,.25)" stroke-width="8"/>'+
      '<circle cx="46" cy="46" r="40" fill="none" stroke="#fff" stroke-width="8" stroke-linecap="round" stroke-dasharray="'+len+' '+(circ-len)+'" transform="rotate(-90 46 46)"/>';
  }

  function applySeg(){
    document.querySelectorAll('.station').forEach(function(c){
      var cat=c.getAttribute('data-cat');
      c.style.display=(segIdx===0||(segIdx===1&&cat==='fast')||(segIdx===2&&cat==='public'))?'':'none';
    });
  }

  function renderStations(st){
    document.getElementById('stations').innerHTML=st.map(function(s){
      return '<a href="'+esc(s.url)+'" class="station" data-cat="'+esc(s.cat)+'">'+
        '<div class="st-img"><img src="'+esc(s.image||'/assets/app-img/station-default-sm.webp')+'" alt="" loading="lazy"></div>'+
        '<div style="flex:1">'+
          '<div class="row between"><span class="st-name">'+esc(s.name)+'</span><span class="pill '+esc(s.status_pill)+'"><span class="pdot"></span>'+esc(s.status)+'</span></div>'+
          '<div class="st-meta">'+esc(s.type)+'</div>'+
          '<div class="st-foot">'+
            '<span class="row" style="gap:4px;color:var(--text-2)">'+window.icon('nav')+esc(s.distance)+'</span>'+
            '<span class="muted">·</span><span class="muted">'+esc(s.free)+'</span>'+
            '<span style="margin-inline-start:auto" class="fw7 tnum">'+esc(s.price)+' <span class="fs12 muted">ت/kWh</span></span>'+
          '</div>'+
        '</div>'+
      '</a>';
    }).join('');
    document.querySelectorAll('.station .st-foot svg').forEach(function(s){s.style.width='14px';s.style.height='14px';});
    applySeg();
  }

  renderActive(@json($activeCard));
  renderStations(@json($nearbyStations));

  document.querySelectorAll('#seg button').forEach(function(b,idx){
    b.addEventListener('click',function(){
      document.querySelectorAll('#seg button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');
      segIdx=idx;applySeg();
    });
  });

  window.scLive('home',10000,function(d){
    renderActive(d.active);
    if(d.balance!=null)document.getElementById('homeBalance').textContent=d.balance;
    if(d.stations)renderStations(d.stations);
  });
})();
</script>
@endpush
