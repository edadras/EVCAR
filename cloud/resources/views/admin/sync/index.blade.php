@extends('admin.layouts.app', ['page' => 'sync', 'title' => 'همگام‌سازی و حالت آفلاین', 'crumb' => 'سامانه و ارتباطات'])

@section('content')
@php use App\Support\Persian; use Illuminate\Support\Str; @endphp
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <span class="pill pill-green"><span class="pdot"></span>موتور همگام‌سازی فعال · رویدادمحور</span>
      <div class="flex gap-8">
        <button class="btn btn-sm"><span class="bic" data-ic="refresh"></span> همگام‌سازی دستی</button>
        <a href="/admin/sync/settings" class="btn btn-sm btn-primary">تنظیمات Sync</a>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="bolt"></div><div><div class="ms-v tnum">{{ Persian::num($stats['online'] ?? 0) }}</div><div class="ms-l">ایستگاه آنلاین</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-amber" data-ic="refresh"></div><div><div class="ms-v tnum">{{ Persian::num(($stats['total'] ?? 0) - ($stats['online'] ?? 0)) }}</div><div class="ms-l">ایستگاه آفلاین</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="activity"></div><div><div class="ms-v tnum">{{ Persian::num($stats['pending'] ?? 0) }}</div><div class="ms-l">رویداد در صف همگام‌سازی</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="clock"></div><div><div class="ms-v tnum">{{ Persian::num($stats['total'] ?? 0) }}</div><div class="ms-l">کل کنترلر Edge</div></div></div>
    </div>

    <!-- دیاگرام جریان -->
    <div class="card mt-16">
      <div class="card-head"><div><h3>جریان همگام‌سازی رویدادمحور</h3><div class="ch-sub">Edge Station Controller → Cloud Platform (CSMS)</div></div></div>
      <div class="card-pad">
        <div class="flowd">
          <div class="fnode">
            <div class="fn-ic ic-blue" data-ic="station"></div>
            <div class="fn-t">کنترلر لبه</div>
            <div class="fn-s">Mini PC · Local DB<br>OCPP Gateway · GSM</div>
          </div>
          <div class="farrow"><span>صف محلی</span><div class="fline"><i></i></div></div>
          <div class="fnode">
            <div class="fn-ic ic-amber" data-ic="activity"></div>
            <div class="fn-t">صف رویداد محلی</div>
            <div class="fn-s">Local Queue<br>ذخیره در زمان قطعی</div>
          </div>
          <div class="farrow"><span>اینترنت</span><div class="fline"><i></i></div></div>
          <div class="fnode">
            <div class="fn-ic ic-green" data-ic="refresh"></div>
            <div class="fn-t">صف ابری</div>
            <div class="fn-s">Cloud Queue<br>پردازش و ادغام</div>
          </div>
          <div class="farrow"><span>ثبت نهایی</span><div class="fline"><i></i></div></div>
          <div class="fnode">
            <div class="fn-ic ic-violet" data-ic="grid"></div>
            <div class="fn-t">پلتفرم مرکزی</div>
            <div class="fn-s">CSMS<br>پایگاه‌دادهٔ ابری</div>
          </div>
        </div>
      </div>
    </div>

    <div class="grid col-2-1 mt-16">
      <!-- کنترلرهای لبه -->
      <div class="card">
        <div class="card-head"><div><h3>کنترلرهای ایستگاه (Edge)</h3><div class="ch-sub">وضعیت اتصال و صف همگام‌سازی هر ایستگاه</div></div></div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>ایستگاه</th><th>کنترلر</th><th>حالت</th><th>پایگاه محلی</th><th>رویداد در انتظار</th><th>آخرین Sync</th></tr></thead>
            <tbody id="edgeBody">
              @foreach($edges as $edge)
                @php
                  $online = $edge->sync_status === 'online';
                  $pill = $online ? 'pill-green' : ($edge->sync_status === 'syncing' ? 'pill-accent' : 'pill-amber');
                  $label = $online ? 'آنلاین' : ($edge->sync_status === 'syncing' ? 'همگام‌سازی' : 'آفلاین');
                @endphp
                <tr>
                  <td class="cell-strong">{{ $edge->station?->name ?? '—' }}</td>
                  <td class="mono">{{ $edge->edge_code }}</td>
                  <td><span class="pill {{ $pill }}">@if($online)<span class="pdot"></span>@endif{{ $label }}</span></td>
                  <td class="tnum cell-muted">—</td>
                  <td class="tnum cell-muted">{{ Persian::num(\App\Models\SyncInbox::where('edge_station_uuid', $edge->uuid)->where('status', 'pending')->count()) }}</td>
                  <td class="cell-muted">{{ $edge->last_sync_at?->diffForHumans() ?? '—' }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>

      <!-- صف رویدادها -->
      <div class="card">
        <div class="card-head"><h3>صف رویدادها بر اساس نوع</h3></div>
        <div class="card-pad" style="padding-top:8px" id="queue">
          @php $queueGroups = \App\Models\SyncInbox::where('status', 'pending')->selectRaw('event_type, COUNT(*) as c')->groupBy('event_type')->orderByDesc('c')->get(); @endphp
          @forelse($queueGroups as $qg)
            <div class="qrow">
              <div class="qi ic-blue">{{ substr($qg->event_type, 0, 1) }}</div>
              <div class="l-main"><div class="l-title">{{ $qg->event_type }}</div><div class="l-sub mono">pending</div></div>
              <span class="qbadge">{{ Persian::num($qg->c) }}</span>
            </div>
          @empty
            <div class="cell-muted">صف خالی است.</div>
          @endforelse
        </div>
      </div>
    </div>

    <!-- ثبت‌نام آفلاین -->
    <div class="card mt-16">
      <div class="card-head">
        <div><h3>ثبت‌نام‌های محلی در انتظار همگام‌سازی</h3><div class="ch-sub">کاربرانی که آفلاین (با WiFi ایستگاه و OTP از مودم GSM) ثبت‌نام کرده‌اند</div></div>
        <div class="head-actions"><span class="pill pill-amber"><span class="pdot"></span>{{ Persian::digits(\App\Models\SyncInbox::where('event_type', 'like', '%user%')->where('status', 'pending')->count()) }} مورد در انتظار</span></div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>شناسهٔ محلی</th><th>موبایل</th><th>ایستگاه ثبت</th><th>روش OTP</th><th>تراکنش معلق</th><th>زمان ثبت</th><th>وضعیت Sync</th></tr></thead>
            <tbody id="pendBody">
              @foreach(\App\Models\SyncInbox::where('event_type', 'like', '%user%')->where('status', 'pending')->latest('received_at')->limit(10)->get() as $ev)
                <tr>
                  <td class="mono">{{ Str::limit($ev->event_uuid, 12) }}</td>
                  <td class="mono cell-muted">—</td>
                  <td class="cell-strong">{{ $ev->aggregate_type ?? '—' }}</td>
                  <td><span class="tag">Sync</span></td>
                  <td class="tnum">—</td>
                  <td class="cell-muted">{{ $ev->received_at?->diffForHumans() }}</td>
                  <td><span class="pill pill-amber">در انتظار</span></td>
                </tr>
              @endforeach
            </tbody>
        </table>
      </div>
    </div>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm)}
.ms-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:22px;height:22px}
.ms-v{font-size:22px;font-weight:800}.ms-v small{font-size:12px;font-weight:600;color:var(--text-2)}.ms-l{font-size:12px;color:var(--muted)}
.ic-amber{background:var(--amber-soft);color:var(--amber)}

.flowd{display:flex;align-items:stretch;gap:0;overflow-x:auto;padding:6px 0}
.fnode{flex:none;width:150px;text-align:center;background:var(--surface-2);border:1px solid var(--border);border-radius:16px;padding:16px 12px}
.fn-ic{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;margin:0 auto 10px}.fn-ic svg{width:24px;height:24px}
.fn-t{font-weight:700;font-size:13.5px}.fn-s{font-size:11px;color:var(--muted);margin-top:5px;line-height:1.7}
.farrow{flex:1;min-width:70px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px}
.farrow span{font-size:11px;color:var(--muted);font-weight:600}
.fline{width:100%;height:2px;background:var(--border);position:relative;border-radius:2px}
.fline i{position:absolute;top:-3px;width:8px;height:8px;border-radius:50%;background:var(--accent);animation:travel 2.2s linear infinite}
html[dir=rtl] .fline i{right:0;animation-name:travelR}
@keyframes travelR{from{right:0}to{right:100%}}

.qrow{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-2)}
.qrow:last-child{border-bottom:none}
.qrow .qi{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;flex:none;font-weight:800;font-size:13px}
.qbadge{margin-inline-start:auto;font-weight:800;font-variant-numeric:tabular-nums}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

})();
</script>
@endpush
