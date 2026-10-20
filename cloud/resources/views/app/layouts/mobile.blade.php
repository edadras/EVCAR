<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'SafirCharge')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/mobile/app-mobile.css">
    @stack('styles')
</head>
<body
    @if(!empty($tab)) data-tab="{{ $tab }}" @endif
    @if($noTab ?? false) data-no-tab @endif
    @if($darkStatus ?? false) data-dark-status @endif
>
<div class="phone {{ $phoneClass ?? '' }}">
    <button type="button" class="sc-backend-badge cloud" id="scBackendBadge" hidden title="تغییر منبع داده" onclick="window.scOpenBackendPicker&&window.scOpenBackendPicker()">ابری</button>
    @yield('phone')
</div>
@stack('overlays')
@include('app.partials.backend-picker')
<script>
window.SC_ROUTES = {
    home: @json(route('app.home')),
    map: @json(route('app.map')),
    navigator: @json(route('app.navigator')),
    scan: @json(route('app.scan')),
    wallet: @json(route('app.wallet')),
    account: @json(route('app.account')),
    arrival: @json(route('app.arrival')),
};
window.SC_CONNECTIVITY = @json($scConnectivity ?? []);
window.SC_API = {
    context: @json(route('app.api.context')),
    chargingCloud: @json(route('app.api.charging.status')),
    chargingEdge: '/api/v1/app/charging/status',
    chargingStartEdge: '/api/v1/app/charging/start',
    chargingStopEdge: '/api/v1/app/charging/stop',
    arrivalCloud: @json(route('app.api.arrival')),
    arrivalEdge: '/api/v1/app/arrival',
    arrivalConfirmCloud: @json(route('app.api.arrival.confirm')),
    arrivalCancelCloud: @json(route('app.api.arrival.cancel')),
    arrivalConfirmEdge: '/api/v1/app/arrival/confirm',
    arrivalCancelEdge: '/api/v1/app/arrival/cancel',
    authorizeEdge: '/api/v1/app/authorize',
    authorizeStatusEdge: '/api/v1/app/authorize/status',
    otpRequestEdge: '/api/v1/auth/otp/request',
    otpVerifyEdge: '/api/v1/auth/otp/verify',
    authLookupEdge: '/api/v1/auth/lookup',
};
</script>
<script src="/assets/mobile/app-mobile.js"></script>
<script src="/assets/mobile/edge-router.js"></script>
@if(auth()->check())
<script src="/assets/mobile/arrival-monitor.js"></script>
@endif
@stack('scripts')
<style>
.sc-backend-badge{position:fixed;bottom:calc(92px + env(safe-area-inset-bottom,0));left:max(12px,calc(50% - var(--phone-w,430px)/2 + 12px));z-index:300;opacity:.92;border:none;border-radius:20px;padding:4px 12px;font-family:inherit;font-size:11px;font-weight:800;letter-spacing:.02em;cursor:pointer;box-shadow:var(--shadow-md)}
body[data-no-tab] .sc-backend-badge{bottom:calc(120px + env(safe-area-inset-bottom,0))}
.sc-backend-badge.cloud{background:var(--accent-soft);color:var(--accent-strong)}
.sc-backend-badge.edge{background:var(--green-soft);color:var(--green)}
.sc-bp-overlay{position:fixed;inset:0;background:rgba(8,16,24,.55);display:flex;align-items:flex-end;justify-content:center;opacity:0;visibility:hidden;transition:opacity .2s;z-index:400;padding:0 12px}
.sc-bp-overlay.open{opacity:1;visibility:visible}
.sc-bp-sheet{width:100%;max-width:var(--phone-w);background:var(--surface);border-radius:22px 22px 0 0;padding:12px 18px calc(20px + env(safe-area-inset-bottom,0))}
.sc-bp-grab{width:42px;height:5px;border-radius:5px;background:var(--border);margin:0 auto 14px}
.sc-bp-title{margin:0 0 6px;font-size:17px}
.sc-bp-desc{margin:0 0 14px;font-size:13px;color:var(--muted);line-height:1.6}
.sc-bp-hint{margin:14px 0 0;font-size:11.5px;color:var(--text-2);line-height:1.7}
.sc-bp-opt{display:flex;align-items:center;gap:12px;width:100%;text-align:right;border:1.5px solid var(--border);background:var(--surface-2);border-radius:14px;padding:12px 14px;margin-bottom:10px;font-family:inherit;cursor:pointer}
.sc-bp-opt:hover{border-color:var(--accent)}
.sc-bp-kind{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;font-size:11px;font-weight:800;background:var(--accent-soft);color:var(--accent-strong);flex:none}
.sc-bp-opt[data-id^="edge"] .sc-bp-kind{background:var(--green-soft);color:var(--green)}
.sc-bp-main{display:flex;flex-direction:column;gap:3px;flex:1;min-width:0}
.sc-bp-main b{font-size:14px}
.sc-bp-main small{font-size:12px;color:var(--muted)}
</style>
</body>
</html>
