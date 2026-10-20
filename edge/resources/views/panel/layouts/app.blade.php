<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'پنل ایستگاه') — SafirCharge Edge</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/panel/panel.css">
    @stack('styles')
</head>
<body>
<div class="shell">
    <aside class="sidebar">
        <div class="brand">SafirCharge</div>
        <div class="sub">پنل محلی ایستگاه</div>
        <nav class="nav">
            <a href="{{ route('panel.dashboard') }}" class="{{ request()->routeIs('panel.dashboard') ? 'active' : '' }}"><span data-ic="grid"></span> پیشخوان</a>
            <a href="{{ route('panel.remote') }}" class="{{ request()->routeIs('panel.remote') ? 'active' : '' }}"><span data-ic="bolt"></span> کنترل دستگاه‌ها</a>
            <a href="{{ route('panel.sessions') }}" class="{{ request()->routeIs('panel.sessions') ? 'active' : '' }}"><span data-ic="clock"></span> جلسات شارژ</a>
            <a href="{{ route('panel.sync') }}" class="{{ request()->routeIs('panel.sync') ? 'active' : '' }}"><span data-ic="refresh"></span> سینک و داده</a>
            <a href="{{ route('panel.setup') }}" class="{{ request()->routeIs('panel.setup') ? 'active' : '' }}"><span data-ic="settings"></span> تنظیمات ایستگاه</a>
        </nav>
        <form method="POST" action="{{ route('panel.logout') }}" style="margin-top:24px">
            @csrf
            <button type="submit" class="btn btn-sm" style="width:100%;color:var(--sidebar-text);background:transparent;border-color:rgba(255,255,255,.15)">
                <span data-ic="logout"></span> خروج
            </button>
        </form>
    </aside>
    <div class="main">
        <header class="topbar">
            <h1>@yield('heading', 'پنل ایستگاه')</h1>
            <div class="flex items-center gap-8">
                @if(!empty($stationName ?? null))
                <span class="pill pill-accent">{{ $stationName }}</span>
                @endif
                <span class="pill pill-green" id="cloudPill"><span class="pdot"></span>محلی فعال</span>
            </div>
        </header>
        <main class="content">
            @if(session('success'))
            <div class="alert" style="background:var(--green-soft);color:var(--green)">{{ session('success') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</div>
<script src="/assets/panel/panel-icons.js"></script>
<script>document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});</script>
@stack('scripts')
</body>
</html>
