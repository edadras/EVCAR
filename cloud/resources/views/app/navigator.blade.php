@extends('app.layouts.mobile', ['tab' => 'map', 'noTab' => true])

@section('title', 'SafirCharge — مسیریاب برقی')

@section('phone')
@php use App\Support\Persian; @endphp
<div class="nav-map" id="navMap"></div>

<div class="nav-top">
    <a href="{{ route('app.map') }}" class="nav-back" data-ic="chevron"></a>
    <div class="nav-title">
        <div class="fw8">مسیریاب برقی</div>
        <div class="fs11 muted">برنامه‌ریزی مسیر و مصرف انرژی</div>
    </div>
    @if($apiConfigured)
        <span class="pill pill-green" style="font-size:10px">سرور پیشرفته</span>
    @else
        <span class="pill pill-accent" style="font-size:10px">حالت ابری</span>
    @endif
</div>

<button class="nav-loc" type="button" id="navLocBtn" data-ic="nav" title="موقعیت من"></button>
<button class="nav-pin" type="button" id="navDestBtn" title="انتخاب مقصد">📍</button>
<button class="nav-pin nav-origin" type="button" id="navOriginBtn" title="انتخاب مبدا">◎</button>

<div class="nav-sheet" id="navSheet">
    <div class="sheet-drag-zone" id="sheetDragZone">
        <div class="sheet-grip"></div>
        <div class="sheet-drag-hint">برای دیدن نقشه پایین بکشید</div>
    </div>

    <div class="nav-form" id="navForm">
        @if(count($popularRoutes))
        <div class="nav-popular" id="popularRoutes">
            @foreach($popularRoutes as $pr)
            <button type="button" class="nav-pr" data-route="{{ json_encode($pr, JSON_UNESCAPED_UNICODE) }}">{{ $pr['label'] }}</button>
            @endforeach
        </div>
        @endif

        <div class="nav-endpoints">
            <div class="nav-endpoints-fields">
                <div class="nav-row">
                    <span class="nav-dot origin"></span>
                    <input id="originLabel" placeholder="مبدا — جستجو یا انتخاب روی نقشه" value="تهران (پیش‌فرض)">
                    <button type="button" class="nav-mini" id="useMyLoc">من</button>
                </div>
                <div class="nav-row">
                    <span class="nav-dot dest"></span>
                    <input id="destLabel" placeholder="مقصد — جستجو یا انتخاب روی نقشه">
                </div>
            </div>
            <button type="button" class="nav-swap" id="swapEndpoints" title="جابجایی مبدا و مقصد" disabled aria-label="جابجایی مبدا و مقصد">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                    <path d="M7 4v16M7 20L3 16M7 4L3 8"/>
                    <path d="M17 20V4M17 4l4 4M17 20l4-4"/>
                </svg>
            </button>
        </div>
        <div class="nav-preview-hint" id="routePreviewHint" hidden></div>

        <div class="nav-grid">
            <label class="nav-field nav-field-full">
                <span>خودرو</span>
                <select id="vehicleSelect">
                    @foreach($userVehicles as $v)
                    <option value="user:{{ $v->uuid }}" @selected($defaultVehicle && $defaultVehicle->id === $v->id)>
                        {{ $v->brand }} {{ $v->model }} ({{ Persian::digits((int) $v->battery_kwh) }} کیلووات‌ساعت)
                    </option>
                    @endforeach
                    @foreach($vehiclePresets as $p)
                    <option value="preset:{{ $p['id'] }}" @selected($userVehicles->isEmpty() && $p['id'] === 'byd_atto3')>
                        {{ $p['name'] }}
                    </option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="nav-custom card card-p" id="customVehicle" hidden>
            <div class="nav-grid">
                <label class="nav-field"><span>ظرفیت باتری (کیلووات‌ساعت)</span><input type="number" id="customBattery" min="20" max="200" value="60" step="0.5"></label>
                <label class="nav-field"><span>مصرف (کیلووات‌ساعت در ۱۰۰ کیلومتر)</span><input type="number" id="customConsumption" min="8" max="40" value="16" step="0.1"></label>
            </div>
        </div>

        <div class="nav-soc-stack">
            <label class="nav-field">
                <span>درصد شارژ فعلی باتری</span>
                <div class="nav-soc-row">
                    <input type="range" id="socRange" min="10" max="100" value="80">
                    <output id="socOut">{{ Persian::digits(80) }}٪</output>
                </div>
            </label>
            <label class="nav-field">
                <span>درصد شارژ هدف در مقصد</span>
                <div class="nav-soc-row">
                    <input type="range" id="targetSocRange" min="10" max="100" value="20">
                    <output id="targetSocOut">{{ Persian::digits(20) }}٪</output>
                </div>
            </label>
        </div>

        <button type="button" class="btn btn-primary nav-analyze" id="analyzeBtn">
            <span data-ic="nav"></span> تحلیل مسیر و مصرف انرژی
        </button>
        <p class="fs11 muted center-col mt8" id="navHint" hidden></p>
    </div>

    <div class="nav-results" id="navResults" hidden>
        <div class="nav-verdict" id="navVerdict"></div>
        <div class="nav-summary card card-p" id="navSummary"></div>

        <div class="sec-title mt16">ایستگاه‌های شارژ در مسیر</div>
        <div id="chargerList" class="nav-plan"></div>

        <div class="sec-title mt16">برنامه شارژ پیشنهادی</div>
        <div id="chargePlan" class="nav-plan"></div>

        <details class="nav-details mt16">
            <summary class="sec-title">جزئیات بیشتر (نمودار و بخش‌های مسیر)</summary>
            <div class="sec-title mt8">وضعیت شارژ در مسیر</div>
            <div class="nav-soc-bar card card-p">
                <canvas id="socChart" height="56"></canvas>
                <div class="nav-soc-labels"><span>شروع</span><span>میانه</span><span>مقصد</span></div>
            </div>
            <div class="sec-title mt16">معنی رنگ‌ها روی نقشه</div>
            <div class="nav-colors card card-p">
                <span><i class="nc" style="background:#1D9E75"></i> مصرف کمتر از پایه (بازیابی)</span>
                <span><i class="nc" style="background:#378ADD"></i> مصرف نزدیک پایه</span>
                <span><i class="nc" style="background:#EF9F27"></i> مصرف بالاتر</span>
                <span><i class="nc" style="background:#E24B4A"></i> مصرف خیلی بالا</span>
            </div>
            <p class="fs11 muted mt4">رنگ مسیر از نسبت مصرف نهایی هر بخش به مصرف پایه خودرو (پایه + شیب خالص + دما + باد + تهویه) تعیین می‌شود.</p>
            <div class="sec-title mt16">نمودار ارتفاع و مصرف</div>
            <div class="nav-chart-wrap card card-p">
                <canvas id="elevChart" height="140"></canvas>
                <div class="nav-legend">
                    <span><i class="lg lg-elev"></i> ارتفاع (متر)</span>
                    <span><i class="lg lg-use"></i> مصرف نسبت به پایه</span>
                </div>
                <div class="nav-chart-note" id="chartDataNote"></div>
            </div>
            <div class="sec-title mt16">بخش‌های مسیر</div>
            <div id="segmentList" class="nav-segments"></div>
        </details>

        <button type="button" class="btn btn-soft mt16" id="navReset">مسیر جدید</button>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
.nav-map{position:absolute;inset:0;background:var(--surface-3);z-index:1}
.leaflet-container{font-family:inherit}
.nav-top{position:absolute;top:calc(12px + env(safe-area-inset-top,0));left:16px;right:16px;display:flex;align-items:center;gap:10px;z-index:20;background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:10px 12px;box-shadow:var(--shadow-md)}
.nav-back{display:grid;place-items:center;width:36px;height:36px;border-radius:12px;background:var(--surface-2);color:var(--text-2);text-decoration:none;transform:scaleX(-1)}
.nav-back svg{width:18px;height:18px}
.nav-title{flex:1;min-width:0}
.nav-loc,.nav-pin{position:absolute;z-index:20;width:46px;height:46px;border-radius:14px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-md);display:grid;place-items:center;cursor:pointer;font-family:inherit}
.nav-loc{right:18px;bottom:calc(var(--nav-sheet-h, 52vh) + 16px);color:var(--accent);transition:bottom .22s ease}
.nav-loc svg{width:21px;height:21px}
.nav-pin{right:18px;bottom:calc(var(--nav-sheet-h, 52vh) + 72px);font-size:18px;transition:bottom .22s ease}
.nav-origin{right:18px;bottom:calc(var(--nav-sheet-h, 52vh) + 128px);font-size:16px;color:var(--accent);transition:bottom .22s ease}
.nav-sheet{position:absolute;bottom:0;left:0;right:0;background:var(--surface);border-radius:26px 26px 0 0;box-shadow:var(--shadow-up);padding:0 18px calc(18px + env(safe-area-inset-bottom,0));height:var(--nav-sheet-h,52vh);max-height:var(--nav-sheet-h,52vh);overflow-x:hidden;overflow-y:auto;z-index:25;transition:height .22s ease,max-height .22s ease;box-sizing:border-box;max-width:100%}
.nav-sheet.collapsed .nav-form,.nav-sheet.collapsed .nav-results{visibility:hidden;opacity:0;pointer-events:none;height:0;overflow:hidden}
.sheet-drag-zone{touch-action:none;cursor:grab;padding:6px 0 10px;margin:0 -8px 6px;user-select:none}
.sheet-drag-zone:active{cursor:grabbing}
.sheet-drag-hint{font-size:10px;color:var(--muted);text-align:center;margin-top:6px}
.nav-sheet.collapsed .sheet-drag-hint::after{content:' · برای جزئیات بالا بکشید'}
.nav-sheet::-webkit-scrollbar{width:0}
.sheet-grip{width:42px;height:5px;border-radius:10px;background:var(--border);margin:4px auto 14px}
.nav-popular{display:flex;gap:8px;overflow-x:auto;margin-bottom:12px;padding-bottom:2px}
.nav-popular::-webkit-scrollbar{height:0}
.nav-pr{flex:none;border:1px solid var(--border);background:var(--surface-2);border-radius:20px;padding:7px 12px;font-size:11.5px;font-weight:700;font-family:inherit;cursor:pointer;color:var(--text-2)}
.nav-pr:hover{border-color:var(--accent);color:var(--accent)}
.nav-endpoints{display:flex;align-items:stretch;gap:8px;margin-bottom:4px}
.nav-endpoints-fields{flex:1;min-width:0}
.nav-swap{flex:none;width:44px;align-self:center;border:1px solid var(--border);background:var(--surface-2);border-radius:14px;color:var(--accent-strong);cursor:pointer;display:grid;place-items:center;padding:0;transition:background .15s,border-color .15s,opacity .15s}
.nav-swap:not(:disabled):hover{background:var(--accent-soft);border-color:var(--accent)}
.nav-swap:disabled{opacity:.35;cursor:not-allowed}
.nav-row{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.nav-dot{width:10px;height:10px;border-radius:50%;flex:none}
.nav-dot.origin{background:var(--accent)}
.nav-dot.dest{background:var(--green)}
.nav-row input{flex:1;border:1px solid var(--border);border-radius:14px;padding:12px 14px;font-family:inherit;font-size:14px;background:var(--surface-2);color:var(--text)}
.nav-mini{border:none;background:var(--accent-soft);color:var(--accent-strong);font-weight:700;font-size:12px;padding:8px 12px;border-radius:12px;cursor:pointer;font-family:inherit}
.nav-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:14px 0}
.nav-soc-stack{display:flex;flex-direction:column;gap:12px;margin:14px 0}
.nav-field-full{grid-column:1/-1}
.nav-field{display:block;min-width:0}
.nav-field span{display:block;font-size:12px;color:var(--text-2);margin-bottom:8px;font-weight:600;line-height:1.5}
.nav-field select,.nav-field input[type=number]{width:100%;border:1px solid var(--border);border-radius:12px;padding:10px;font-family:inherit;font-size:13px;background:var(--surface-2);box-sizing:border-box}
.nav-soc-row{display:flex;align-items:center;gap:10px;width:100%;min-width:0}
.nav-soc-row input[type=range]{flex:1;min-width:0;width:100%}
.nav-soc-row output{flex:none;font-size:14px;font-weight:800;min-width:44px;text-align:center;color:var(--accent-strong)}
.nav-form,.nav-results{max-width:100%;overflow-x:hidden}
.nav-custom{margin-bottom:4px;padding:12px!important}
.nav-custom .nav-grid{grid-template-columns:1fr}
.nav-analyze{width:100%;display:flex;align-items:center;justify-content:center;gap:8px}
.nav-analyze svg{width:18px;height:18px}
.nav-summary .ns-row{display:flex;justify-content:space-between;padding:6px 0;font-size:13px}
.nav-summary .ns-row strong{font-weight:800}
.nav-summary .ns-warn{margin-top:8px;padding:8px 10px;border-radius:10px;background:var(--orange-soft);color:var(--orange);font-size:12px;line-height:1.5}
.nav-verdict{border-radius:18px;padding:16px;margin-bottom:14px;border:2px solid transparent}
.nav-verdict.ok{background:var(--green-soft);border-color:var(--green)}
.nav-verdict.needs_charge{background:var(--orange-soft);border-color:var(--orange)}
.nav-verdict.no_chargers,.nav-verdict.critical{background:#fde8e8;border-color:#E24B4A}
.nav-verdict .nv-title{font-size:16px;font-weight:800;margin-bottom:6px;line-height:1.4}
.nav-verdict .nv-msg{font-size:13px;line-height:1.55;color:var(--text-2)}
.nav-verdict .nv-warn{margin-top:10px;padding:8px 10px;border-radius:10px;background:rgba(0,0,0,.06);font-size:12px;line-height:1.5}
.nav-verdict.ok .nv-title{color:var(--green)}
.nav-verdict.needs_charge .nv-title{color:var(--orange)}
.nav-verdict.no_chargers .nv-title,.nav-verdict.critical .nv-title{color:#E24B4A}
.nav-preview-hint{font-size:12px;color:var(--text-2);padding:8px 12px;border-radius:12px;background:var(--surface-2);margin-bottom:10px;line-height:1.55}
.nav-preview-hint.nav-preview-danger{background:#fde8e8;color:#B91C1C;border:1px solid #E24B4A;font-weight:600}
.nav-details summary{cursor:pointer;list-style:none}
.nav-details summary::-webkit-details-marker{display:none}
.nav-details[open] summary{margin-bottom:12px}
.nav-chart-wrap,.nav-soc-bar{padding:12px}
.nav-chart-note{margin-top:10px;padding:10px 12px;border-radius:12px;background:var(--surface-2);font-size:11px;line-height:1.6;color:var(--text-2)}
.nav-chart-note .ds-row{display:flex;gap:8px;align-items:flex-start;margin-top:4px}
.nav-chart-note .ds-tag{flex:none;font-size:10px;font-weight:700;padding:2px 6px;border-radius:6px}
.nav-chart-note .ds-tag.real{background:var(--green-soft);color:var(--green)}
.nav-chart-note .ds-tag.estimated{background:var(--orange-soft);color:var(--orange)}
.nav-chart-note .ds-tag.fallback{background:#fde8e8;color:#E24B4A}
.nav-chart-wrap canvas,.nav-soc-bar canvas{display:block;width:100%;height:auto}
.nav-soc-labels{display:flex;justify-content:space-between;font-size:10px;color:var(--muted);margin-top:4px}
.nav-legend{display:flex;gap:16px;justify-content:center;margin-top:8px;font-size:11px;color:var(--muted)}
.nav-colors{display:flex;flex-wrap:wrap;gap:10px 14px;font-size:11px;color:var(--muted);padding:12px}
.nc{display:inline-block;width:12px;height:12px;border-radius:3px;margin-left:4px;vertical-align:middle}
.lg{display:inline-block;width:12px;height:12px;border-radius:3px;margin-left:4px;vertical-align:middle}
.lg-elev{background:var(--accent)}
.lg-use{background:var(--orange)}
.nav-plan{display:flex;flex-direction:column;gap:10px}
.nav-plan-item{display:flex;gap:12px;align-items:center;padding:12px;border-radius:16px;background:var(--surface-2);border:1px solid var(--border-2);text-decoration:none;color:inherit}
.nav-plan-item .pi{width:44px;height:44px;border-radius:12px;background:var(--green-soft);color:var(--green);display:grid;place-items:center;flex:none}
.nav-segments{display:flex;flex-direction:column;gap:8px;max-height:220px;overflow-y:auto}
.nav-seg{display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:14px;background:var(--surface-2);border:1px solid var(--border-2);font-size:12px}
.nav-seg .bar{width:4px;height:32px;border-radius:4px;flex:none}
.nav-loading{opacity:.6;pointer-events:none}
.sc-route-seg{stroke-linecap:round;stroke-linejoin:round}
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
window.SC_NAV = {
    analyzeUrl: @json(route('app.api.navigator.analyze')),
    previewUrl: @json(route('app.api.navigator.preview')),
    mapCenter: @json($mapCenter),
    csrf: @json(csrf_token()),
};
</script>
<script src="/assets/mobile/ev-navigator.js"></script>
@endpush
