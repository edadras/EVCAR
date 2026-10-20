@extends('admin.layouts.app', ['page' => 'dashboard', 'title' => 'داشبورد و مانیتورینگ', 'crumb' => 'مدیریت شبکه'])

@section('content')
@php use App\Support\Persian; @endphp

<!-- نوار اقدام -->
    <div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="flex items-center gap-12">
        <div class="seg" id="rangeSeg">
          <button class="active">امروز</button>
          <button>هفته</button>
          <button>ماه</button>
        </div>
        <span class="pill pill-green"><span class="pdot"></span>سامانه پایدار</span>
      </div>
      <div class="flex gap-8">
        <button class="btn btn-sm"><span class="ic">↻</span> به‌روزرسانی</button>
        <button class="btn btn-sm btn-primary" id="exportBtn">خروجی گزارش</button>
      </div>
    </div>

    <!-- KPI -->
    <div class="kpi-grid">
      <div class="kpi rise" style="animation-delay:.02s">
        <div class="kpi-ic ic-blue" data-ic="bolt"></div>
        <div class="kpi-label">شارژرهای آنلاین</div>
        <div class="kpi-val tnum">{{ Persian::digits($kpis['chargers_online']) }} <small>/ {{ Persian::digits($kpis['chargers_total']) }}</small></div>
        <div class="kpi-foot"><span class="muted">{{ Persian::digits($kpis['charger_uptime'] ?? 0) }}٪ در دسترس</span></div>
        <svg class="kpi-spark" width="92" height="40" viewBox="0 0 92 40" data-spark="up" data-color="var(--accent)"></svg>
      </div>
      <div class="kpi rise" style="animation-delay:.06s">
        <div class="kpi-ic ic-green" data-ic="leaf"></div>
        <div class="kpi-label">انرژی تحویلی امروز</div>
        <div class="kpi-val tnum">{{ Persian::digits(number_format($kpis['today_energy_kwh'], 0)) }} <small>kWh</small></div>
        <div class="kpi-foot">@if(($kpis['energy_trend'] ?? 0) != 0)<span class="{{ ($kpis['energy_trend'] ?? 0) >= 0 ? 'trend-up' : 'trend-down' }}">{{ ($kpis['energy_trend'] ?? 0) >= 0 ? '▲' : '▼' }} {{ Persian::digits(abs($kpis['energy_trend'] ?? 0)) }}٪</span>@endif<span class="muted">نسبت به دیروز</span></div>
        <svg class="kpi-spark" width="92" height="40" viewBox="0 0 92 40" data-spark="up" data-color="var(--green)"></svg>
      </div>
      <div class="kpi rise" style="animation-delay:.1s">
        <div class="kpi-ic ic-orange" data-ic="coins"></div>
        <div class="kpi-label">درآمد امروز</div>
        <div class="kpi-val tnum">{{ Persian::digits($kpis['today_revenue_million']) }} <small>میلیون تومان</small></div>
        <div class="kpi-foot">@if(($kpis['revenue_trend'] ?? 0) != 0)<span class="{{ ($kpis['revenue_trend'] ?? 0) >= 0 ? 'trend-up' : 'trend-down' }}">{{ ($kpis['revenue_trend'] ?? 0) >= 0 ? '▲' : '▼' }} {{ Persian::digits(abs($kpis['revenue_trend'] ?? 0)) }}٪</span>@endif<span class="muted">نسبت به دیروز</span></div>
        <svg class="kpi-spark" width="92" height="40" viewBox="0 0 92 40" data-spark="up" data-color="var(--orange)"></svg>
      </div>
      <div class="kpi rise" style="animation-delay:.14s">
        <div class="kpi-ic ic-violet" data-ic="activity"></div>
        <div class="kpi-label">جلسات فعال</div>
        <div class="kpi-val tnum">{{ Persian::digits($kpis['active_sessions']) }}</div>
        <div class="kpi-foot"><span class="muted">نرخ بهره‌برداری {{ Persian::digits($kpis['utilization_percent'] ?? 0) }}٪</span></div>
        <svg class="kpi-spark" width="92" height="40" viewBox="0 0 92 40" data-spark="down" data-color="var(--violet)"></svg>
      </div>
    </div>

    <!-- نمودار + بهره‌برداری -->
    <div class="grid col-2-1 mt-16">
      <div class="card">
        <div class="card-head">
          <div>
            <h3>روند انرژی و درآمد</h3>
            <div class="ch-sub">۲۴ ساعت گذشته · به تفکیک ساعت</div>
          </div>
          <div class="head-actions">
            <span class="legend-inline"><i style="background:var(--accent)"></i> انرژی (kWh)</span>
            <span class="legend-inline"><i style="background:var(--orange)"></i> درآمد</span>
          </div>
        </div>
        <div class="card-pad">
          <svg id="mainChart" width="100%" height="240" viewBox="0 0 720 240" preserveAspectRatio="none"></svg>
          <div class="chart-axis" id="chartAxis"></div>
        </div>
      </div>

      <div class="card">
        <div class="card-head"><h3>بهره‌برداری شبکه</h3></div>
        <div class="card-pad">
          <div class="flex items-center gap-16">
            <svg width="132" height="132" viewBox="0 0 132 132" id="donut"></svg>
            <div class="legend">
              <div class="li"><span class="sw" style="background:var(--green)"></span><span>در حال شارژ</span><b class="tnum" style="margin-inline-start:auto">{{ Persian::digits($breakdown['charging']) }}</b></div>
              <div class="li"><span class="sw" style="background:var(--accent)"></span><span>آماده</span><b class="tnum" style="margin-inline-start:auto">{{ Persian::digits($breakdown['available']) }}</b></div>
              <div class="li"><span class="sw" style="background:var(--amber)"></span><span>رزرو شده</span><b class="tnum" style="margin-inline-start:auto">{{ Persian::digits($breakdown['reserved']) }}</b></div>
              <div class="li"><span class="sw" style="background:var(--red)"></span><span>خطا / آفلاین</span><b class="tnum" style="margin-inline-start:auto">{{ Persian::digits($breakdown['faulted']) }}</b></div>
            </div>
          </div>
          <hr class="sep">
          <div class="lrow">
            <div class="l-ic ic-blue" data-ic="plug"></div>
            <div class="l-main"><div class="l-title">کانکتورهای DC سریع</div><div class="l-sub">میانگین توان ۸۴ کیلووات</div></div>
            <div class="l-end"><b class="tnum">{{ Persian::digits($connectorCounts['dc'] ?? 0) }}</b></div>
          </div>
          <div class="lrow">
            <div class="l-ic ic-green" data-ic="plug"></div>
            <div class="l-main"><div class="l-title">کانکتورهای AC</div><div class="l-sub">میانگین توان ۲۲ کیلووات</div></div>
            <div class="l-end"><b class="tnum">{{ Persian::digits($connectorCounts['ac'] ?? 0) }}</b></div>
          </div>
        </div>
      </div>
    </div>

    <!-- نقشه + هشدارها -->
    <div class="grid col-2-1 mt-16">
      <div class="card">
        <div class="card-head">
          <div><h3>نقشهٔ زندهٔ شبکه</h3><div class="ch-sub">{{ Persian::digits($kpis['cities_count'] ?? 0) }} شهر · {{ Persian::digits($kpis['stations_count'] ?? 0) }} ایستگاه</div></div>
          <div class="head-actions"><a href="/admin/stations" class="btn btn-sm">نمای کامل نقشه</a></div>
        </div>
        <div class="card-pad">
          @include('admin.partials.leaflet-map', ['markers' => $mapMarkers, 'mapId' => 'dashMap', 'height' => '320px'])
        </div>
      </div>

      <div class="card">
        <div class="card-head">
          <div><h3>هشدارها و خطاها</h3></div>
          <div class="head-actions"><a href="/admin/support" class="btn btn-sm">همه</a></div>
        </div>
        <div class="card-pad" style="padding-top:6px">
          @forelse($alerts as $alert)
            @php
              $isTicket = $alert instanceof \App\Models\SupportTicket;
              $prio = $isTicket ? $alert->priority : 'high';
              $pill = in_array($prio, ['critical', 'high']) ? 'pill-red' : 'pill-amber';
              $label = $prio === 'critical' ? 'بحرانی' : ($prio === 'high' ? 'هشدار' : 'اطلاع');
              $title = $isTicket ? ($alert->subject ?? 'تیکت پشتیبانی') : ('سفارش تعمیر #'.($alert->id ?? ''));
              $sub = ($alert->station?->name ?? '—').' · '.($alert->created_at?->diffForHumans() ?? '—');
            @endphp
            <div class="lrow">
              <div class="l-ic {{ $pill === 'pill-red' ? 'ic-red' : 'ic-amber' }}" data-ic="alert"></div>
              <div class="l-main"><div class="l-title">{{ $title }}</div><div class="l-sub">{{ $sub }}</div></div>
              <span class="pill {{ $pill }}">{{ $label }}</span>
            </div>
          @empty
            <div class="cell-muted">هشدار فعالی ثبت نشده است.</div>
          @endforelse
        </div>
      </div>
    </div>

    <!-- جلسات زنده -->
    <div class="card mt-16">
      <div class="card-head">
        <div><h3>جلسات شارژ زنده</h3><div class="ch-sub">{{ Persian::digits($kpis['active_sessions']) }} جلسه فعال در سراسر شبکه</div></div>
        <div class="head-actions">
          <span class="pill pill-green"><span class="pdot"></span>زنده</span>
          <a href="/admin/sessions" class="btn btn-sm">مشاهدهٔ همه</a>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr>
            <th>کاربر</th><th>ایستگاه / شارژر</th><th>کانکتور</th><th>پیشرفت</th>
            <th>انرژی</th><th>توان لحظه‌ای</th><th>مدت</th><th>هزینه</th><th>وضعیت</th>
          </tr></thead>
          <tbody>
            @forelse($activeSessions as $s)
            @php
                $prog = min(99, max(5, (int)($s->soc_end ?? $s->soc_start ?? 50)));
                $mins = (int) floor(($s->duration_seconds ?? 0) / 60);
            @endphp
            <tr>
              <td><div class="cell-user"><span class="uavatar" style="background:var(--accent)">{{ mb_substr($s->user?->name ?? 'م', 0, 2) }}</span><div><div class="cell-strong">{{ $s->user?->name ?? 'مهمان' }}</div><div class="cell-muted mono">{{ $s->session_code ?? '—' }}</div></div></div></td>
              <td><div class="cell-strong">{{ $s->station?->name }}</div><div class="cell-muted mono">{{ $s->charger?->charge_point_id }}</div></td>
              <td><span class="tag">{{ $s->charger?->connector_type ?? 'DC' }}</span></td>
              <td style="min-width:130px"><div class="bar"><i style="width:{{ $prog }}%"></i></div><div class="cell-muted tnum" style="margin-top:4px">{{ Persian::digits($prog) }}٪</div></td>
              <td class="tnum cell-strong">{{ Persian::kwh($s->energy_kwh) }} kWh</td>
              <td class="tnum">{{ Persian::digits($s->charger?->max_power_kw ?? 0) }} kW</td>
              <td class="tnum">{{ Persian::digits($mins) }} دق</td>
              <td class="tnum cell-strong">{{ Persian::money($s->cost_amount / 10) }}</td>
              <td><span class="pill pill-green"><span class="pdot"></span>در حال شارژ</span></td>
            </tr>
            @empty
            <tr><td colspan="9" class="muted" style="text-align:center;padding:24px">جلسه فعالی نیست</td></tr>
            @endforelse
          </tbody>
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
</style>
@endpush

@push('scripts')
<script>
window.__BREAKDOWN = {!! json_encode($breakdown, JSON_UNESCAPED_UNICODE) !!};

window.__CHART = {!! json_encode($chart, JSON_UNESCAPED_UNICODE) !!};

(function () {
  // پر کردن آیکون‌ها
  document.querySelectorAll('[data-ic]').forEach(function (el) {
    el.innerHTML = window.icon(el.getAttribute('data-ic'));
  });

  // اسپارک‌لاین‌های KPI
  document.querySelectorAll('[data-spark]').forEach(function (s) {
    var up = s.getAttribute('data-spark') === 'up';
    var base = [5,7,6,9,8,11,10,13,12,15,14,18];
    var vals = up ? base : base.slice().reverse();
    var c = s.getAttribute('data-color');
    var p = window.areaPath(vals, 92, 40, 3);
    var l = window.linePath(vals, 92, 40, 3);
    s.innerHTML = '<path d="'+p+'" fill="'+c+'" opacity=".12"/><path d="'+l+'" fill="none" stroke="'+c+'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>';
  });

  // نمودار اصلی — دو سری
  (function () {
    var W=720, H=240, pad=10;
    var energy=(window.__CHART||[]).map(function(r){return r.energy;});
    var revenue=(window.__CHART||[]).map(function(r){return r.revenue;});
    function path(vals, area){
      var max=48, min=0;
      var step=(W-pad*2)/(vals.length-1);
      var d=vals.map(function(v,i){var x=pad+i*step;var y=pad+(H-pad*2)*(1-(v-min)/(max-min));return (i?'L':'M')+x.toFixed(1)+' '+y.toFixed(1);}).join(' ');
      if(area) d+=' L'+(W-pad)+' '+(H-pad)+' L'+pad+' '+(H-pad)+' Z';
      return d;
    }
    var svg=document.getElementById('mainChart');
    var grid='';
    for(var g=0; g<=4; g++){var y=pad+(H-pad*2)*g/4; grid+='<line x1="'+pad+'" y1="'+y+'" x2="'+(W-pad)+'" y2="'+y+'" stroke="var(--border)" stroke-width="1" stroke-dasharray="3 5"/>';}
    svg.innerHTML =
      '<defs><linearGradient id="gE" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="var(--accent)" stop-opacity=".28"/><stop offset="1" stop-color="var(--accent)" stop-opacity="0"/></linearGradient></defs>'+
      grid+
      '<path d="'+path(energy,true)+'" fill="url(#gE)"/>'+
      '<path d="'+path(energy)+'" fill="none" stroke="var(--accent)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>'+
      '<path d="'+path(revenue)+'" fill="none" stroke="var(--orange)" stroke-width="2.4" stroke-dasharray="2 4" stroke-linecap="round" stroke-linejoin="round"/>';
    var ax=document.getElementById('chartAxis');
    ['۰۰','۰۴','۰۸','۱۲','۱۶','۲۰','۲۴'].forEach(function(t){var sp=document.createElement('span');sp.textContent=t;ax.appendChild(sp);});
  })();

  // دونات
  (function () {
    var bd=window.__BREAKDOWN||{}; var data=[{v:bd.charging||0,c:'var(--green)'},{v:bd.available||0,c:'var(--accent)'},{v:bd.reserved||0,c:'var(--amber)'},{v:bd.faulted||0,c:'var(--red)'}];
    var total=data.reduce(function(a,b){return a+b.v;},0);
    var R=54, C=66, sw=16, circ=2*Math.PI*R, off=0;
    var rings=data.map(function(d){
      var frac=d.v/total, len=frac*circ;
      var el='<circle cx="'+C+'" cy="'+C+'" r="'+R+'" fill="none" stroke="'+d.c+'" stroke-width="'+sw+'" stroke-dasharray="'+len+' '+(circ-len)+'" stroke-dashoffset="'+(-off)+'" transform="rotate(-90 '+C+' '+C+')" stroke-linecap="butt"/>';
      off+=len; return el;
    }).join('');
    document.getElementById('donut').innerHTML = rings +
      '<text x="'+C+'" y="'+(C-4)+'" text-anchor="middle" font-size="22" font-weight="800" fill="var(--text)" font-family="Vazirmatn">'+'{{ Persian::digits($kpis["utilization_percent"]) }}٪'+'</text>'+
      '<text x="'+C+'" y="'+(C+14)+'" text-anchor="middle" font-size="10" fill="var(--muted)" font-family="Vazirmatn">بهره‌برداری</text>';
  })();

  // نقشه از partial leaflet

  // سگمنت
  document.querySelectorAll('#rangeSeg button').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#rangeSeg button').forEach(function(x){x.classList.remove('active');});
      b.classList.add('active');
    });
  });
})();
</script>
@endpush
