@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — رزرو شارژ')

@section('phone')
@php
    use App\Support\Persian;
    $defaultId = $defaultStation?->id;
    $reservationsJs = $reservations->map(fn ($r) => [
        'station_id' => $r->station_id,
        'from' => $r->reserved_from?->toIso8601String(),
        'until' => $r->reserved_until?->toIso8601String(),
    ])->values();
    $reserveConfig = [
        'stations' => $stationsPayload,
        'connectorOptions' => $connectorOptions,
        'reservations' => $reservationsJs,
        'defaultStationId' => $defaultId,
    ];
@endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.home') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">رزرو شارژ</div></div>
</div>

<div class="screen" style="padding-bottom:90px">
    @if($errors->any())
    <div class="card-lite" style="color:var(--red);margin-bottom:12px">{{ $errors->first() }}</div>
    @endif

    <button class="station-pick" id="stPick" type="button">
        <span class="sp-ic" data-ic="pin"></span>
        <span style="flex:1;text-align:start">
            <span class="fw7 fs14" id="stName">{{ $defaultStation?->name ?? 'انتخاب ایستگاه' }}</span>
            <span class="fs12 muted" style="display:block" id="stSub">{{ $defaultStation?->city ?? '' }}</span>
        </span>
        <span class="chev" data-ic="chevron"></span>
    </button>

    <div class="avail-row">
        <div class="av-box"><div class="av-n tnum" id="statFree" style="color:var(--green)">{{ Persian::digits($stationStats['free'] ?? 0) }}</div><div class="av-l">آزاد اکنون</div></div>
        <div class="av-box"><div class="av-n tnum" id="statBusy" style="color:var(--amber)">{{ Persian::digits($stationStats['busy'] ?? 0) }}</div><div class="av-l">مشغول</div></div>
        <div class="av-box"><div class="av-n tnum" id="statSoon" style="color:var(--accent-strong)">{{ Persian::digits($stationStats['soon'] ?? 0) }}</div><div class="av-l">خارج از دسترس</div></div>
    </div>

    <div class="sec-title">وضعیت کانکتورها <span class="live-dot"></span><span class="fs12 muted" style="font-weight:400">زنده</span></div>
    <div style="display:flex;flex-direction:column;gap:10px" id="connList"></div>

    <div class="sec-title mt20">انتخاب زمان رزرو</div>
    <div class="day-pick" id="dayPick"></div>
    <div class="slot-grid" id="slots"></div>
</div>

<form method="POST" action="{{ route('app.reserve.store') }}" id="reserveForm">@csrf
    <input type="hidden" name="station_id" id="stationId" value="{{ $defaultId }}">
    <input type="hidden" name="connector_id" id="connectorId" value="">
    <input type="hidden" name="reserved_from" id="reservedFrom" value="">
    <input type="hidden" name="reserved_until" id="reservedUntil" value="">
    <div class="savebar">
        <div class="sb-info"><span class="fs12 muted">زمان انتخابی</span><span class="fw8" id="selLabel">—</span></div>
        <button class="btn btn-orange" id="reserveBtn" type="submit" style="flex:1;height:50px" disabled><span data-ic="calendar"></span> ثبت رزرو</button>
    </div>
</form>
@endsection

@push('overlays')
<div class="done-ov {{ session('success') ? 'show' : '' }}" id="doneOv">
    <div class="ok-circle"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></div>
    <h2 class="mt20" style="font-size:20px">رزرو شما ثبت شد</h2>
    <p class="muted" style="margin-top:8px;font-size:13px;line-height:1.9" id="doneText">{{ session('success') }}</p>
    <div class="res-note"><span data-ic="clock"></span><span>کانکتور تا ۱۵ دقیقه پس از زمان رزرو برای شما نگه داشته می‌شود.</span></div>
    <a href="{{ route('app.home') }}" class="btn btn-orange mt20" style="height:48px;width:100%;max-width:280px">بازگشت به خانه</a>
</div>

<div class="pick-ov" id="stationOv">
    <div class="pick-sheet">
        <div class="fw8 mb12">انتخاب ایستگاه</div>
        <div id="stationList" style="display:flex;flex-direction:column;gap:8px;max-height:50vh;overflow:auto"></div>
        <button class="btn btn-ghost mt12" type="button" id="closeStationOv" style="width:100%">بستن</button>
    </div>
</div>
@endpush

@push('styles')
<style>
.station-pick{display:flex;align-items:center;gap:12px;width:100%;padding:14px;border:1.5px solid var(--border);border-radius:16px;background:var(--surface);font-family:inherit;box-shadow:var(--shadow-sm)}
.sp-ic{width:42px;height:42px;border-radius:12px;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;flex:none}.sp-ic svg{width:21px;height:21px}
.station-pick .chev svg{width:18px;height:18px;color:var(--muted)}
.avail-row{display:flex;gap:10px;margin-top:14px}
.av-box{flex:1;text-align:center;background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:12px 6px;box-shadow:var(--shadow-sm)}
.av-n{font-size:22px;font-weight:800}.av-l{font-size:11px;color:var(--muted);margin-top:2px}
.live-dot{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--green);margin:0 6px 0 3px;animation:lpulse 1.6s infinite}
@keyframes lpulse{0%,100%{opacity:1}50%{opacity:.3}}
.conn{display:flex;align-items:center;gap:12px;padding:13px 14px;border:1.5px solid var(--border);border-radius:15px;background:var(--surface)}
.conn.free{border-color:color-mix(in srgb,var(--green) 40%,var(--border))}
.conn.sel{border-color:var(--accent);background:var(--accent-soft)}
.conn .cn-ic{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;flex:none}.cn-ic svg{width:21px;height:21px}
.conn .cn-main{flex:1;min-width:0}
.conn .cn-t{font-weight:700;font-size:13.5px}
.conn .cn-s{font-size:11.5px;color:var(--muted);margin-top:2px}
.conn .cn-prog{height:5px;border-radius:5px;background:var(--surface-3);overflow:hidden;margin-top:7px}
.conn .cn-prog i{display:block;height:100%;border-radius:5px;background:linear-gradient(90deg,var(--amber),#fbbf24)}
.cn-eta{text-align:center;flex:none}
.cn-eta .e-v{font-weight:800;font-size:14px}.cn-eta .e-l{font-size:10px;color:var(--muted)}
.cn-free-tag{flex:none;font-size:11.5px;font-weight:700;color:var(--green);background:var(--green-soft);padding:5px 11px;border-radius:20px}
.day-pick{display:flex;gap:9px;margin-bottom:14px}
.day{flex:1;display:flex;flex-direction:column;gap:3px;padding:11px;border:1.5px solid var(--border);border-radius:13px;background:var(--surface);font-family:inherit;cursor:pointer}
.day.active{border-color:var(--accent);background:var(--accent-soft)}
.d-w{font-weight:700;font-size:13px;color:var(--text)}.d-n{font-size:11px;color:var(--muted)}
.slot-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:9px}
.slot{padding:11px 4px;border:1.5px solid var(--border);border-radius:11px;background:var(--surface);font-family:inherit;font-weight:700;font-size:13px;color:var(--text);font-variant-numeric:tabular-nums;cursor:pointer}
.slot.taken{opacity:.4;text-decoration:line-through;pointer-events:none}
.slot.sel{border-color:var(--accent);background:var(--accent);color:#fff}
.savebar{position:absolute;bottom:0;left:0;right:0;display:flex;align-items:center;gap:12px;background:color-mix(in srgb,var(--surface) 94%,transparent);backdrop-filter:blur(14px);border-top:1px solid var(--border);padding:12px 18px calc(16px + env(safe-area-inset-bottom,0));z-index:20}
.sb-info{display:flex;flex-direction:column;flex:none}
.savebar .btn svg{width:18px;height:18px}
.savebar .btn:disabled{opacity:.5}
.done-ov{position:absolute;inset:0;background:var(--bg);display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:34px;opacity:0;visibility:hidden;transition:opacity .25s;z-index:80}
.done-ov.show{opacity:1;visibility:visible}
.ok-circle{width:92px;height:92px;border-radius:50%;background:linear-gradient(140deg,var(--green),#4ade80);display:grid;place-items:center;box-shadow:0 16px 34px rgba(22,163,74,.4)}.ok-circle svg{width:44px;height:44px}
.res-note{display:flex;align-items:flex-start;gap:9px;margin-top:20px;padding:13px;border-radius:14px;background:var(--surface-2);border:1px solid var(--border-2);font-size:12px;color:var(--text-2);line-height:1.7;max-width:300px;text-align:start}
.res-note svg{width:18px;height:18px;color:var(--accent);flex:none;margin-top:1px}
.pick-ov{position:absolute;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:flex-end;opacity:0;visibility:hidden;transition:opacity .2s;z-index:70}
.pick-ov.show{opacity:1;visibility:visible}
.pick-sheet{width:100%;background:var(--surface);border-radius:20px 20px 0 0;padding:18px 18px calc(20px + env(safe-area-inset-bottom,0))}
.pick-item{display:flex;align-items:center;gap:10px;width:100%;padding:12px;border:1px solid var(--border);border-radius:12px;background:var(--surface-2);font-family:inherit;text-align:start;cursor:pointer}
</style>
@endpush

@push('scripts')
<script>
window.__RESERVE = @json($reserveConfig);
(function(){
  var cfg=window.__RESERVE||{};
  var stations=cfg.stations||[];
  var connectorOptions=cfg.connectorOptions||{};
  var reservations=cfg.reservations||[];
  var stationId=cfg.defaultStationId||stations[0]?.id;
  var selDay=0,selSlot=null,selConnectorId=null;
  var SLOT_MIN=30;

  function fa(n){return String(n).replace(/[0-9]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d];});}
  function faTime(h,m){return fa(String(h).padStart(2,'0'))+':'+fa(String(m).padStart(2,'0'));}
  function fmtLocal(dt){
    var p=function(n){return String(n).padStart(2,'0');};
    return dt.getFullYear()+'-'+p(dt.getMonth()+1)+'-'+p(dt.getDate())+' '+p(dt.getHours())+':'+p(dt.getMinutes())+':00';
  }
  function station(){return stations.find(function(s){return s.id===stationId;})||stations[0];}

  function buildDays(){
    var days=['یکشنبه','دوشنبه','سه‌شنبه','چهارشنبه','پنجشنبه','جمعه','شنبه'];
    var labels=['امروز','فردا','پس‌فردا'];
  var html='';
    for(var d=0;d<3;d++){
      var dt=new Date(); dt.setDate(dt.getDate()+d);
      var w=d===0?'امروز':d===1?'فردا':days[dt.getDay()];
      var dateLabel=fa(dt.getDate())+' '+dt.toLocaleDateString('fa-IR',{month:'long'});
      html+='<button type="button" class="day'+(d===selDay?' active':'')+'" data-d="'+d+'"><span class="d-w">'+w+'</span><span class="d-n tnum">'+dateLabel+'</span></button>';
    }
    document.getElementById('dayPick').innerHTML=html;
    document.querySelectorAll('#dayPick .day').forEach(function(b){
      b.addEventListener('click',function(){
        document.querySelectorAll('#dayPick .day').forEach(function(x){x.classList.remove('active');});
        b.classList.add('active'); selDay=+b.getAttribute('data-d'); selSlot=null; renderSlots(); updateBar();
      });
    });
  }

  function slotDateTime(dayOffset,hour,minute){
    var dt=new Date(); dt.setHours(0,0,0,0); dt.setDate(dt.getDate()+dayOffset);
    dt.setHours(hour,minute,0,0); return dt;
  }

  function isTaken(dt){
    var until=new Date(dt.getTime()+SLOT_MIN*60000);
    return reservations.some(function(r){
      if(r.station_id!==stationId)return false;
      var from=new Date(r.from), to=new Date(r.until);
      return dt<to&&until>from;
    });
  }

  function renderSlots(){
    var slots=[];
    for(var h=8;h<20;h++)for(var m of [0,30])slots.push({h:h,m:m});
    var html='';
    slots.forEach(function(s,i){
      var dt=slotDateTime(selDay,s.h,s.m);
      var taken=dt<=new Date()||isTaken(dt);
      var label=faTime(s.h,s.m);
      html+='<button type="button" class="slot'+(taken?' taken':'')+(selSlot===i?' sel':'')+'" data-i="'+i+'" data-h="'+s.h+'" data-m="'+s.m+'"'+(taken?' disabled':'')+'>'+label+'</button>';
    });
    document.getElementById('slots').innerHTML=html;
    document.querySelectorAll('#slots .slot').forEach(function(b){
      b.addEventListener('click',function(){
        if(b.classList.contains('taken'))return;
        selSlot=+b.getAttribute('data-i'); renderSlots(); updateBar();
      });
    });
  }

  function renderConnectors(){
    var st=station();
    var rows=st&&st.connectors&&st.connectors.length?st.connectors:[];
    if(!rows.length){
      var opts=(connectorOptions[stationId]||[]).map(function(o){
        return {id:o.label,db_id:o.id,free:o.reservable,t:'',charging:false};
      });
      rows=opts;
    }
    document.getElementById('connList').innerHTML=rows.map(function(c){
      var sel=selConnectorId===c.db_id?' sel':'';
      if(c.free){
        return '<div class="conn free'+sel+'" data-id="'+c.db_id+'"><div class="cn-ic ic-green">'+window.icon('plug')+'</div>'+
          '<div class="cn-main"><div class="cn-t">کانکتور '+c.id+'</div><div class="cn-s">'+(c.t||'آمادهٔ رزرو')+'</div></div>'+
          '<span class="cn-free-tag">آزاد · آمادهٔ شارژ</span></div>';
      }
      if(!c.charging){
        var lbl={offline:'آفلاین',fault:'خارج از سرویس',reserved:'رزرو شده',inactive:'غیرفعال'}[c.state]||'در دسترس نیست';
        return '<div class="conn'+sel+'" data-id="'+c.db_id+'"><div class="cn-ic ic-amber">'+window.icon('plug')+'</div>'+
          '<div class="cn-main"><div class="cn-t">کانکتور '+c.id+'</div><div class="cn-s">'+(c.reason||lbl)+'</div></div>'+
          '<span class="pill pill-muted">'+lbl+'</span></div>';
      }
      return '<div class="conn'+sel+'" data-id="'+c.db_id+'"><div class="cn-ic ic-orange">'+window.icon('plug')+'</div>'+
        '<div class="cn-main"><div class="cn-t">کانکتور '+c.id+'</div><div class="cn-s">خودروی فعلی: '+fa(c.soc||0)+'٪ شارژ شده</div>'+
        '<div class="cn-prog"><i style="width:'+(c.soc||0)+'%"></i></div></div>'+
        '<div class="cn-eta"><div class="e-v">'+(c.eta?'~'+fa(c.eta)+' دق':'—')+'</div><div class="e-l">تا آزاد شدن</div></div></div>';
    }).join('');
    document.querySelectorAll('#connList .conn.free').forEach(function(el){
      el.style.cursor='pointer';
      el.addEventListener('click',function(){
        document.querySelectorAll('#connList .conn').forEach(function(x){x.classList.remove('sel');});
        el.classList.add('sel'); selConnectorId=+el.getAttribute('data-id'); updateBar();
      });
    });
    if(!selConnectorId){
      var first=document.querySelector('#connList .conn.free');
      if(first){first.classList.add('sel'); selConnectorId=+first.getAttribute('data-id');}
    }
  }

  function updateStationUi(){
    var st=station();
    if(!st)return;
    document.getElementById('stName').textContent=st.name;
    document.getElementById('stSub').textContent=st.city||'';
    document.getElementById('stationId').value=st.id;
    document.getElementById('statFree').textContent=fa(st.stats.free||0);
    document.getElementById('statBusy').textContent=fa(st.stats.busy||0);
    document.getElementById('statSoon').textContent=fa(st.stats.soon||0);
    selConnectorId=null; renderConnectors(); renderSlots(); updateBar();
  }

  function updateBar(){
    var btn=document.getElementById('reserveBtn');
    document.getElementById('connectorId').value=selConnectorId||'';
    if(selSlot!=null){
      var slotBtn=document.querySelector('#slots .slot.sel');
      var h=+slotBtn.getAttribute('data-h'), m=+slotBtn.getAttribute('data-m');
      var from=slotDateTime(selDay,h,m);
      var until=new Date(from.getTime()+SLOT_MIN*60000);
      document.getElementById('reservedFrom').value=fmtLocal(from);
      document.getElementById('reservedUntil').value=fmtLocal(until);
      var dayTxt=document.querySelector('#dayPick .day.active .d-w').textContent;
      document.getElementById('selLabel').textContent=dayTxt+' ساعت '+faTime(h,m);
      btn.disabled=!selConnectorId;
    }else{
      document.getElementById('selLabel').textContent='—';
      document.getElementById('reservedFrom').value='';
      document.getElementById('reservedUntil').value='';
      btn.disabled=true;
    }
  }

  document.getElementById('stationList').innerHTML=stations.map(function(s){
    return '<button type="button" class="pick-item" data-id="'+s.id+'"><span class="fw7">'+s.name+'</span><span class="muted fs12">'+s.city+'</span></button>';
  }).join('');
  document.querySelectorAll('#stationList .pick-item').forEach(function(b){
    b.addEventListener('click',function(){
      stationId=+b.getAttribute('data-id'); updateStationUi();
      document.getElementById('stationOv').classList.remove('show');
    });
  });
  document.getElementById('stPick').addEventListener('click',function(){document.getElementById('stationOv').classList.add('show');});
  document.getElementById('closeStationOv').addEventListener('click',function(){document.getElementById('stationOv').classList.remove('show');});

  buildDays(); updateStationUi();
})();
</script>
@endpush
