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
        <a href="{{ route('app.reserve') }}" class="chg-tool-btn"><span data-ic="calendar"></span><span>رزرو</span></a>
        <button class="chg-tool-icon" type="button" aria-label="علاقه‌مندی"><span data-ic="star"></span></button>
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

    <div class="sec-title">تعرفه</div>
    <div class="card card-p">
        <div class="lrow" style="padding-top:0">
            <div class="l-ic ic-blue" data-ic="bolt"></div>
            <div class="l-main"><div class="l-title">هزینهٔ انرژی</div><div class="l-sub">شارژ سریع DC</div></div>
            <div class="l-end tnum">@if($tariffEnergy){{ Persian::money($tariffEnergy) }} <span class="fs12 muted">ت/kWh</span>@else—@endif</div>
        </div>
        <div class="lrow">
            <div class="l-ic ic-amber" data-ic="clock"></div>
            <div class="l-main"><div class="l-title">هزینهٔ اشغال</div><div class="l-sub">پس از پایان شارژ</div></div>
            <div class="l-end tnum">@if($tariffIdle){{ Persian::money($tariffIdle) }} <span class="fs12 muted">ت/دقیقه</span>@else—@endif</div>
        </div>
        <div class="lrow" style="padding-bottom:0">
            <div class="l-ic ic-green" data-ic="leaf"></div>
            <div class="l-main"><div class="l-title">ساعات کم‌باری</div><div class="l-sub">{{ $tariffOffpeakHours }}</div></div>
            <div class="l-end tnum" style="color:var(--green)">{{ Persian::money($tariffOffpeak) }} <span class="fs12 muted">ت/kWh</span></div>
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
</div>

<div class="startbar">
    <div>
        <div class="muted fs12">کانکتور انتخاب‌شده</div>
        <div class="fw8" id="selName">{{ $firstConn['name'] ?? '—' }}</div>
    </div>
    <form method="POST" action="{{ route('app.charging.start') }}" id="startForm" style="margin:0">
        @csrf
        <input type="hidden" name="connector_id" id="connectorId" value="{{ $firstConn['db_id'] ?? '' }}">
        <button type="submit" class="btn btn-orange" id="startBtn" style="width:auto;padding:0 24px" disabled><span data-ic="bolt"></span> شروع شارژ</button>
    </form>
</div>
@endif
@endsection

@push('styles')
<style>
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
    startBtn.disabled=!ok;
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
        '<div class="fs12 muted mt4">حداکثر توان '+c.pw+'</div></div>'+
        (busy?'<span class="pill pill-orange">مشغول</span>':(allowed?'<span class="pill pill-green">مجاز</span>':'<span class="pill pill-muted">قفل</span>'))+
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

  var startForm=document.getElementById('startForm');
  var startBtn=document.getElementById('startBtn');

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
      return window.scPost(@json(route('app.charging.start')), (window.SC_API&&window.SC_API.chargingStartEdge)||'/api/v1/app/charging/start', {connector_uuid:uuid});
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
