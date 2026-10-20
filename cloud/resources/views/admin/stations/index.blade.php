@extends('admin.layouts.app', ['page' => 'stations', 'title' => 'ایستگاه‌ها و نقشه', 'crumb' => 'مدیریت شبکه'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="flex items-center gap-12">
        <div class="seg" id="statusSeg">
          <button class="active">همه ({{ Persian::digits($stats['total']) }})</button>
          <button>آنلاین ({{ Persian::digits($stats['online']) }})</button>
          <button>خطا ({{ Persian::digits($stats['faulty']) }})</button>
        </div>
      </div>
      <div class="flex gap-8">
        <div class="search" style="max-width:200px"><input id="stationSearch" placeholder="جستجوی ایستگاه…" autocomplete="off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
        <a href="/admin/stations/create" class="btn btn-sm btn-primary"><span data-ic="plus" class="bic"></span> ایستگاه جدید</a>
      </div>
    </div>

    <!-- KPI کوچک -->
    <div class="kpi-grid" style="margin-bottom:16px">
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="station"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['total']) }}</div><div class="ms-l">ایستگاه فعال</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="plug"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['connectors']) }}</div><div class="ms-l">کانکتور نصب‌شده</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="activity"></div><div><div class="ms-v tnum">{{ Persian::digits($kpis['utilization_percent'] ?? 0) }}٪</div><div class="ms-l">میانگین بهره‌برداری</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="leaf"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['cities']) }} شهر</div><div class="ms-l">پوشش جغرافیایی</div></div></div>
    </div>

    <div class="grid col-2-1">
      <!-- نقشه -->
      <div class="card">
        <div class="card-head">
          <div><h3>نقشهٔ ایستگاه‌ها</h3><div class="ch-sub">کلیک روی نشانگر برای جزئیات ایستگاه</div></div>
          <div class="head-actions">
            <span class="map-leg"><i style="background:var(--green)"></i>آنلاین</span>
            <span class="map-leg"><i style="background:var(--amber)"></i>پرتراکم</span>
            <span class="map-leg"><i style="background:var(--red)"></i>خطا</span>
          </div>
        </div>
        <div class="card-pad">
          @include('admin.partials.leaflet-map', ['markers' => $mapMarkers, 'mapId' => 'stationsMap', 'height' => '520px'])
        </div>
      </div>

      <!-- لیست ایستگاه -->
      <div class="card" style="align-self:start">
        <div class="card-head"><h3>فهرست ایستگاه‌ها</h3><div class="head-actions"><span class="muted fs12">مرتب: بهره‌برداری</span></div></div>
        <div class="scroll-y" style="max-height:560px" id="stationList">
          @foreach($stationsJs as $i => $st)
          <div class="st-item {{ $i === 0 ? 'active' : '' }}" data-station="{{ $i }}" data-status="{{ $st['status'] }}" data-search="{{ $st['name'] }} {{ $st['city'] }} {{ $st['code'] }}">
            <div class="st-top">
              <span class="st-name">{{ $st['name'] }}</span>
              <span class="pill {{ $st['pill'][0] }}">@if($st['pill'][0]==='pill-green')<span class="pdot"></span>@endif{{ $st['pill'][1] }}</span>
            </div>
            <div class="st-meta">{{ $st['city'] }} · {{ Persian::digits($st['chargers']) }} کانکتور @if(!empty($st['anpr']['enabled'])) · <span class="anpr-badge">ANPR</span>@endif</div>
            <div class="st-bars"><div class="bar {{ $st['load']>85?'bar-orange':'' }}"><i style="width:{{ $st['load'] }}%"></i></div></div>
            <div class="st-foot"><span class="muted">بهره‌برداری {{ Persian::digits($st['load']) }}٪</span><span class="tnum t2">{{ Persian::digits($st['charging']) }} فعال / {{ Persian::digits($st['free']) }} آزاد</span></div>
          </div>
          @endforeach
        </div>
      </div>
    </div>

    <!-- جزئیات ایستگاه انتخاب‌شده -->
    <div class="card mt-16">
      <div class="card-head">
        <div><h3 id="detName">ایستگاه میدان آزادی</h3><div class="ch-sub" id="detAddr">تهران، میدان آزادی، ابتدای بزرگراه لشکری · کد ایستگاه ST‑۱۰۰۱</div></div>
        <div class="head-actions">
          <span class="pill pill-green"><span class="pdot"></span>آنلاین</span>
          <a href="/admin/remote" class="btn btn-sm">کنترل از راه دور</a>
          <button class="btn btn-sm btn-primary">گزارش ایستگاه</button>
        </div>
      </div>
      <div class="card-pad">
        <div class="grid g-3" style="margin-bottom:18px">
          <div class="det-stat"><div class="ds-l">انرژی امروز</div><div class="ds-v tnum" id="detEnergy">— <small>kWh</small></div></div>
          <div class="det-stat"><div class="ds-l">درآمد امروز</div><div class="ds-v tnum" id="detRevenue">— <small>م تومان</small></div></div>
          <div class="det-stat"><div class="ds-l">جلسات امروز</div><div class="ds-v tnum" id="detSessions">—</div></div>
        </div>

        <div class="section-title">وضعیت کانکتورها</div>
        <div class="grid g-3" id="connectors"></div>

        <div class="section-title mt-16" id="anprSectionTitle" hidden>پلاک‌خوان (ANPR)</div>
        <div id="anprSummary" class="anpr-station-box" hidden></div>
      </div>
    </div>
@endsection

@push('styles')
<style>
.bic, .ms-ic[data-ic] svg, .ds-v small { }
.bic { display:inline-grid; width:16px; height:16px; }
.bic svg { width:16px; height:16px; }
.mini-stat { background:var(--surface); border:1px solid var(--border); border-radius:var(--r-lg); padding:16px; display:flex; align-items:center; gap:13px; box-shadow:var(--shadow-sm); }
.ms-ic { width:44px; height:44px; border-radius:12px; display:grid; place-items:center; flex:none; }
.ms-ic svg { width:22px; height:22px; }
.ms-v { font-size:22px; font-weight:800; letter-spacing:-.02em; }
.ms-l { font-size:12px; color:var(--muted); }
.map-leg { display:inline-flex; align-items:center; gap:5px; font-size:11.5px; color:var(--text-2); font-weight:600; }
.map-leg i { width:9px; height:9px; border-radius:50%; }

.st-item { padding:14px 18px; border-bottom:1px solid var(--border-2); cursor:pointer; transition:background .12s; }
.st-item:hover { background:var(--surface-2); }
.st-item.active { background:var(--accent-softer); box-shadow: inset 3px 0 0 var(--accent); }
.st-top { display:flex; align-items:center; justify-content:space-between; }
.st-name { font-weight:700; font-size:14px; }
.st-meta { font-size:12px; color:var(--muted); margin:4px 0 9px; }
.st-foot { display:flex; justify-content:space-between; margin-top:7px; font-size:11.5px; }

.det-stat { background:var(--surface-2); border:1px solid var(--border-2); border-radius:var(--r); padding:14px 16px; }
.ds-l { font-size:12px; color:var(--muted); }
.ds-v { font-size:22px; font-weight:800; margin-top:3px; }
.ds-v small { font-size:13px; font-weight:600; color:var(--text-2); }

.conn { border:1px solid var(--border); border-radius:14px; padding:14px; }
.conn-top { display:flex; align-items:center; gap:11px; }
.conn-ic { width:40px; height:40px; border-radius:11px; display:grid; place-items:center; flex:none; }
.conn-ic svg { width:20px; height:20px; }
.conn-id { font-weight:700; font-size:13.5px; }
.conn-type { font-size:11.5px; color:var(--muted); }
.conn-pw { margin-inline-start:auto; text-align:end; }
.conn-pw b { font-size:15px; }
.anpr-badge{font-size:10px;font-weight:800;color:var(--green);background:var(--green-soft);padding:2px 7px;border-radius:8px}
.anpr-station-box{border:1px solid var(--border);border-radius:14px;padding:14px;background:var(--surface-2)}
.anpr-station-box .as-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;font-size:13px}
</style>
@endpush

@push('scripts')
<script>
window.__STATIONS_DATA = {!! json_encode($stationsJs, JSON_UNESCAPED_UNICODE) !!};
(function () {
  document.querySelectorAll('[data-ic]').forEach(function (el) { el.innerHTML = window.icon(el.getAttribute('data-ic')); });

  var stMap={busy:{c:'green',p:'pill-green',l:'در حال شارژ'},free:{c:'blue',p:'pill-accent',l:'آماده'},fault:{c:'red',p:'pill-red',l:'خطا'}};
  function renderConns(idx){
    var st=(window.__STATIONS_DATA||[])[idx]; if(!st)return;
    document.getElementById('connectors').innerHTML=(st.connectors||[]).map(function(c){
      var s=stMap[c.st]||stMap.free;
      return '<div class="conn"><div class="conn-top"><div class="conn-ic ic-'+s.c+'">'+window.icon('plug')+'</div><div><div class="conn-id">کانکتور '+c.id+'</div><div class="conn-type">'+c.type+'</div></div><div class="conn-pw"><b class="tnum">'+c.pw+'</b></div></div><div class="flex between items-center mt-12"><span class="pill '+s.p+'"><span class="pdot"></span>'+s.l+'</span><span class="muted fs12">'+(c.u||'—')+'</span></div></div>';
    }).join('');
    document.getElementById('detName').textContent='ایستگاه '+st.name;
    document.getElementById('detAddr').textContent=(st.city||'')+'، '+(st.address||'')+' · کد '+st.code;
    document.getElementById('detEnergy').innerHTML=(st.today_energy||'۰')+' <small>kWh</small>';
    document.getElementById('detRevenue').innerHTML=(st.today_revenue||'۰')+' <small>م تومان</small>';
    document.getElementById('detSessions').textContent=st.today_sessions||'۰';
    var remote=document.querySelector('.card-head .head-actions a[href="/admin/remote"]');
    if(remote&&st.id) remote.href='/admin/remote?station='+st.id;
    var anpr=st.anpr||{};
    var anprBox=document.getElementById('anprSummary');
    var anprTitle=document.getElementById('anprSectionTitle');
    if(anpr.enabled){
      anprTitle.hidden=false;anprBox.hidden=false;
      anprBox.innerHTML='<div class="as-row"><span class="muted">دوربین‌های فعال</span><b class="tnum">'+anpr.cameras+'</b></div>'+
        '<div class="as-row"><span class="muted">تشخیص امروز</span><b class="tnum">'+anpr.today+'</b></div>'+
        (anpr.last_plate?'<div class="as-row"><span class="muted">آخرین پلاک</span><b>'+anpr.last_plate+(anpr.last_time?' · '+anpr.last_time:'')+'</b></div>':'')+
        '<a href="'+anpr.anpr_url+'" class="btn btn-sm mt-12" style="width:100%">مدیریت پلاک‌خوان</a>';
    }else{
      anprTitle.hidden=true;anprBox.hidden=true;anprBox.innerHTML='';
    }
  }
  document.querySelectorAll('.st-item').forEach(function(it){
    it.addEventListener('click',function(){
      document.querySelectorAll('.st-item').forEach(function(x){x.classList.remove('active');});
      it.classList.add('active');
      renderConns(+it.getAttribute('data-station'));
    });
  });
  if(window.__STATIONS_DATA&&window.__STATIONS_DATA.length)renderConns(0);

  var statusKeys=['all','online','error'];
  function applyStationFilter(){
    var active=document.querySelector('#statusSeg button.active');
    var i=[].indexOf.call(document.querySelectorAll('#statusSeg button'),active);
    var f=statusKeys[i]||'all';
    var q=window.AdminUI?window.AdminUI.normalize(document.getElementById('stationSearch').value):'';
    document.querySelectorAll('.st-item').forEach(function(el){
      var st=el.getAttribute('data-status');
      var segOk=f==='all'||(f==='online'&&(st==='online'||st==='busy'))||(f==='error'&&st==='error');
      var text=window.AdminUI?window.AdminUI.normalize(el.getAttribute('data-search')||''):'';
      el.style.display=segOk&&(!q||text.indexOf(q)!==-1)?'':'none';
    });
  }
  document.querySelectorAll('#statusSeg button').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#statusSeg button').forEach(function(x){x.classList.remove('active');});
      b.classList.add('active');
      applyStationFilter();
    });
  });
  document.getElementById('stationSearch').addEventListener('input',applyStationFilter);
})();
</script>
@endpush
