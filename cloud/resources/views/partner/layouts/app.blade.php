@php
    $partner = auth()->user()?->partner;
    $initials = mb_substr($partner?->name ?? 'ش', 0, 2);
    $onlineChargers = $partner ? \App\Models\Charger::whereIn('station_id', $partner->stations()->pluck('id'))->where('is_online', true)->count() : 0;
@endphp
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل شرکا') — SafirCharge</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/admin/app.css">
    @stack('styles')
</head>
<body
    data-page="{{ $page ?? 'dashboard' }}"
    data-title="{{ $title ?? '' }}"
    data-crumb="{{ $crumb ?? 'پنل شرکا' }}"
    data-partner-name="{{ $partner?->name }}"
    data-partner-code="{{ $partner?->code }}"
    data-partner-share="{{ $partner?->revenue_share_percent }}"
    data-partner-devices="{{ $onlineChargers }}"
    data-partner-initials="{{ $initials }}"
>
<div class="app">
<div class="main">
    <div class="content">
        @if(session('success'))
            <div style="margin-bottom:16px;padding:12px;background:var(--green-soft);border-radius:12px;color:var(--green)">{{ session('success') }}</div>
        @endif
        @yield('content')
    </div>
</div>
</div>
@stack('modals')
<script src="/assets/partner/app.js"></script>
@stack('scripts')
</body>
</html>
