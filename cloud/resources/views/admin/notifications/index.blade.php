@extends('admin.layouts.app', ['page' => 'notifications', 'title' => 'سیستم اعلان‌ها', 'crumb' => 'سامانه و ارتباطات'])

@section('content')
@php
  use App\Support\Persian;
  $channelStats = \App\Models\UserNotification::whereDate('sent_at', today())
    ->selectRaw('channel, COUNT(*) as c')
    ->groupBy('channel')
    ->get();
  $todayTotal = max(1, $channelStats->sum('c'));
  $channelColors = ['push' => 'var(--accent)', 'in_app' => 'var(--accent)', 'sms' => 'var(--green)', 'email' => 'var(--violet)'];
  $donutData = $channelStats->isEmpty()
    ? [['v' => 100, 'c' => 'var(--accent)']]
    : $channelStats->map(fn ($ch) => [
        'v' => (int) round($ch->c / $todayTotal * 100),
        'c' => $channelColors[$ch->channel] ?? 'var(--orange)',
      ])->values()->all();
  $channelNames = ['push' => 'پوش', 'in_app' => 'درون‌برنامه‌ای', 'sms' => 'پیامک', 'email' => 'ایمیل', 'whatsapp' => 'واتس‌اپ'];
  $typeStats = \App\Models\UserNotification::where('sent_at', '>=', now()->subDays(30))
    ->selectRaw('type, COUNT(*) as c, SUM(read_at IS NOT NULL) as r, GROUP_CONCAT(DISTINCT channel) as chs, MAX(title) as title')
    ->groupBy('type')->orderByDesc('c')->get();
  $typeCount = \App\Models\UserNotification::distinct()->count('type');
@endphp
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="muted fs12">آمار کانال‌ها مربوط به امروز و قالب‌ها مربوط به ۳۰ روز اخیر است.</div>
      <a href="/admin/notifications/create" class="btn btn-sm btn-primary"><span class="bic" data-ic="plus"></span> ارسال اعلان جدید</a>
    </div>

    <div class="kpi-grid">
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="bell"></div><div><div class="ms-v tnum">{{ Persian::num($stats['today'] ?? 0) }}</div><div class="ms-l">ارسال امروز</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="activity"></div><div><div class="ms-v tnum">{{ Persian::num($notifications->total()) }}</div><div class="ms-l">کل اعلان‌ها</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="bolt"></div><div><div class="ms-v tnum">{{ Persian::num($stats['unread'] ?? 0) }}</div><div class="ms-l">خوانده‌نشده</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="sliders"></div><div><div class="ms-v tnum">{{ Persian::num($typeCount) }}</div><div class="ms-l">نوع رویداد</div></div></div>
    </div>

    <div class="grid col-2-1 mt-16">
      <!-- کانال‌ها -->
      <div class="card">
        <div class="card-head"><div><h3>کانال‌های ارسال</h3><div class="ch-sub">پیکربندی درگاه‌های اعلان</div></div></div>
        <div class="card-pad">
          <div class="grid g-2" id="channels">
            @php
              $channelMeta = [
                'push' => ['n' => 'اعلان پوش', 'ic' => 'bell', 'c' => 'ic-blue'],
                'in_app' => ['n' => 'درون‌برنامه‌ای', 'ic' => 'bell', 'c' => 'ic-blue'],
                'sms' => ['n' => 'پیامک (SMS)', 'ic' => 'activity', 'c' => 'ic-green'],
                'email' => ['n' => 'ایمیل', 'ic' => 'receipt', 'c' => 'ic-violet'],
              ];
            @endphp
            @forelse($channelStats as $ch)
              @php $m = $channelMeta[$ch->channel] ?? ['n' => $ch->channel, 'ic' => 'bell', 'c' => 'ic-blue']; @endphp
              <div class="chan">
                <div class="chan-top"><div class="chan-ic {{ $m['c'] }}"><span data-ic="{{ $m['ic'] }}"></span></div>
                  <div><div class="fw7">{{ $m['n'] }}</div><div class="fs12 muted">امروز</div></div>
                  <span class="switch on"><i></i></span></div>
                <div class="chan-stat"><div><div class="v tnum">{{ Persian::digits($ch->c) }}</div><div class="l">ارسال امروز</div></div>
                  <div><div class="v tnum">{{ Persian::digits((int) round($ch->c / $todayTotal * 100)) }}٪</div><div class="l">سهم</div></div></div>
              </div>
            @empty
              <div class="cell-muted">امروز اعلانی ارسال نشده است.</div>
            @endforelse
          </div>
        </div>
      </div>

      <!-- توزیع کانال -->
      <div class="card">
        <div class="card-head"><h3>سهم کانال‌ها</h3></div>
        <div class="card-pad">
          <div class="flex items-center gap-16">
            <svg width="128" height="128" viewBox="0 0 128 128" id="donut"></svg>
            <div class="legend">
              @forelse($channelStats as $ch)
                <div class="li"><span class="sw" style="background:{{ $channelColors[$ch->channel] ?? 'var(--orange)' }}"></span><span>{{ $channelNames[$ch->channel] ?? $ch->channel }}</span><b class="tnum" style="margin-inline-start:auto">{{ Persian::digits((int) round($ch->c / $todayTotal * 100)) }}٪</b></div>
              @empty
                <div class="li cell-muted">امروز ارسالی نبوده است.</div>
              @endforelse
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- قالب‌ها -->
    <div class="card mt-16">
      <div class="card-head"><div><h3>قالب‌های رویداد</h3><div class="ch-sub">اعلان‌های خودکار بر اساس رویدادهای سامانه</div></div></div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>رویداد</th><th>نمونهٔ عنوان</th><th>کانال‌ها</th><th>ارسال (۳۰ روز)</th><th>نرخ خوانده‌شدن</th></tr></thead>
          <tbody id="tplBody"></tbody>
        </table>
      </div>
    </div>

    <!-- گزارش ارسال -->
    <div class="card mt-16">
      <div class="card-head"><div><h3>گزارش ارسال‌های اخیر</h3></div><div class="head-actions"><div class="seg" id="logSeg"><button class="active">همه</button><button>پوش</button><button>پیامک</button></div></div></div>
      <div class="card-pad" style="padding-top:6px" id="log">
        @forelse($notifications as $n)
          <div class="nrow" data-ch="{{ $n->channel }}">
            <div class="ni ic-blue">{{-- icon via JS below --}}</div>
            <div class="l-main">
              <div class="l-title">{{ $n->title }} — {{ $n->user?->name ?? 'کاربر' }}</div>
              <div class="l-sub">{{ $n->channel }} · {{ $n->sent_at?->diffForHumans() }}</div>
            </div>
            <span class="pill {{ $n->read_at ? 'pill-green' : 'pill-amber' }}">{{ $n->read_at ? 'خوانده‌شده' : 'ارسال‌شده' }}</span>
          </div>
        @empty
          <div class="cell-muted">اعلانی ثبت نشده است.</div>
        @endforelse
      </div>
    </div>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm)}
.ms-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:22px;height:22px}
.ms-v{font-size:22px;font-weight:800}.ms-l{font-size:12px;color:var(--muted)}

.chan{border:1px solid var(--border);border-radius:14px;padding:15px}
.chan-top{display:flex;align-items:center;gap:11px}
.chan-ic{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;flex:none}.chan-ic svg{width:21px;height:21px}
.chan-stat{display:flex;gap:16px;margin-top:13px;padding-top:12px;border-top:1px solid var(--border-2)}
.chan-stat .v{font-size:16px;font-weight:800}.chan-stat .l{font-size:11px;color:var(--muted)}
.switch{width:42px;height:24px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;cursor:pointer;margin-inline-start:auto;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:18px;height:18px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .15s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-18px)}

.nrow{display:flex;align-items:center;gap:13px;padding:12px 0;border-bottom:1px solid var(--border-2)}
.nrow:last-child{border-bottom:none}
.nrow .ni{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:none}.nrow .ni svg{width:18px;height:18px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  document.querySelectorAll('#channels [data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  // دونات (از آمار کانال امروز)
  (function(){
    var data=@json($donutData);
    var circ=2*Math.PI*52,off=0;
    var rings=data.map(function(d){var len=d.v/100*circ;var el='<circle cx="64" cy="64" r="52" fill="none" stroke="'+d.c+'" stroke-width="16" stroke-dasharray="'+len+' '+(circ-len)+'" stroke-dashoffset="'+(-off)+'" transform="rotate(-90 64 64)"/>';off+=len;return el;}).join('');
    document.getElementById('donut').innerHTML=rings+'<text x="64" y="60" text-anchor="middle" font-size="20" font-weight="800" fill="var(--text)" font-family="Vazirmatn">{{ Persian::digits($stats['today'] ?? 0) }}</text><text x="64" y="78" text-anchor="middle" font-size="10" fill="var(--muted)" font-family="Vazirmatn">ارسال امروز</text>';
  })();

  var tpls=@json($typeStats->map(fn ($t) => [
    (string) ($t->type ?: '—'),
    (string) ($t->title ?? ''),
    collect(explode(',', (string) $t->chs))->filter()->map(fn ($c) => $channelNames[$c] ?? $c)->values()->all(),
    (int) $t->c,
    $t->c > 0 ? round($t->r / $t->c * 100, 1) : 0,
  ])->values());
  var esc=AdminUI.esc;
  document.getElementById('tplBody').innerHTML=tpls.length?tpls.map(function(t){
    var chips=t[2].map(function(c){return '<span class="tag">'+esc(c)+'</span>';}).join(' ');
    return '<tr><td class="cell-strong">'+esc(t[0])+'</td><td class="cell-muted">'+esc(t[1])+'</td>'+
      '<td><div class="flex gap-8">'+chips+'</div></td><td class="tnum">'+AdminUI.fa(t[3])+'</td><td class="tnum">'+AdminUI.fa(t[4],1)+'٪</td></tr>';
  }).join(''):'<tr><td colspan="5" class="cell-muted">در ۳۰ روز اخیر اعلانی ارسال نشده است.</td></tr>';

  document.querySelectorAll('#log .ni').forEach(function(el){el.innerHTML=window.icon('bell');});

  document.querySelectorAll('.switch').forEach(function(s){s.addEventListener('click',function(){s.classList.toggle('on');});});
  document.querySelectorAll('#logSeg button').forEach(function(b,idx){
    b.addEventListener('click',function(){document.querySelectorAll('#logSeg button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');
      document.querySelectorAll('.nrow').forEach(function(r){var ch=r.getAttribute('data-ch');var show=idx===0||(idx===1&&ch==='push')||(idx===2&&ch==='sms');r.style.display=show?'':'none';});});
  });
})();
</script>
@endpush
