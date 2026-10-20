@extends('panel.layouts.app', ['stationName' => $station?->name])

@section('title', 'پیشخوان')
@section('heading', 'پیشخوان ایستگاه')

@section('content')
<div class="banner">
    <strong>حالت آفلاین:</strong> شارژ و کنترل OCPP بدون مرکز ادامه می‌یابد. سینک <em>خودکار</em> هر دقیقه فقط تغییرات را با مرکز رد و بدل می‌کند و در قطع ارتباط، ارسال صف با Retry ادامه می‌یابد.
</div>

<div class="stat-grid mt16">
    <div class="stat-box"><div class="l">کانکتورها</div><div class="v tnum">{{ \App\Support\Persian::num($connectorCount) }}</div></div>
    <div class="stat-box"><div class="l">شارژ فعال</div><div class="v tnum">{{ \App\Support\Persian::num($activeSessions) }}</div></div>
    <div class="stat-box"><div class="l">صف ارسال به ابر</div><div class="v tnum">{{ \App\Support\Persian::num($syncStatus['outbox']['pending'] ?? 0) }}</div></div>
    <div class="stat-box"><div class="l">مرکز ابری</div><div class="v" style="font-size:16px">{{ ($syncStatus['cloud']['reachable'] ?? false) ? 'متصل' : 'آفلاین' }}</div></div>
</div>

<div class="grid col-2 mt16">
    <div class="card">
        <div class="card-head"><h3>ایستگاه</h3></div>
        <div class="card-pad fs13">
            <div><span class="muted">نام:</span> <strong>{{ $station?->name ?? '—' }}</strong></div>
            <div class="mt8"><span class="muted">کد Edge:</span> {{ $station?->edge_code ?? '—' }}</div>
            <div class="mt8"><span class="muted">نسخه:</span> {{ $station?->software_version ?? '—' }}</div>
            <div class="mt8"><span class="muted">آخرین سینک از ابر:</span> {{ $syncStatus['cloud']['last_pull_at'] ? \Carbon\Carbon::parse($syncStatus['cloud']['last_pull_at'])->diffForHumans() : '—' }}</div>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3>دسترسی سریع</h3></div>
        <div class="card-pad flex gap-8" style="flex-wrap:wrap">
            <a href="{{ route('panel.remote') }}" class="btn btn-primary"><span data-ic="bolt"></span> کنترل دستگاه</a>
            <a href="{{ route('panel.setup') }}" class="btn"><span data-ic="settings"></span> تنظیمات ایستگاه</a>
            <a href="{{ route('panel.sync') }}" class="btn"><span data-ic="refresh"></span> سینک داده</a>
            <a href="{{ route('panel.sessions') }}" class="btn"><span data-ic="clock"></span> جلسات</a>
        </div>
    </div>
</div>

<div class="card mt16">
    <div class="card-head"><h3>آخرین جلسات شارژ</h3></div>
    <div class="card-pad" style="padding-top:0">
        <table class="table">
            <thead><tr><th>وضعیت</th><th>شروع</th><th>انرژی</th><th>هزینه (ریال)</th></tr></thead>
            <tbody>
            @forelse($recentSessions as $s)
                <tr>
                    <td><span class="pill {{ $s->status === 'active' ? 'pill-green' : 'pill-accent' }}">{{ $s->status }}</span></td>
                    <td class="tnum">{{ $s->started_at?->format('Y/m/d H:i') ?? '—' }}</td>
                    <td class="tnum">{{ \App\Support\Persian::num($s->energy_kwh) }} kWh</td>
                    <td class="tnum">{{ \App\Support\Persian::num((int) $s->cost_amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">جلسه‌ای ثبت نشده — پس از سینک یا شروع شارژ محلی نمایش داده می‌شود.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
