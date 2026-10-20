@extends('app.layouts.mobile', ['tab' => 'map', 'noTab' => true])

@section('title', 'SafirCharge — جزئیات شارژر')

@section('phone')
@php use App\Support\Persian; @endphp
@if(!$station)
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.map') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">شارژر</div></div>
</div>
<div class="screen">
    <div class="card-lite">ایستگاه یافت نشد یا غیرفعال است. <a href="{{ route('app.map') }}">بازگشت به نقشه</a></div>
</div>
@else
@php
    $rating = $station->meta['rating'] ?? null;
    $reviews = $station->meta['reviews'] ?? null;
    $distance = $station->meta['distance_km'] ?? null;
    $amenities = $station->meta['amenities'] ?? [];
    $amenityIcons = ['پارکینگ' => 'car', 'کافه' => 'coins', 'نگهبانی' => 'shield', 'WiFi رایگان' => 'bolt'];
    $hoursLabel = is_array($station->operating_hours) && !empty($station->operating_hours['label'])
        ? $station->operating_hours['label']
        : '۲۴ ساعته';
    $firstConn = collect($connectorsJs)->firstWhere('st', 'free') ?? ($connectorsJs[0] ?? null);
@endphp
<div class="hero">
    <div class="hero-bg"><span data-ic="plug"></span></div>
    <a href="{{ route('app.map') }}" class="hero-back"><span data-ic="back"></span></a>
    <button class="hero-share" type="button" onclick="if(navigator.share){navigator.share({title:@json($station->name),url:location.href})}"><span data-ic="share"></span></button>
    <span class="hero-pill pill pill-green"><span class="pdot"></span>{{ Persian::digits($freeCount) }} کانکتور آزاد</span>
</div>

<div class="screen" style="padding-top:16px;padding-bottom:100px">
    <div class="row between">
        <div>
            <h2 style="font-size:21px">{{ $station->name }}</h2>
            <div class="muted fs13 mt4">{{ $station->city }}@if($station->address)، {{ $station->address }}@endif</div>
        </div>
    </div>

    <div class="chg-tags">
        @if($rating)
        <span class="tag chg-tag"><b class="tnum">{{ Persian::digits($rating) }}</b> <span class="chg-star">★</span>@if($reviews) ({{ Persian::digits($reviews) }})@endif</span>
        @endif
        @if($distance)
        <span class="tag chg-tag"><span class="tnum">{{ Persian::digits($distance) }}</span> کیلومتر</span>
        @endif
        <span class="tag chg-tag chg-tag-hours">{{ $hoursLabel }}</span>
    </div>

    <div class="chg-toolbar">
        @if($station->latitude && $station->longitude)
        <a href="https://www.google.com/maps/dir/?api=1&destination={{ $station->latitude }},{{ $station->longitude }}" class="chg-tool-btn" target="_blank" rel="noopener"><span data-ic="nav"></span><span>مسیریابی</span></a>
        @else
        <button class="chg-tool-btn" type="button" disabled><span data-ic="nav"></span><span>مسیریابی</span></button>
        @endif
        <a href="{{ route('app.reserve', ['station' => $station->code]) }}" class="chg-tool-btn"><span data-ic="calendar"></span><span>رزرو</span></a>
        <button class="chg-tool-icon {{ $favorite ? 'fav-on' : '' }}" type="button" id="favBtn" aria-label="علاقه‌مندی" aria-pressed="{{ $favorite ? 'true' : 'false' }}"><span data-ic="star"></span></button>
    </div>

    <div class="auth-panel">
        <div class="auth-banner" id="authBanner">
            <div class="auth-banner-ic" data-ic="shield"></div>
            <div class="auth-banner-body">
                <div class="fw7 fs13" id="authBannerTitle">برای شروع شارژ احراز هویت لازم است</div>
                <div class="fs12 muted mt4" id="authBannerText">QR کانکتور را اسکن کنید، کارت RFID را بزنید، یا منتظر تشخیص پلاک باشید.</div>
            </div>
        </div>
        <div class="auth-actions-grid" id="authActions">
            <a href="{{ route('app.scan') }}" class="auth-act-btn"><span class="auth-act-ic" data-ic="qr"></span><span>اسکن QR</span></a>
            <a href="{{ route('app.arrival') }}" class="auth-act-btn"><span class="auth-act-ic" data-ic="scan"></span><span>تأیید پلاک</span></a>
        </div>
    </div>

    <div class="sec-title">کانکتور مجاز</div>
    <div style="display:flex;flex-direction:column;gap:10px" id="connectors"></div>

    @if($freeCount < 1)
    <div class="watch-card {{ $watching ? 'on' : '' }}" id="watchCard">
        <div class="watch-ic" data-ic="bell"></div>
        <div style="flex:1">
            <div class="fw7 fs13">همهٔ کانکتورها مشغول‌اند</div>
            <div class="fs12 muted mt4" id="watchText">{{ $watching ? 'به محض آزاد شدن به شما اعلان می‌دهیم.' : 'وقتی کانکتوری آزاد شد خبرتان کنیم؟' }}</div>
        </div>
        <button type="button" class="btn btn-sm {{ $watching ? 'btn-ghost' : '' }}" id="watchBtn" style="width:auto;padding:0 14px">{{ $watching ? 'لغو' : 'خبرم کن' }}</button>
    </div>
    @endif

    <div class="sec-title">تعرفه</div>
    <div class="card card-p">
        <div class="lrow" style="padding-top:0">
            <div class="l-ic ic-blue" data-ic="bolt"></div>
            <div class="l-main"><div class="l-title">هزینهٔ انرژی</div><div class="l-sub">شارژ سریع DC</div></div>
            <div class="l-end tnum">@if($tariffEnergy){{ Persian::money($tariffEnergy) }} <span class="fs12 muted">ت/kWh</span>@else—@endif</div>
        </div>
        <div class="lrow">
            <div class="l-ic ic-amber" data-ic="clock"></div>
            <div class="l-main"><div class="l-title">هزینهٔ اشغال</div><div class="l-sub">پس از پایان شارژ@if(($tariffIdleGrace ?? null)) · {{ Persian::digits($tariffIdleGrace) }} دقیقه رایگان@endif</div></div>
            <div class="l-end tnum">@if($tariffIdle){{ Persian::money($tariffIdle) }} <span class="fs12 muted">ت/دقیقه</span>@else—@endif</div>
        </div>
        <div class="lrow" style="padding-bottom:0">
            <div class="l-ic ic-green" data-ic="leaf"></div>
            <div class="l-main"><div class="l-title">ساعات کم‌باری</div><div class="l-sub">{{ $tariffOffpeakHours }}</div></div>
            <div class="l-end tnum" style="color:var(--green)">@if($tariffOffpeak){{ Persian::money($tariffOffpeak) }} <span class="fs12 muted">ت/kWh</span>@else—@endif</div>
        </div>
    </div>

    @if(count($amenities))
    <div class="sec-title">امکانات رفاهی</div>
    <div class="row" style="gap:18px;flex-wrap:wrap;color:var(--text-2);font-size:12.5px;font-weight:600">
        @foreach($amenities as $amenity)
        <span class="amen"><span data-ic="{{ $amenityIcons[$amenity] ?? 'bolt' }}"></span>{{ $amenity }}</span>
        @endforeach
    </div>
    @endif

    <div class="sec-title row between" style="align-items:center"><span>امتیاز و نظرات</span>@if(($reviewsData['count'] ?? 0) > 0)<span class="fs12 muted">{{ Persian::digits($reviewsData['count']) }} نظر</span>@endif</div>
    <div class="card card-p">
        @if(($reviewsData['count'] ?? 0) > 0)
        <div class="rv-sum">
            <div class="rv-big"><b class="tnum">{{ Persian::digits(number_format((float) $reviewsData['rating'], 1)) }}</b><div class="rv-stars">@for($i = 1; $i <= 5; $i++)<span class="{{ $i <= round($reviewsData['rating']) ? 'on' : '' }}">★</span>@endfor</div></div>
            <div style="flex:1">
                @foreach($reviewsData['distribution'] as $star => $cnt)
                <div class="rv-bar"><span class="tnum">{{ Persian::digits($star) }}</span><i><b style="width:{{ $reviewsData['count'] ? round(100 * $cnt / $reviewsData['count']) : 0 }}%"></b></i></div>
                @endforeach
            </div>
        </div>
        @if($reviewsData['top_tags'])
        <div class="rv-tags">@foreach($reviewsData['top_tags'] as $t)<span class="tag">{{ $t['tag'] }} · {{ Persian::digits($t['count']) }}</span>@endforeach</div>
        @endif
        @foreach($reviewsData['items'] as $rv)
        <div class="rv-item">
            <div class="row between"><span class="fw7 fs13">{{ $rv['author'] }}</span><span class="rv-stars sm">@for($i = 1; $i <= 5; $i++)<span class="{{ $i <= $rv['rating'] ? 'on' : '' }}">★</span>@endfor</span></div>
            @if($rv['comment'])<div class="fs13 mt4" style="line-height:1.8">{{ $rv['comment'] }}</div>@endif
            @if($rv['tags'])<div class="fs12 muted mt4">{{ implode(' · ', $rv['tags']) }}</div>@endif
            @if($rv['reply'])<div class="rv-reply"><b>پاسخ SafirCharge:</b> {{ $rv['reply'] }}</div>@endif
            <div class="fs12 muted mt4">{{ $rv['date'] }}</div>
        </div>
        @endforeach
        @else
        <div class="fs13 muted">هنوز نظری ثبت نشده است. پس از شارژ در این ایستگاه از صفحهٔ رسید جلسه امتیاز دهید.</div>
        @endif
    </div>

    <button type="button" class="report-btn" id="reportOpen"><span data-ic="alert"></span> گزارش خرابی یا مشکل این ایستگاه</button>
</div>

<div class="sheet-back" id="reportSheet" hidden>
    <form class="sheet" method="POST" action="{{ route('app.station.report', $station->code) }}">@csrf
        <div class="sheet-grip"></div>
        <div class="fw8" style="font-size:16px">گزارش مشکل — {{ $station->name }}</div>
        <div class="fs12 muted mt4">گزارش مستقیم برای تیم فنی ارسال و از بخش پشتیبانی قابل پیگیری است.</div>
        <div class="prob-list">
            @foreach($problems as $k => $label)
            <label class="prob"><input type="radio" name="problem" value="{{ $k }}" @checked($loop->first) required><span>{{ $label }}</span></label>
            @endforeach
        </div>
        <select name="connector" class="sheet-in">
            <option value="">کانکتور (اختیاری)</option>
            @foreach($connectorsJs as $c)<option value="{{ $c['id'] }}">{{ $c['name'] }}</option>@endforeach
        </select>
        <textarea name="description" class="sheet-in" rows="3" maxlength="1500" placeholder="توضیح کوتاه (مثلاً پیغام خطای روی صفحه)"></textarea>
        <div class="row" style="gap:10px;margin-top:12px">
            <button type="button" class="btn btn-ghost" id="reportClose" style="flex:1">انصراف</button>
            <button type="submit" class="btn btn-orange" style="flex:2">ثبت گزارش</button>
        </div>
    </form>
</div>

<div class="startbar">
    <div>
        <div class="muted fs12">کانکتور انتخاب‌شده</div>
        <div class="fw8" id="selName">{{ $firstConn['name'] ?? '—' }}</div>
    </div>
    <form method="POST" action="{{ route('app.charging.start') }}" id="startForm" style="margin:0">
        @csrf
        <input type="hidden" name="connector_id" id="connectorId" value="{{ $firstConn['db_id'] ?? '' }}">
        <label class="fs12 muted" style="display:inline-flex;align-items:center;gap:6px;margin-inline-end:8px">شارژ فعلی
            <select name="start_soc" id="startSoc" style="padding:6px 8px;border-radius:10px;border:1px solid var(--border-2);background:var(--bg-2,transparent);color:inherit">
                @foreach([5,10,15,20,25,30,40,50,60,70,80] as $pct)
                <option value="{{ $pct }}" @selected($pct === 20)>{{ Persian::digits($pct) }}٪</option>
                @endforeach
            </select>
        </label>
        <button type="submit" class="btn btn-orange" id="startBtn" style="width:auto;padding:0 24px" disabled><span data-ic="bolt"></span> شروع شارژ</button>
    </form>
</div>
@endif
@endsection

@push('styles')
<style>
.fav-on{color:var(--amber,#F59E0B)!important;border-color:var(--amber,#F59E0B)!important}
.watch-card{display:flex;align-items:center;gap:12px;margin-top:12px;padding:14px;border-radius:16px;background:var(--accent-soft);border:1px dashed var(--accent)}
.watch-card.on{background:var(--green-soft);border-color:var(--green)}
.watch-ic{width:40px;height:40px;border-radius:12px;background:var(--surface);display:grid;place-items:center;color:var(--accent);flex:none}.watch-ic svg{width:20px;height:20px}
.rv-sum{display:flex;gap:18px;align-items:center}
.rv-big{text-align:center}.rv-big b{font-size:34px;font-weight:800;display:block;line-height:1}
.rv-stars span{color:var(--border);font-size:14px}.rv-stars span.on{color:#F59E0B}.rv-stars.sm span{font-size:12px}
.rv-bar{display:flex;align-items:center;gap:8px;font-size:11px;color:var(--muted)}
.rv-bar i{flex:1;height:6px;border-radius:4px;background:var(--surface-2);overflow:hidden;display:block}.rv-bar i b{display:block;height:100%;background:#F59E0B;border-radius:4px}
.rv-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:12px}
.rv-item{border-top:1px solid var(--border-2);margin-top:12px;padding-top:12px}
.rv-reply{margin-top:8px;padding:8px 10px;border-radius:10px;background:var(--surface-2);font-size:12.5px}
.report-btn{width:100%;margin-top:14px;height:46px;border-radius:14px;border:1px solid var(--red-soft,#fde2e2);background:var(--surface);color:var(--red);font-family:inherit;font-weight:700;font-size:13px;display:flex;align-items:center;justify-content:center;gap:8px;cursor:pointer}
.report-btn svg{width:18px;height:18px}
.sheet-back{position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:60;display:flex;align-items:flex-end;justify-content:center}
.sheet-back[hidden]{display:none}
.sheet{width:100%;max-width:480px;background:var(--surface);border-radius:22px 22px 0 0;padding:14px 18px calc(18px + env(safe-area-inset-bottom,0));max-height:88vh;overflow:auto}
.sheet-grip{width:44px;height:5px;border-radius:3px;background:var(--border);margin:0 auto 12px}
.prob-list{display:flex;flex-direction:column;gap:8px;margin:14px 0}
.prob{display:flex;align-items:center;gap:10px;padding:11px 12px;border:1.5px solid var(--border);border-radius:13px;font-size:13.5px;cursor:pointer}
.prob:has(input:checked){border-color:var(--accent);background:var(--accent-soft)}
.sheet-in{width:100%;margin-top:8px;border:1.5px solid var(--border);background:var(--surface-2);border-radius:13px;padding:11px 12px;font-family:inherit;font-size:13.5px;color:var(--text)}
.hero{position:relative;height:200px;flex:none}
.hero-bg{position:absolute;inset:0;background:linear-gradient(140deg,var(--accent),color-mix(in srgb,var(--accent) 50%,var(--orange)));display:grid;place-items:center;color:rgba(255,255,255,.5)}
.hero-bg svg{width:72px;height:72px}
.hero-back,.hero-share{position:absolute;top:54px;width:42px;height:42px;border-radius:13px;background:rgba(255,255,255,.92);color:var(--text);display:grid;place-items:center;box-shadow:var(--shadow-sm);border:none;text-decoration:none}
.hero-back{right:18px}.hero-share{left:18px}
.hero-back svg,.hero-share svg{width:20px;height:20px}
.hero-pill{position:absolute;bottom:16px;right:18px}
.conn-card{display:flex;align-items:center;gap:13px;padding:14px;border:1.5px solid var(--border);border-radius:16px;background:var(--surface);cursor:pointer;transition:all .15s}
.conn-card.sel{border-color:var(--accent);background:var(--accent-soft)}
.conn-card.busy{opacity:.55}
.conn-card .ci{width:46px;height:46px;border-radius:13px;display:grid;place-items:center;flex:none}
.conn-card .ci svg{width:22px;height:22px}
.conn-radio{width:22px;height:22px;border-radius:50%;border:2px solid var(--border);flex:none;display:grid;place-items:center}
.conn-card.sel .conn-radio{border-color:var(--accent)}
.conn-card.sel .conn-radio::after{content:"";width:11px;height:11px;border-radius:50%;background:var(--accent)}
.conn-card.locked{cursor:default;opacity:.72}
.conn-card.locked:not(.sel){pointer-events:none}
.conn-card.auth-ok{border-color:color-mix(in srgb,var(--green) 45%,var(--border))}
.chg-tags{display:flex;flex-wrap:wrap;gap:8px;margin-top:12px}
.chg-tag{display:inline-flex;align-items:center;gap:4px}
.chg-star{color:var(--orange)}
.chg-tag-hours{background:var(--green-soft);color:var(--green)}
.chg-toolbar{display:grid;grid-template-columns:1fr 1fr 48px;gap:10px;margin-top:16px;align-items:stretch}
.chg-tool-btn{display:flex;align-items:center;justify-content:center;gap:6px;min-height:48px;padding:0 10px;border-radius:14px;background:var(--accent-soft);color:var(--accent-strong);font-size:13px;font-weight:700;text-decoration:none;border:none;font-family:inherit;cursor:pointer;min-width:0}
.chg-tool-btn:disabled{opacity:.5;cursor:not-allowed}
.chg-tool-btn span:last-child{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.chg-tool-btn svg{width:18px;height:18px;flex:none}
.chg-tool-icon{display:grid;place-items:center;width:48px;height:48px;border-radius:14px;background:var(--surface);border:1px solid var(--border);color:var(--text);cursor:pointer;font-family:inherit;box-shadow:var(--shadow-sm)}
.chg-tool-icon svg{width:20px;height:20px}
.auth-panel{margin-top:20px;padding:14px;border-radius:18px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-sm);display:flex;flex-direction:column;gap:12px}
.auth-banner{display:flex;gap:12px;align-items:flex-start;padding:12px;border-radius:14px;background:var(--accent-soft);border:1px solid color-mix(in srgb,var(--accent) 20%,var(--border))}
.auth-panel:has(.auth-banner.ok) .auth-actions-grid{display:none}
.auth-banner.ok{background:var(--green-soft);border-color:color-mix(in srgb,var(--green) 25%,var(--border))}
.auth-banner-body{flex:1;min-width:0}
.auth-banner-body #authBannerText{line-height:1.65}
.auth-banner-ic{width:40px;height:40px;border-radius:12px;background:var(--surface);display:grid;place-items:center;flex:none;color:var(--accent-strong)}
.auth-banner.ok .auth-banner-ic{color:var(--green)}
.auth-banner-ic svg{width:20px;height:20px}
.auth-actions-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.auth-act-btn{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;min-height:76px;padding:12px 8px;border-radius:14px;background:var(--surface-2);border:1px solid var(--border);text-decoration:none;color:var(--text);font-size:12.5px;font-weight:700;text-align:center;line-height:1.45}
.auth-act-ic{width:40px;height:40px;border-radius:12px;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center}
.auth-act-ic svg{width:20px;height:20px}
.pill-muted{background:var(--surface-3);color:var(--muted)}
.amen{display:inline-flex;align-items:center;gap:6px}
.amen svg{width:18px;height:18px;color:var(--accent)}
.startbar{position:absolute;bottom:0;left:0;right:0;background:color-mix(in srgb,var(--surface) 94%,transparent);backdrop-filter:blur(14px);border-top:1px solid var(--border);box-shadow:var(--shadow-up);display:flex;align-items:center;gap:12px;padding:14px 20px calc(18px + env(safe-area-inset-bottom,0));z-index:20}
.startbar .btn svg{width:18px;height:18px}
</style>
@endpush

@if($station ?? null)
@push('scripts')
@if($station)
<script>
(function(){
  var csrf=document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  function post(url){return fetch(url,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json();});}
  function toast(m){if(window.showToast){window.showToast(m);}else{var t=document.createElement('div');t.textContent=m;t.style.cssText='position:fixed;bottom:110px;left:50%;transform:translateX(-50%);background:#0f172a;color:#fff;padding:10px 16px;border-radius:12px;font-size:13px;z-index:99;max-width:86%;text-align:center';document.body.appendChild(t);setTimeout(function(){t.remove();},2600);}}
  var fav=document.getElementById('favBtn');
  fav&&fav.addEventListener('click',function(){post(@json(route('app.station.favorite', $station->code))).then(function(d){fav.classList.toggle('fav-on',d.favorite);fav.setAttribute('aria-pressed',d.favorite?'true':'false');toast(d.message);});});
  var wb=document.getElementById('watchBtn');
  wb&&wb.addEventListener('click',function(){wb.disabled=true;post(@json(route('app.station.watch', $station->code))).then(function(d){
    document.getElementById('watchCard').classList.toggle('on',d.watching);
    document.getElementById('watchText').textContent=d.watching?'به محض آزاد شدن به شما اعلان می‌دهیم.':'وقتی کانکتوری آزاد شد خبرتان کنیم؟';
    wb.textContent=d.watching?'لغو':'خبرم کن';wb.classList.toggle('btn-ghost',d.watching);toast(d.message);
  }).finally(function(){wb.disabled=false;});});
  var sheet=document.getElementById('reportSheet');
  document.getElementById('reportOpen').addEventListener('click',function(){sheet.hidden=false;});
  document.getElementById('reportClose').addEventListener('click',function(){sheet.hidden=true;});
  sheet.addEventListener('click',function(e){if(e.target===sheet)sheet.hidden=true;});
})();
</script>
@endif
<script>
(function(){
  var conns=@json($connectorsJs);
  var highlight=@json($highlightConnectorId);
  var qrToken=@json(request('qr'));
  var qrGrant=@json($qrGrant ?? null);
  var entryAuth=@json($entryAuth ?? null);
  var scanUrl=@json(route('app.scan'));
  var arrivalUrl=@json(route('app.arrival'));
  var chargingUrl=@json(route('app.charging'));
  var denyMsg='برای شروع شارژ، QR کانکتور را اسکن کنید، کارت RFID را بزنید، یا پلاک خودرو توسط دوربین ثبت شود.';
  var startForm=document.getElementById('startForm');
  var startBtn=document.getElementById('startBtn');
  var stateLabels={charging:['در حال شارژ','pill-orange'],offline:['آفلاین','pill-muted'],fault:['خطا','pill-red'],reserved:['رزرو','pill-amber'],inactive:['غیرفعال','pill-muted']};

  var allowedUuids=new Set();
  var edgeGrants={};
  var selIdx=-1;
  var edgeQrOk=false;
  var cloudQrOk=!!qrGrant;

  if(qrGrant&&qrGrant.connector_id){
    conns.forEach(function(c,i){if(c.db_id===qrGrant.connector_id)selIdx=i;});
  }else if(highlight){
    conns.forEach(function(c,i){if(c.db_id===highlight)selIdx=i;});
  }

  function grantLabel(type){
    return {qr:'اسکن QR',rfid:'کارت RFID',anpr:'تشخیص پلاک'}[type]||'احراز هویت';
  }

  function markAllowed(uuid,type){
    if(!uuid)return;
    allowedUuids.add(uuid);
    edgeGrants[uuid]=type||'unknown';
  }

  function isAllowed(uuid){
    return uuid&&allowedUuids.has(uuid);
  }

  function selectedUuid(){
    var sel=document.querySelector('.conn-card.sel');
    return sel&&sel.getAttribute('data-uuid');
  }

  function updateBanner(){
    var banner=document.getElementById('authBanner');
    var title=document.getElementById('authBannerTitle');
    var text=document.getElementById('authBannerText');
    var actions=document.getElementById('authActions');
    var uuid=selectedUuid();
    if(uuid&&isAllowed(uuid)){
      banner.classList.add('ok');
      title.textContent='مجوز شروع شارژ فعال است';
      text.textContent='روش احراز: '+grantLabel(edgeGrants[uuid]||(cloudQrOk?'qr':entryAuth||''));
      if(actions)actions.hidden=true;
      return;
    }
    banner.classList.remove('ok');
    title.textContent='برای شروع شارژ احراز هویت لازم است';
    text.textContent=denyMsg;
    if(actions)actions.hidden=false;
  }

  function updateStartBtn(){
    var uuid=selectedUuid();
    var ok=!!uuid&&isAllowed(uuid)&&document.querySelector('.conn-card.sel:not(.busy)');
    if(startBtn)startBtn.disabled=!ok;
    updateBanner();
  }

  function renderConnectors(){
    document.getElementById('connectors').innerHTML=conns.map(function(c,i){
      var busy=c.st==='busy';
      var allowed=isAllowed(c.uuid);
      var sel=i===selIdx;
      var locked=!allowed;
      return '<div class="conn-card '+(sel?'sel':'')+(busy?' busy':'')+(locked?' locked':'')+(allowed?' auth-ok':'')+'" data-idx="'+i+'" data-name="'+c.name+'" data-id="'+c.db_id+'" data-uuid="'+(c.uuid||'')+'">'+
        '<div class="ci '+c.ic+'">'+window.icon('plug')+'</div>'+
        '<div style="flex:1"><div class="fw7">کانکتور '+c.id+' · '+c.t+'</div>'+
        '<div class="fs12 muted mt4">حداکثر توان '+c.pw+(busy&&c.reason?' · '+c.reason:'')+'</div></div>'+
        (busy?'<span class="pill '+((stateLabels[c.state]||['مشغول','pill-orange'])[1])+'">'+((stateLabels[c.state]||['مشغول'])[0])+'</span>':(allowed?'<span class="pill pill-green">مجاز</span>':'<span class="pill pill-muted">قفل</span>'))+
      '</div>';
    }).join('');

    var selName=document.getElementById('selName');
    var hid=document.getElementById('connectorId');
    if(selIdx>=0&&conns[selIdx]){
      selName.textContent=conns[selIdx].name;
      if(hid)hid.value=conns[selIdx].db_id;
    }else{
      selName.textContent='ابتدا احراز هویت کنید';
      if(hid)hid.value='';
    }

    document.querySelectorAll('.conn-card').forEach(function(card){
      card.addEventListener('click',function(){
        if(card.classList.contains('busy')||card.classList.contains('locked'))return;
        var idx=parseInt(card.getAttribute('data-idx'),10);
        selIdx=idx;
        renderConnectors();
        updateStartBtn();
      });
    });
    updateStartBtn();
  }

  function loadEdgeGrants(){
    var path=(window.SC_API&&window.SC_API.authorizeStatusEdge)||'/api/v1/app/authorize/status';
    if(!window.scFetchAnyEdge)return Promise.resolve();
    return window.scFetchAnyEdge(path).then(function(hit){
      if(!hit||!hit.data||!hit.data.grants)return;
      hit.data.grants.forEach(function(g){
        if(g.connector_uuid)markAllowed(g.connector_uuid,g.grant_type);
        if(g.cloud_connector_uuid)markAllowed(g.cloud_connector_uuid,g.grant_type);
      });
      if(selIdx<0){
        conns.forEach(function(c,i){
          if(selIdx<0&&isAllowed(c.uuid))selIdx=i;
        });
      }
      renderConnectors();
    });
  }

  function authorizeEdgeQr(uuid){
    if(!qrToken||!uuid||!window.scPost)return Promise.resolve();
    return window.scEnsureBackend().then(function(){
      var backend=window.SC_ACTIVE_BACKEND;
      if(!backend||backend.kind!=='edge')return;
      return window.scPost('', (window.SC_API&&window.SC_API.authorizeEdge)||'/api/v1/app/authorize', {
        method:'qr', connector_uuid:uuid, qr_token:qrToken
      }).then(function(r){return r.json().then(function(d){
        if(!r.ok)throw new Error(d.message||'QR نامعتبر');
        edgeQrOk=true;
        markAllowed(uuid,'qr');
        renderConnectors();
      });});
    });
  }

  if(qrGrant&&qrGrant.connector_uuid){
    markAllowed(qrGrant.connector_uuid,'qr');
  }

  renderConnectors();
  loadEdgeGrants();

  if(qrToken&&selIdx>=0&&conns[selIdx]){
    authorizeEdgeQr(conns[selIdx].uuid).catch(function(err){
      console.warn('edge authorize',err.message);
    });
  }

  startForm.addEventListener('submit',function(e){
    e.preventDefault();
    var uuid=selectedUuid();
    if(!uuid||!isAllowed(uuid)){
      alert(denyMsg);
      return;
    }
    var backend=window.SC_ACTIVE_BACKEND;
    if(!backend||backend.kind!=='edge'||!window.scPost){
      startForm.submit();
      return;
    }
    startBtn.disabled=true;
    var chain=window.scEnsureBackend();
    if(qrToken&&!edgeQrOk){
      chain=chain.then(function(){return authorizeEdgeQr(uuid);});
    }
    chain.then(function(){
      return window.scPost(@json(route('app.charging.start')), (window.SC_API&&window.SC_API.chargingStartEdge)||'/api/v1/app/charging/start', {connector_uuid:uuid,start_soc:parseInt((document.getElementById('startSoc')||{}).value||'20',10)});
    }).then(function(r){return r.json().then(function(d){return {ok:r.ok,d:d};});})
      .then(function(res){
        if(!res.ok)throw new Error(res.d.message||denyMsg);
        window.location.href=chargingUrl+'?edge=1';
      })
      .catch(function(err){alert(err.message||'خطا');updateStartBtn();});
  });

  setInterval(loadEdgeGrants,15000);
})();
</script>
@endpush
@endif
