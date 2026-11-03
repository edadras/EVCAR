@extends('admin.layouts.app', ['page' => 'stations', 'title' => 'ایستگاه‌ها و نقشه', 'crumb' => 'مدیریت شبکه'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="flex items-center gap-12">
        <div class="seg" id="statusSeg">
          <button class="active">همه ({{ Persian::digits($stats['total']) }})</button>
          <button>آنلاین ({{ Persian::digits($stats['online']) }})</button>
          <button>خطا / آفلاین ({{ Persian::digits($stats['faulty']) }})</button>
        </div>
      </div>
      <div class="flex gap-8">
        <div class="search" style="max-width:200px"><input id="stationSearch" placeholder="جستجوی ایستگاه…" autocomplete="off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
        <a href="/admin/stations/create" class="btn btn-sm btn-primary"><span data-ic="plus" class="bic"></span> ایستگاه جدید</a>
      </div>
    </div>

    <!-- KPI کوچک -->
    <div class="kpi-grid" style="margin-bottom:16px">
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="station"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['total']) }}</div><div class="ms-l">ایستگاه ثبت‌شده</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="plug"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['connectors']) }}</div><div class="ms-l">کانکتور نصب‌شده</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="activity"></div><div><div class="ms-v tnum"><span id="utilPct">{{ Persian::digits($kpis['utilization_percent'] ?? 0) }}</span>٪</div><div class="ms-l">بهره‌برداری لحظه‌ای کانکتورها</div></div></div>
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
        <div class="card-head"><h3>فهرست ایستگاه‌ها</h3><div class="head-actions"><span class="muted fs12">به‌روزرسانی: <b class="tnum" data-live-stamp>—</b></span></div></div>
        <div class="scroll-y" style="max-height:560px" id="stationList"></div>
      </div>
    </div>

    <!-- جزئیات ایستگاه انتخاب‌شده -->
    <div class="card mt-16">
      <div class="card-head">
        <div><h3 id="detName">—</h3><div class="ch-sub" id="detAddr">—</div></div>
        <div class="head-actions">
          <span class="pill" id="detPill">—</span>
          <a href="/admin/remote" class="btn btn-sm" id="detRemote">کنترل از راه دور</a>
          <a href="#" class="btn btn-sm btn-primary" id="detShow">جزئیات ایستگاه</a>
        </div>
      </div>
      <div class="card-pad">
        <div class="grid g-3" style="margin-bottom:18px">
          <div class="det-stat"><div class="ds-l">انرژی امروز</div><div class="ds-v tnum" id="detEnergy">— <small>kWh</small></div></div>
          <div class="det-stat"><div class="ds-l">درآمد امروز</div><div class="ds-v tnum" id="detRevenue">— <small>تومان</small></div></div>
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
window.__STATIONS_DATA = @json($stationsJs);
(function () {
  var UI = window.AdminUI, fa = UI.fa, esc = UI.esc;
  document.querySelectorAll('[data-ic]').forEach(function (el) { el.innerHTML = window.icon(el.getAttribute('data-ic')); });

  var DATA = [], selectedId = null;
  var stMap = { busy: { c: 'green', p: 'pill-green', l: 'در حال استفاده' }, free: { c: 'blue', p: 'pill-accent', l: 'آماده' }, fault: { c: 'red', p: 'pill-red', l: 'خطا' }, offline: { c: 'gray', p: 'pill', l: 'آفلاین' } };

  function renderList() {
    document.getElementById('stationList').innerHTML = DATA.map(function (st) {
      var p = st.pill || ['pill', '—'];
      return '<div class="st-item' + (st.id === selectedId ? ' active' : '') + '" data-id="' + st.id + '" data-status="' + st.status + '" data-search="' + esc(st.name + ' ' + (st.city || '') + ' ' + (st.code || '')) + '">' +
        '<div class="st-top"><span class="st-name">' + esc(st.name) + '</span><span class="pill ' + p[0] + '">' + (p[0] === 'pill-green' ? '<span class="pdot"></span>' : '') + esc(p[1]) + '</span></div>' +
        '<div class="st-meta">' + esc(st.city || '') + ' · ' + fa((st.connectors || []).length, 0) + ' کانکتور' + (st.anpr && st.anpr.enabled ? ' · <span class="anpr-badge">ANPR</span>' : '') + '</div>' +
        '<div class="st-bars"><div class="bar ' + (st.load > 85 ? 'bar-orange' : '') + '"><i style="width:' + st.load + '%"></i></div></div>' +
        '<div class="st-foot"><span class="muted">بهره‌برداری ' + fa(st.load, 0) + '٪</span><span class="tnum t2">' + fa(st.charging, 0) + ' فعال / ' + fa(st.free, 0) + ' آزاد</span></div></div>';
    }).join('') || '<div class="muted" style="padding:20px;text-align:center">ایستگاهی ثبت نشده</div>';
    applyStationFilter();
  }

  function renderDetail() {
    var st = DATA.filter(function (s) { return s.id === selectedId; })[0]; if (!st) return;
    document.getElementById('connectors').innerHTML = (st.connectors || []).map(function (c) {
      var s = stMap[c.st] || stMap.free;
      return '<div class="conn"><div class="conn-top"><div class="conn-ic ic-' + s.c + '">' + window.icon('plug') + '</div><div><div class="conn-id">' + esc(c.id) + '</div><div class="conn-type">' + esc(c.type) + '</div></div><div class="conn-pw"><b class="tnum">' + esc(c.pw) + '</b></div></div><div class="flex between items-center mt-12"><span class="pill ' + s.p + '"><span class="pdot"></span>' + s.l + '</span><span class="muted fs12">' + esc(c.u || '—') + '</span></div></div>';
    }).join('') || '<div class="muted">کانکتوری ثبت نشده</div>';
    var pill = document.getElementById('detPill'), sp = st.pill || ['pill', '—'];
    pill.className = 'pill ' + sp[0];
    pill.innerHTML = (sp[0] === 'pill-green' ? '<span class="pdot"></span>' : '') + esc(sp[1]);
    document.getElementById('detName').textContent = st.name;
    document.getElementById('detAddr').textContent = [st.city, st.address].filter(Boolean).join('، ') + ' · کد ' + (st.code || '—');
    document.getElementById('detEnergy').innerHTML = esc(st.today_energy || '۰') + ' <small>kWh</small>';
    document.getElementById('detRevenue').innerHTML = esc(st.today_revenue || '۰') + ' <small>تومان</small>';
    document.getElementById('detSessions').textContent = st.today_sessions || '۰';
    document.getElementById('detRemote').href = st.remoteUrl || '/admin/remote';
    document.getElementById('detShow').href = UI.apiBase() + '/stations/' + st.id;
    var anpr = st.anpr || {}, anprBox = document.getElementById('anprSummary'), anprTitle = document.getElementById('anprSectionTitle');
    if (anpr.enabled) {
      anprTitle.hidden = false; anprBox.hidden = false;
      anprBox.innerHTML = '<div class="as-row"><span class="muted">دوربین‌های فعال</span><b class="tnum">' + esc(anpr.cameras) + '</b></div>' +
        '<div class="as-row"><span class="muted">تشخیص امروز</span><b class="tnum">' + esc(anpr.today) + '</b></div>' +
        (anpr.last_plate ? '<div class="as-row"><span class="muted">آخرین پلاک</span><b>' + esc(anpr.last_plate) + (anpr.last_time ? ' · ' + esc(anpr.last_time) : '') + '</b></div>' : '') +
        '<a href="' + esc(anpr.anpr_url) + '" class="btn btn-sm mt-12" style="width:100%">مدیریت پلاک‌خوان</a>';
    } else { anprTitle.hidden = true; anprBox.hidden = true; anprBox.innerHTML = ''; }
  }

  document.getElementById('stationList').addEventListener('click', function (e) {
    var it = e.target.closest('.st-item'); if (!it) return;
    selectedId = +it.getAttribute('data-id');
    document.querySelectorAll('.st-item').forEach(function (x) { x.classList.toggle('active', x === it); });
    renderDetail();
  });

  var statusKeys = ['all', 'online', 'error'];
  function applyStationFilter() {
    var i = [].indexOf.call(document.querySelectorAll('#statusSeg button'), document.querySelector('#statusSeg button.active'));
    var f = statusKeys[i] || 'all', q = UI.normalize(document.getElementById('stationSearch').value);
    document.querySelectorAll('.st-item').forEach(function (el) {
      var st = el.getAttribute('data-status');
      var segOk = f === 'all' || (f === 'online' && (st === 'online' || st === 'busy')) || (f === 'error' && (st === 'error' || st === 'offline'));
      el.style.display = segOk && (!q || UI.normalize(el.getAttribute('data-search') || '').indexOf(q) !== -1) ? '' : 'none';
    });
  }
  document.querySelectorAll('#statusSeg button').forEach(function (b) {
    b.addEventListener('click', function () {
      document.querySelectorAll('#statusSeg button').forEach(function (x) { x.classList.remove('active'); });
      b.classList.add('active'); applyStationFilter();
    });
  });
  document.getElementById('stationSearch').addEventListener('input', applyStationFilter);

  function apply(stations, util) {
    DATA = stations || [];
    if (selectedId === null || !DATA.some(function (s) { return s.id === selectedId; })) selectedId = DATA.length ? DATA[0].id : null;
    renderList(); renderDetail();
    if (util != null) document.getElementById('utilPct').textContent = fa(util);
  }
  apply(window.__STATIONS_DATA, null);
  UI.live('stations', 15000, function (d) { apply(d.stations, d.utilization); });
})();
</script>
@endpush
