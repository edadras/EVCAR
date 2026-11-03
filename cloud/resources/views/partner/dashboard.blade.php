@extends('partner.layouts.app', ['page' => 'dashboard', 'title' => 'داشبورد', 'crumb' => 'پنل شرکا · '.$partner->name])

@section('content')
@php use App\Support\Persian; @endphp

<div class="flex between items-center mt-8" style="margin-bottom:18px">
    <span class="pill pill-green" id="livePill"><span class="pdot"></span><span id="liveStamp">به‌روزرسانی خودکار</span></span>
    <a href="{{ route('partner.payments') }}" class="btn btn-sm btn-primary">مشاهدهٔ پرداختی‌ها</a>
</div>

<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-ic ic-green" data-ic="coins"></div>
        <div class="kpi-label">سهم درآمد شما (این ماه)</div>
        <div class="kpi-val tnum"><span id="kShare">{{ Persian::money($partner_share_toman) }}</span> <small>تومان</small></div>
        <div class="kpi-foot" id="kTrend">
            @if($share_trend !== null)
                <span class="{{ $share_trend >= 0 ? 'trend-up' : 'trend-down' }}">{{ $share_trend >= 0 ? '▲' : '▼' }} {{ Persian::digits(abs($share_trend)) }}٪</span> <span class="muted">نسبت به ماه قبل</span>
            @else
                <span class="muted">ماه قبل درآمدی ثبت نشده</span>
            @endif
        </div>
    </div>
    <div class="kpi">
        <div class="kpi-ic ic-blue" data-ic="bolt"></div>
        <div class="kpi-label">انرژی تحویلی (این ماه)</div>
        <div class="kpi-val tnum"><span id="kEnergy">{{ Persian::kwh($month_energy, 2) }}</span> <small>kWh</small></div>
        <div class="kpi-foot"><span class="muted"><span id="kSessions">{{ Persian::digits($month_sessions) }}</span> جلسه</span></div>
    </div>
    <div class="kpi">
        <div class="kpi-ic ic-orange" data-ic="station"></div>
        <div class="kpi-label">دستگاه‌های آنلاین</div>
        <div class="kpi-val tnum"><span id="kOnline">{{ Persian::digits($chargers_online) }}</span> <small>/ <span id="kTotal">{{ Persian::digits($chargers_total) }}</span></small></div>
    </div>
    <div class="kpi">
        <div class="kpi-ic ic-violet" data-ic="chart"></div>
        <div class="kpi-label">ایستگاه‌ها</div>
        <div class="kpi-val tnum" id="kStations">{{ Persian::digits($stations_count) }}</div>
    </div>
</div>

<div class="card mt-16">
    <div class="card-head"><h3>آخرین جلسات ایستگاه‌های شما</h3></div>
    <div class="card-pad" style="padding:0">
        <table class="tbl">
            <thead><tr><th>ایستگاه</th><th>شارژر</th><th>انرژی (kWh)</th><th>درآمد (تومان)</th><th>زمان</th></tr></thead>
            <tbody id="recentBody"></tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-ic]').forEach(function (el) { el.innerHTML = window.icon(el.getAttribute('data-ic')); });

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function fa(n) { return Number(n).toLocaleString('fa-IR'); }

    function renderSessions(rows) {
        document.getElementById('recentBody').innerHTML = rows.length ? rows.map(function (r) {
            return '<tr><td>' + esc(r.station) + (r.active ? ' <span class="pill pill-green" style="font-size:10px">در حال شارژ</span>' : '') + '</td>' +
                '<td class="tnum">' + esc(r.charger) + '</td><td class="tnum">' + esc(r.energy) + '</td>' +
                '<td class="tnum">' + esc(r.revenue) + '</td><td class="tnum">' + esc(r.time) + '</td></tr>';
        }).join('') : '<tr><td colspan="5" class="muted" style="text-align:center;padding:18px">جلسه‌ای ثبت نشده است.</td></tr>';
    }

    function apply(d) {
        var k = d.kpis;
        document.getElementById('kShare').textContent = k.share;
        document.getElementById('kEnergy').textContent = k.energy;
        document.getElementById('kSessions').textContent = k.sessions;
        document.getElementById('kOnline').textContent = k.online;
        document.getElementById('kTotal').textContent = k.total;
        document.getElementById('kStations').textContent = k.stations;
        document.getElementById('kTrend').innerHTML = k.trend === null
            ? '<span class="muted">ماه قبل درآمدی ثبت نشده</span>'
            : '<span class="' + (k.trend >= 0 ? 'trend-up' : 'trend-down') + '">' + (k.trend >= 0 ? '▲' : '▼') + ' ' + fa(Math.abs(k.trend)) + '٪</span> <span class="muted">نسبت به ماه قبل</span>';
        renderSessions(d.sessions || []);
        var t = new Date(d.at);
        document.getElementById('liveStamp').textContent = 'به‌روز: ' + t.toLocaleTimeString('fa-IR', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    renderSessions(@json($recentSessions));

    var url = @json(route('partner.live')), busy = false;
    function tick() {
        if (busy || document.hidden) return;
        busy = true;
        fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', cache: 'no-store' })
            .then(function (r) { if (!r.ok) throw new Error(r.status); return r.json(); })
            .then(apply)
            .catch(function () { document.getElementById('liveStamp').textContent = 'قطع ارتباط — تلاش مجدد…'; })
            .then(function () { busy = false; });
    }
    setInterval(tick, 15000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) tick(); });
})();
</script>
@endpush
