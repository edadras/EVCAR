@extends('admin.layouts.app', ['page' => 'support', 'title' => 'مرکز پشتیبانی مشتریان', 'crumb' => 'عملیات'])

@section('content')
@php
    use App\Support\Persian;
    $pri = fn ($p) => match ($p) { 'critical' => ['بحرانی', 'pill-red'], 'high' => ['بالا', 'pill-red'], 'low' => ['پایین', 'pill'], default => ['عادی', 'pill'] };
    $st = fn ($s) => match ($s) { 'open' => ['باز', 'pill-orange'], 'in_progress' => ['در حال بررسی', 'pill-accent'], 'resolved' => ['حل‌شده', 'pill-green'], default => ['بسته', 'pill'] };
    $q = fn (array $over) => route('admin.support', array_filter(array_merge($filters, $over), fn ($v) => $v !== null && $v !== ''));
@endphp

<div class="kpi-grid mt-8">
    <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="headset"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['open']) }}</div><div class="ms-l">تیکت باز</div></div></div>
    <div class="mini-stat"><div class="ms-ic ic-red" data-ic="alert"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['unanswered']) }}</div><div class="ms-l">بدون پاسخ · {{ Persian::digits($stats['high']) }} اولویت بالا</div></div></div>
    <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="clock"></div><div><div class="ms-v tnum">{{ $stats['avg_first_response_min'] === null ? '—' : Persian::digits($stats['avg_first_response_min']) }} <small>دقیقه</small></div><div class="ms-l">میانگین اولین پاسخ (۳۰ روز)</div></div></div>
    <div class="mini-stat"><div class="ms-ic ic-green" data-ic="activity"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['resolved_week']) }}</div><div class="ms-l">حل‌شده در ۷ روز</div></div></div>
</div>

<div class="grid col-2-1 mt-16">
    <div class="card">
        <div class="card-head">
            <div><h3>تیکت‌های پشتیبانی</h3><div class="ch-sub">مرتب‌شده بر اساس اولویت و آخرین به‌روزرسانی</div></div>
            <form class="head-actions" method="GET" action="{{ route('admin.support') }}" style="gap:8px">
                @foreach(['status', 'priority', 'source', 'mine'] as $k)
                    @if(! empty($filters[$k]))<input type="hidden" name="{{ $k }}" value="{{ $filters[$k] }}">@endif
                @endforeach
                <div class="search" style="max-width:240px"><input name="q" value="{{ $filters['q'] ?? '' }}" placeholder="شماره، موضوع، نام یا موبایل…" autocomplete="off"></div>
            </form>
        </div>
        <div class="card-pad" style="padding-top:0;display:flex;flex-wrap:wrap;gap:6px">
            @foreach(['active' => 'فعال', 'open' => 'باز', 'in_progress' => 'در حال بررسی', 'resolved' => 'حل‌شده', 'closed' => 'بسته', 'all' => 'همه'] as $k => $l)
                <a href="{{ $q(['status' => $k, 'page' => null]) }}" class="chip-f {{ $filters['status'] === $k ? 'on' : '' }}">{{ $l }}</a>
            @endforeach
            <span class="sep-v"></span>
            <a href="{{ $q(['priority' => ($filters['priority'] ?? null) === 'high' ? null : 'high', 'page' => null]) }}" class="chip-f {{ ($filters['priority'] ?? null) === 'high' ? 'on' : '' }}">اولویت بالا</a>
            <a href="{{ $q(['source' => ($filters['source'] ?? null) === 'ai' ? null : 'ai', 'page' => null]) }}" class="chip-f {{ ($filters['source'] ?? null) === 'ai' ? 'on' : '' }}">ارجاع از دستیار هوشمند</a>
            <a href="{{ $q(['mine' => empty($filters['mine']) ? 1 : null, 'page' => null]) }}" class="chip-f {{ ! empty($filters['mine']) ? 'on' : '' }}">ارجاع‌شده به من</a>
        </div>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>شماره</th><th>مشتری</th><th>موضوع</th><th>دسته</th><th>منبع</th><th>کارشناس</th><th>به‌روزرسانی</th><th>اولویت</th><th>وضعیت</th></tr></thead>
                <tbody>
                @forelse($tickets as $t)
                    <tr class="row-link" onclick="location.href='{{ route('admin.support.ticket', $t) }}'">
                        <td class="mono"><a href="{{ route('admin.support.ticket', $t) }}">{{ $t->ticket_number }}</a>@if(! $t->first_response_at && in_array($t->status, ['open', 'in_progress'], true)) <span class="dot-new" title="بدون پاسخ"></span>@endif</td>
                        <td class="cell-strong">{{ $t->user?->name ?? '—' }}<div class="cell-muted fs12 tnum">{{ Persian::digits($t->user?->phone ?? '') }}</div></td>
                        <td>{{ \Illuminate\Support\Str::limit($t->subject, 48) }}<div class="cell-muted fs12">{{ $t->station?->name }}</div></td>
                        <td><span class="tag">{{ $labels['categories'][$t->category] ?? $t->category }}</span></td>
                        <td class="cell-muted">{{ $labels['sources'][$t->source] ?? $t->source }}</td>
                        <td class="cell-muted">{{ $t->assignee?->name ?? 'تعیین نشده' }}</td>
                        <td class="cell-muted">{{ Persian::digits($t->updated_at?->locale('fa')->diffForHumans() ?? '—') }}</td>
                        <td><span class="pill {{ $pri($t->priority)[1] }}">{{ $pri($t->priority)[0] }}</span></td>
                        <td><span class="pill {{ $st($t->status)[1] }}">{{ $st($t->status)[0] }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="muted" style="text-align:center;padding:28px">تیکتی با این فیلتر وجود ندارد.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($tickets->hasPages())<div class="card-pad">{{ $tickets->links() }}</div>@endif
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
        <a class="card ai-card" href="{{ route('admin.support.ai') }}">
            <div class="card-pad">
                <div class="flex between items-center"><h3 style="margin:0">✦ دستیار هوشمند پشتیبانی</h3><span class="pill pill-accent">۷ روز</span></div>
                <div class="grid g-3 mt-16" style="text-align:center">
                    <div><div class="ms-v tnum">{{ Persian::digits($ai['week']) }}</div><div class="ms-l">گفتگو</div></div>
                    <div><div class="ms-v tnum">{{ Persian::digits($ai['escalated']) }}</div><div class="ms-l">ارجاع به کارشناس</div></div>
                    <div><div class="ms-v tnum">{{ $ai['self_served_pct'] === null ? '—' : Persian::digits($ai['self_served_pct']).'٪' }}</div><div class="ms-l">حل بدون کارشناس</div></div>
                </div>
                <div class="muted fs12 mt-8">مشاهدهٔ گفتگوها و تنظیم دستورالعمل دستیار ←</div>
            </div>
        </a>

        <div class="card">
            <div class="card-head"><div><h3>خطاهای فعال شارژرها</h3><div class="ch-sub">از StatusNotification و قطع ارتباط OCPP</div></div></div>
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>شارژر</th><th>خطا</th><th>تیکت</th><th></th></tr></thead>
                    <tbody>
                    @forelse($faults as $f)
                        <tr>
                            <td class="mono cell-strong">{{ $f['charger']->charge_point_id }}<div class="cell-muted fs12">{{ $f['charger']->station?->name }}</div></td>
                            <td><span class="pill {{ $f['critical'] ? 'pill-red' : 'pill-amber' }}">{{ $f['code'] }}</span></td>
                            <td class="tnum">{{ Persian::digits($f['open_tickets']) }}</td>
                            <td><a href="{{ route('admin.chargers.show', $f['charger']) }}" class="btn btn-sm">رسیدگی</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted" style="text-align:center;padding:20px">همهٔ شارژرها سالم و آنلاین‌اند.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm)}
.ms-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:22px;height:22px}
.ms-v{font-size:22px;font-weight:800}.ms-v small{font-size:12px;font-weight:600;color:var(--text-2)}.ms-l{font-size:12px;color:var(--muted)}
.ic-red{background:var(--red-soft);color:var(--red)}
.chip-f{padding:6px 12px;border-radius:20px;border:1px solid var(--border);font-size:12.5px;font-weight:600;color:var(--text-2);text-decoration:none;background:var(--surface)}
.chip-f.on{background:var(--accent);border-color:var(--accent);color:#fff}
.sep-v{width:1px;background:var(--border);margin:0 4px}
.row-link{cursor:pointer}.row-link:hover td{background:var(--surface-2)}
.dot-new{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--orange);margin-inline-start:4px}
.ai-card{text-decoration:none;color:inherit;background:linear-gradient(135deg,color-mix(in srgb,var(--accent) 10%,var(--surface)),var(--surface))}
</style>
@endpush

@push('scripts')
<script>document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});</script>
@endpush
