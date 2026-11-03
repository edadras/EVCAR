@extends('admin.layouts.app', ['page' => 'dashboard', 'title' => 'داشبورد و مانیتورینگ', 'crumb' => 'مدیریت شبکه'])

@section('content')
@php use App\Support\Persian; @endphp

<!-- نوار اقدام -->
    <div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="flex items-center gap-12">
        <span class="pill" id="sysPill"><span class="pdot"></span><span id="sysPillText">—</span></span>
        <span class="muted fs12">آخرین به‌روزرسانی: <b class="tnum" data-live-stamp>—</b></span>
      </div>
      <div class="flex gap-8">
        <button class="btn btn-sm" id="refreshBtn"><span class="ic">↻</span> به‌روزرسانی</button>
        <a class="btn btn-sm btn-primary" href="{{ route('admin.insights') }}">خروجی گزارش</a>
      </div>
    </div>

    <!-- KPI -->
    <div class="kpi-grid">
      <div class="kpi rise" style="animation-delay:.02s">
        <div class="kpi-ic ic-blue" data-ic="bolt"></div>
        <div class="kpi-label">شارژرهای آنلاین</div>
        <div class="kpi-val tnum"><span id="kChOnline">—</span> <small>/ <span id="kChTotal">—</span></small></div>
        <div class="kpi-foot"><span class="muted"><span id="kUptime">—</span>٪ در دسترس</span></div>
      </div>
      <div class="kpi rise" style="animation-delay:.06s">
        <div class="kpi-ic ic-green" data-ic="leaf"></div>
        <div class="kpi-label">انرژی تحویلی امروز</div>
        <div class="kpi-val tnum"><span id="kEnergy">—</span> <small>kWh</small></div>
        <div class="kpi-foot"><span id="kEnergyTrend"></span><span class="muted">نسبت به دیروز</span></div>
        <svg class="kpi-spark" id="sparkEnergy" width="92" height="40" viewBox="0 0 92 40"></svg>
      </div>
      <div class="kpi rise" style="animation-delay:.1s">
        <div class="kpi-ic ic-orange" data-ic="coins"></div>
        <div class="kpi-label">درآمد امروز</div>
        <div class="kpi-val tnum"><span id="kRevenue">—</span> <small>تومان</small></div>
        <div class="kpi-foot"><span id="kRevenueTrend"></span><span class="muted">نسبت به دیروز</span></div>
        <svg class="kpi-spark" id="sparkRevenue" width="92" height="40" viewBox="0 0 92 40"></svg>
      </div>
      <div class="kpi rise" style="animation-delay:.14s">
        <div class="kpi-ic ic-violet" data-ic="activity"></div>
        <div class="kpi-label">جلسات فعال</div>
        <div class="kpi-val tnum"><span id="kActive">—</span></div>
        <div class="kpi-foot"><span class="muted">توان لحظه‌ای <span id="kLoad">—</span> kW · بهره‌برداری <span id="kUtil">—</span>٪</span></div>
      </div>
    </div>

    <!-- نمودار + بهره‌برداری -->
    <div class="grid col-2-1 mt-16">
      <div class="card">
        <div class="card-head">
          <div>
            <h3>روند انرژی و درآمد</h3>
            <div class="ch-sub">۲۴ ساعت گذشته · به تفکیک ساعت (وقت تهران)</div>
          </div>
          <div class="head-actions">
            <span class="legend-inline"><i style="background:var(--accent)"></i> انرژی (kWh) · بیشینه <b class="tnum" id="maxE">—</b></span>
            <span class="legend-inline"><i style="background:var(--orange)"></i> درآمد (تومان) · بیشینه <b class="tnum" id="maxR">—</b></span>
          </div>
        </div>
        <div class="card-pad">
          <svg id="mainChart" width="100%" height="240" viewBox="0 0 720 240" preserveAspectRatio="none"></svg>
          <div class="chart-axis" id="chartAxis"></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h3>وضعیت کانکتورها</h3></div>
        <div class="card-pad">
          <div class="flex items-center gap-16">
            <svg width="132" height="132" viewBox="0 0 132 132" id="donut"></svg>
            <div class="legend">
              <div class="li"><span class="sw" style="background:var(--green)"></span><span>در حال شارژ</span><b class="tnum" style="margin-inline-start:auto" id="bdCharging">—</b></div>
              <div class="li"><span class="sw" style="background:var(--accent)"></span><span>آماده</span><b class="tnum" style="margin-inline-start:auto" id="bdAvailable">—</b></div>
              <div class="li"><span class="sw" style="background:var(--amber)"></span><span>رزرو / پایان شارژ</span><b class="tnum" style="margin-inline-start:auto" id="bdReserved">—</b></div>
              <div class="li"><span class="sw" style="background:var(--red)"></span><span>خطا</span><b class="tnum" style="margin-inline-start:auto" id="bdFaulted">—</b></div>
              <div class="li"><span class="sw" style="background:var(--muted)"></span><span>آفلاین</span><b class="tnum" style="margin-inline-start:auto" id="bdOffline">—</b></div>
            </div>
          </div>
          <hr class="sep">
          <div class="lrow">
            <div class="l-ic ic-blue" data-ic="plug"></div>
            <div class="l-main"><div class="l-title">کانکتورهای DC</div><div class="l-sub">میانگین توان نامی <span class="tnum" id="dcKw">—</span> کیلووات</div></div>
            <div class="l-end"><b class="tnum" id="dcCount">—</b></div>
          </div>
          <div class="lrow">
            <div class="l-ic ic-green" data-ic="plug"></div>
            <div class="l-main"><div class="l-title">کانکتورهای AC</div><div class="l-sub">میانگین توان نامی <span class="tnum" id="acKw">—</span> کیلووات</div></div>
            <div class="l-end"><b class="tnum" id="acCount">—</b></div>
          </div>
        </div>
      </div>
    </div>

    <!-- نقشه + هشدارها -->
    <div class="grid col-2-1 mt-16">
      <div class="card">
        <div class="card-head">
          <div><h3>نقشهٔ شبکه</h3><div class="ch-sub"><span id="kCities">{{ Persian::digits($kpis['cities_count'] ?? 0) }}</span> شهر · <span id="kStations">{{ Persian::digits($kpis['stations_count'] ?? 0) }}</span> ایستگاه فعال</div></div>
          <div class="head-actions"><a href="{{ route('admin.stations.index') }}" class="btn btn-sm">نمای کامل نقشه</a></div>
        </div>
        <div class="card-pad">
          @include('admin.partials.leaflet-map', ['markers' => $mapMarkers, 'mapId' => 'dashMap', 'height' => '320px'])
        </div>
      </div>

      <div class="card">
        <div class="card-head">
          <div><h3>هشدارها و خطاها</h3></div>
          <div class="head-actions"><a href="{{ route('admin.alerts.index') }}" class="btn btn-sm">همه</a></div>
        </div>
        <div class="card-pad" style="padding-top:6px" id="alertsBox"></div>
      </div>
    </div>

    <!-- جلسات زنده -->
    <div class="card mt-16">
      <div class="card-head">
        <div><h3>جلسات شارژ زنده</h3><div class="ch-sub"><span id="kActive2">—</span> جلسه فعال در سراسر شبکه</div></div>
        <div class="head-actions">
          <span class="pill pill-green"><span class="pdot"></span>زنده</span>
          <a href="{{ route('admin.sessions.index') }}" class="btn btn-sm">مشاهدهٔ همه</a>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr>
            <th>کاربر</th><th>ایستگاه / شارژر</th><th>کانکتور</th><th>باتری (SoC)</th>
            <th>انرژی</th><th>توان لحظه‌ای</th><th>مدت</th><th>هزینهٔ جاری (تومان)</th><th>وضعیت</th>
          </tr></thead>
          <tbody id="liveBody"></tbody>
        </table>
      </div>
    </div>
@endsection

@push('styles')
<style>
.legend-inline { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--text-2); font-weight:600; }
.legend-inline i { width:10px; height:10px; border-radius:3px; display:inline-block; }
.chart-axis { display:flex; justify-content:space-between; margin-top:8px; color:var(--muted); font-size:11px; }
.kpi-ic[data-ic] svg, .l-ic[data-ic] svg, .c-ic[data-ic] svg { width:100%; height:100%; }
.live-err { color:var(--red); }
</style>
@endpush

@push('scripts')
<script>
window.__DASH = @json($live);

(function () {
  var UI = window.AdminUI, fa = UI.fa, esc = UI.esc;
  document.querySelectorAll('[data-ic]').forEach(function (el) { el.innerHTML = window.icon(el.getAttribute('data-ic')); });
  function set(id, v) { var el = document.getElementById(id); if (el) el.textContent = v; }
  function trend(id, v) {
    var el = document.getElementById(id); if (!el) return;
    if (!v) { el.className = ''; el.textContent = ''; return; }
    el.className = v >= 0 ? 'trend-up' : 'trend-down';
    el.textContent = (v >= 0 ? '▲ ' : '▼ ') + fa(Math.abs(v)) + '٪ ';
  }
  function spark(id, vals, color) {
    var s = document.getElementById(id); if (!s) return;
    if (!vals.length || Math.max.apply(null, vals) <= 0) { s.innerHTML = ''; return; }
    var p = window.areaPath(vals, 92, 40, 3), l = window.linePath(vals, 92, 40, 3);
    s.innerHTML = '<path d="' + p + '" fill="' + color + '" opacity=".12"/><path d="' + l + '" fill="none" stroke="' + color + '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
  }

  function chart(rows) {
    var W = 720, H = 240, pad = 10;
    var energy = rows.map(function (r) { return r.energy; });
    var revenue = rows.map(function (r) { return r.revenue_toman; });
    var maxE = Math.max(1e-6, Math.max.apply(null, energy.concat([0])));
    var maxR = Math.max(1e-6, Math.max.apply(null, revenue.concat([0])));
    function path(vals, max, area) {
      var step = (W - pad * 2) / Math.max(1, vals.length - 1);
      var d = vals.map(function (v, i) { var x = pad + i * step; var y = pad + (H - pad * 2) * (1 - v / max); return (i ? 'L' : 'M') + x.toFixed(1) + ' ' + y.toFixed(1); }).join(' ');
      if (area) d += ' L' + (W - pad) + ' ' + (H - pad) + ' L' + pad + ' ' + (H - pad) + ' Z';
      return d;
    }
    var grid = '';
    for (var g = 0; g <= 4; g++) { var y = pad + (H - pad * 2) * g / 4; grid += '<line x1="' + pad + '" y1="' + y + '" x2="' + (W - pad) + '" y2="' + y + '" stroke="var(--border)" stroke-width="1" stroke-dasharray="3 5"/>'; }
    document.getElementById('mainChart').innerHTML =
      '<defs><linearGradient id="gE" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="var(--accent)" stop-opacity=".28"/><stop offset="1" stop-color="var(--accent)" stop-opacity="0"/></linearGradient></defs>' + grid +
      '<path d="' + path(energy, maxE, true) + '" fill="url(#gE)"/>' +
      '<path d="' + path(energy, maxE) + '" fill="none" stroke="var(--accent)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>' +
      '<path d="' + path(revenue, maxR) + '" fill="none" stroke="var(--orange)" stroke-width="2.4" stroke-dasharray="2 4" stroke-linecap="round" stroke-linejoin="round"/>';
    set('maxE', fa(Math.max.apply(null, energy.concat([0])), 2));
    set('maxR', fa(Math.max.apply(null, revenue.concat([0])), 0));
    var ax = document.getElementById('chartAxis'); ax.innerHTML = '';
    rows.forEach(function (r, i) { if (i % 4 === 0 || i === rows.length - 1) { var sp = document.createElement('span'); sp.textContent = fa(r.hour) + ':۰۰'; ax.appendChild(sp); } });
  }

  function donut(bd, util) {
    var data = [{ v: bd.charging || 0, c: 'var(--green)' }, { v: bd.available || 0, c: 'var(--accent)' }, { v: bd.reserved || 0, c: 'var(--amber)' }, { v: bd.faulted || 0, c: 'var(--red)' }, { v: bd.offline || 0, c: 'var(--muted)' }];
    var total = data.reduce(function (a, b) { return a + b.v; }, 0);
    var R = 54, C = 66, sw = 16, circ = 2 * Math.PI * R, off = 0;
    var rings = total ? data.map(function (d) {
      var len = d.v / total * circ;
      var el = '<circle cx="' + C + '" cy="' + C + '" r="' + R + '" fill="none" stroke="' + d.c + '" stroke-width="' + sw + '" stroke-dasharray="' + len + ' ' + (circ - len) + '" stroke-dashoffset="' + (-off) + '" transform="rotate(-90 ' + C + ' ' + C + ')"/>';
      off += len; return el;
    }).join('') : '<circle cx="' + C + '" cy="' + C + '" r="' + R + '" fill="none" stroke="var(--surface-3)" stroke-width="' + sw + '"/>';
    document.getElementById('donut').innerHTML = rings +
      '<text x="' + C + '" y="' + (C - 4) + '" text-anchor="middle" font-size="22" font-weight="800" fill="var(--text)" font-family="Vazirmatn">' + fa(util) + '٪</text>' +
      '<text x="' + C + '" y="' + (C + 14) + '" text-anchor="middle" font-size="10" fill="var(--muted)" font-family="Vazirmatn">بهره‌برداری</text>';
  }

  var stateLabel = { starting: ['در انتظار شروع', 'pill-accent'], charging: ['در حال شارژ', 'pill-green'], idle: ['توقف از سمت خودرو', 'pill-amber'], stop_requested: ['در حال توقف', 'pill-amber'] };
  function sessions(rows) {
    document.getElementById('liveBody').innerHTML = rows.length ? rows.map(function (r) {
      var st = stateLabel[r.status] || stateLabel.charging;
      var soc = r.prog == null ? '<span class="muted fs12">گزارش نشده</span>'
        : '<div class="bar"><i style="width:' + r.prog + '%"></i></div><div class="cell-muted tnum" style="margin-top:4px">' + fa(r.prog) + '٪</div>';
      return '<tr><td><div class="cell-user"><span class="uavatar" style="background:var(--accent)">' + esc(r.initials) + '</span><div><div class="cell-strong">' + esc(r.user) + '</div><div class="cell-muted mono">' + esc(r.id) + ' · ' + esc(r.auth) + '</div></div></div></td>' +
        '<td><div class="cell-strong">' + esc(r.station) + '</div><div class="cell-muted mono">' + esc(r.charger) + '</div></td>' +
        '<td><span class="tag">' + esc(r.connector) + (r.type ? ' · ' + esc(r.type) : '') + '</span></td>' +
        '<td style="min-width:120px">' + soc + '</td>' +
        '<td class="tnum cell-strong">' + fa(r.energy, 2) + ' kWh</td>' +
        '<td class="tnum">' + (r.power == null ? '—' : fa(r.power) + ' kW') + (r.voltage ? '<div class="cell-muted">' + fa(r.voltage, 0) + 'V · ' + fa(r.current) + 'A</div>' : '') + '</td>' +
        '<td class="tnum">' + fa(r.duration, 0) + ' دق</td>' +
        '<td class="tnum cell-strong">' + fa(r.cost, 0) + '</td>' +
        '<td><span class="pill ' + st[1] + '"><span class="pdot"></span>' + st[0] + '</span></td></tr>';
    }).join('') : '<tr><td colspan="9" class="muted" style="text-align:center;padding:24px">جلسه فعالی نیست</td></tr>';
  }

  function alerts(rows) {
    document.getElementById('alertsBox').innerHTML = rows.length ? rows.map(function (a) {
      return '<a class="lrow" href="' + esc(a.url) + '" style="text-decoration:none;color:inherit"><div class="l-ic ic-' + (a.level === 'red' ? 'red' : (a.level === 'amber' ? 'amber' : 'blue')) + '">' + window.icon('alert') + '</div>' +
        '<div class="l-main"><div class="l-title">' + esc(a.title) + '</div><div class="l-sub">' + esc(a.sub) + '</div></div>' +
        '<span class="pill pill-' + (a.level === 'blue' ? 'accent' : a.level) + '">' + esc(a.label) + '</span></a>';
    }).join('') : '<div class="cell-muted">هشدار فعالی ثبت نشده است.</div>';
    var pill = document.getElementById('sysPill');
    var critical = rows.some(function (a) { return a.level === 'red'; });
    pill.className = 'pill ' + (critical ? 'pill-red' : (rows.length ? 'pill-amber' : 'pill-green'));
    set('sysPillText', critical ? 'هشدار بحرانی فعال' : (rows.length ? rows.length.toLocaleString('fa-IR') + ' هشدار باز' : 'سامانه پایدار'));
  }

  function render(d) {
    var k = d.kpis || {};
    set('kChOnline', fa(k.chargers_online, 0)); set('kChTotal', fa(k.chargers_total, 0)); set('kUptime', fa(k.charger_uptime));
    set('kEnergy', fa(k.today_energy_kwh, 2)); trend('kEnergyTrend', k.energy_trend);
    set('kRevenue', fa(Math.round((k.today_revenue || 0) / 10), 0)); trend('kRevenueTrend', k.revenue_trend);
    set('kActive', fa(k.active_sessions, 0)); set('kActive2', fa(k.active_sessions, 0));
    set('kLoad', fa(k.load_kw)); set('kUtil', fa(k.utilization_percent));
    set('kCities', fa(k.cities_count, 0)); set('kStations', fa(k.stations_count, 0));
    var bd = d.breakdown || {};
    set('bdCharging', fa(bd.charging, 0)); set('bdAvailable', fa(bd.available, 0)); set('bdReserved', fa(bd.reserved, 0)); set('bdFaulted', fa(bd.faulted, 0)); set('bdOffline', fa(bd.offline, 0));
    donut(bd, k.utilization_percent || 0);
    var cc = d.connectorCounts || {};
    set('dcCount', fa((cc.dc || {}).count, 0)); set('dcKw', fa((cc.dc || {}).rated_kw));
    set('acCount', fa((cc.ac || {}).count, 0)); set('acKw', fa((cc.ac || {}).rated_kw));
    var rows = (d.chart || []).map(function (r) { r.revenue_toman = (r.revenue_rial || 0) / 10; return r; });
    chart(rows);
    spark('sparkEnergy', rows.map(function (r) { return r.energy; }), 'var(--green)');
    spark('sparkRevenue', rows.map(function (r) { return r.revenue_toman; }), 'var(--orange)');
    sessions(d.sessions || []);
    alerts(d.alerts || []);
  }

  render(window.__DASH || {});
  var poller = UI.live('dashboard', 10000, render);
  document.getElementById('refreshBtn').addEventListener('click', function () { poller.refresh(); });
})();
</script>
@endpush
