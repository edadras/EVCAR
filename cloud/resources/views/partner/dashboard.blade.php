@extends('partner.layouts.app', ['page' => 'dashboard', 'title' => 'داشبورد', 'crumb' => 'پنل شرکا · '.$partner->name])

@section('content')
@php use App\Support\Persian; @endphp

<div class="flex between items-center mt-8" style="margin-bottom:18px">
    <span class="pill pill-green"><span class="pdot"></span>داده زنده</span>
    <a href="{{ route('partner.payments') }}" class="btn btn-sm btn-primary">مشاهدهٔ پرداختی‌ها</a>
</div>

<div class="kpi-grid">
    <div class="kpi">
        <div class="kpi-ic ic-green" data-ic="coins"></div>
        <div class="kpi-label">سهم درآمد شما (این ماه)</div>
        <div class="kpi-val tnum">{{ Persian::digits($partner_share_million) }} <small>میلیون تومان</small></div>
        <div class="kpi-foot"><span class="{{ $share_trend >= 0 ? 'trend-up' : 'trend-down' }}">{{ $share_trend >= 0 ? '▲' : '▼' }} {{ Persian::digits(abs($share_trend)) }}٪</span></div>
    </div>
    <div class="kpi">
        <div class="kpi-ic ic-blue" data-ic="bolt"></div>
        <div class="kpi-label">انرژی تحویلی</div>
        <div class="kpi-val tnum">{{ Persian::digits(number_format($month_energy, 0)) }} <small>kWh</small></div>
        <div class="kpi-foot"><span class="muted">{{ Persian::digits($month_sessions) }} جلسه</span></div>
    </div>
    <div class="kpi">
        <div class="kpi-ic ic-orange" data-ic="station"></div>
        <div class="kpi-label">دستگاه‌های فعال</div>
        <div class="kpi-val tnum">{{ Persian::digits($chargers_online) }} <small>/ {{ Persian::digits($chargers_total) }}</small></div>
    </div>
    <div class="kpi">
        <div class="kpi-ic ic-violet" data-ic="chart"></div>
        <div class="kpi-label">ایستگاه‌ها</div>
        <div class="kpi-val tnum">{{ Persian::digits($stations_count) }}</div>
    </div>
</div>

<div class="card mt-16">
    <div class="card-head"><h3>آخرین جلسات ایستگاه‌های شما</h3></div>
    <div class="card-pad" style="padding:0">
        <table class="tbl">
            <thead><tr><th>ایستگاه</th><th>شارژر</th><th>انرژی</th><th>درآمد</th><th>زمان</th></tr></thead>
            <tbody>
            @foreach($recentSessions as $s)
                <tr>
                    <td>{{ $s->station?->name }}</td>
                    <td class="tnum">{{ $s->charger?->charge_point_id }}</td>
                    <td class="tnum">{{ Persian::kwh($s->energy_kwh) }}</td>
                    <td class="tnum">{{ Persian::money($s->cost_amount / 10) }}</td>
                    <td class="tnum">{{ $s->started_at?->format('m/d H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script>document.querySelectorAll('[data-ic]').forEach(function (el) { el.innerHTML = window.icon(el.getAttribute('data-ic')); });</script>
@endpush
