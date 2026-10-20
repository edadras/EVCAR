@extends('admin.layouts.app', ['page' => 'reviews', 'title' => 'امتیاز و نظرات مشتریان', 'crumb' => 'مرکز پشتیبانی'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="kpi-grid mt-8">
    <div class="mini-stat"><div><div class="ms-v tnum">{{ $stats['count'] ? Persian::digits(number_format($stats['avg'], 1)) : '—' }} <small>از ۵</small></div><div class="ms-l">میانگین امتیاز شبکه</div></div></div>
    <div class="mini-stat"><div><div class="ms-v tnum">{{ Persian::digits($stats['count']) }}</div><div class="ms-l">کل نظرات</div></div></div>
    <div class="mini-stat"><div><div class="ms-v tnum">{{ Persian::digits($stats['week']) }}</div><div class="ms-l">نظر در ۷ روز</div></div></div>
    <div class="mini-stat"><div><div class="ms-v tnum" style="color:var(--red)">{{ Persian::digits($stats['low']) }}</div><div class="ms-l">امتیاز ۱ و ۲ در ۳۰ روز</div></div></div>
</div>

<div class="grid col-2-1 mt-16">
    <div class="card">
        <div class="card-head">
            <h3>نظرات</h3>
            <div class="head-actions" style="gap:6px">
                @foreach(['all' => 'همه', 'low' => 'امتیاز پایین', 'unanswered' => 'بی‌پاسخ', 'hidden' => 'پنهان'] as $k => $l)
                    <a class="btn btn-sm {{ $filter === $k ? 'btn-primary' : '' }}" href="{{ route('admin.support.reviews', array_filter(['filter' => $k === 'all' ? null : $k, 'station' => request('station')])) }}">{{ $l }}</a>
                @endforeach
            </div>
        </div>
        <div class="card-pad" style="display:flex;flex-direction:column;gap:14px">
            @forelse($reviews as $rv)
                <div class="rv {{ $rv->is_hidden ? 'hidden-rv' : '' }}">
                    <div class="flex between items-center" style="gap:10px;flex-wrap:wrap">
                        <div><b>{{ $rv->user?->name ?? '—' }}</b> <span class="muted fs12 tnum">{{ Persian::digits($rv->user?->phone ?? '') }}</span> ·
                            <a href="{{ route('admin.support.reviews', ['station' => $rv->station?->code]) }}">{{ $rv->station?->name }}</a></div>
                        <div class="stars">@for($i = 1; $i <= 5; $i++)<span class="{{ $i <= $rv->rating ? 'on' : '' }}">★</span>@endfor</div>
                    </div>
                    @if($rv->comment)<div class="mt-8" style="line-height:1.9">{{ $rv->comment }}</div>@endif
                    @if($rv->tags)<div class="mt-8" style="display:flex;gap:6px;flex-wrap:wrap">@foreach($rv->tags as $t)<span class="tag">{{ $tags[$t] ?? $t }}</span>@endforeach</div>@endif
                    <div class="muted fs12 mt-8">{{ Persian::digits($rv->created_at?->format('Y/m/d H:i') ?? '') }}@if($rv->session) · {{ Persian::kwh($rv->session->energy_kwh, 2) }} kWh · {{ Persian::money((int) round($rv->session->cost_amount / 10)) }} ت@endif</div>
                    <form method="POST" action="{{ route('admin.support.reviews.update', $rv) }}" class="mt-8" style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap">@csrf
                        <textarea class="finput" name="reply" rows="2" maxlength="600" placeholder="پاسخ عمومی SafirCharge…" style="flex:1;min-width:220px">{{ $rv->reply }}</textarea>
                        <div style="display:flex;flex-direction:column;gap:6px">
                            <button class="btn btn-sm btn-primary" type="submit">{{ $rv->reply ? 'ویرایش پاسخ' : 'ارسال پاسخ' }}</button>
                            <button class="btn btn-sm" type="submit" name="is_hidden" value="{{ $rv->is_hidden ? 0 : 1 }}">{{ $rv->is_hidden ? 'نمایش' : 'پنهان کردن' }}</button>
                        </div>
                    </form>
                </div>
            @empty
                <div class="muted" style="text-align:center;padding:24px">نظری با این فیلتر وجود ندارد.</div>
            @endforelse
        </div>
        @if($reviews->hasPages())<div class="card-pad">{{ $reviews->links() }}</div>@endif
    </div>

    <div class="card">
        <div class="card-head"><div><h3>ایستگاه‌ها بر اساس امتیاز</h3><div class="ch-sub">کم‌امتیازترین‌ها در اولویت بهره‌برداری</div></div></div>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>ایستگاه</th><th>امتیاز</th><th>نظر</th></tr></thead>
                <tbody>
                @forelse($stations as $s)
                    <tr>
                        <td class="cell-strong"><a href="{{ route('admin.support.reviews', ['station' => $s['station']->code]) }}">{{ $s['station']->name }}</a></td>
                        <td><span class="pill {{ ($s['rating'] ?? 5) < 3 ? 'pill-red' : (($s['rating'] ?? 5) < 4 ? 'pill-amber' : 'pill-green') }} tnum">{{ $s['rating'] !== null ? Persian::digits(number_format($s['rating'], 1)) : '—' }}</span></td>
                        <td class="tnum">{{ Persian::digits($s['reviews']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="muted" style="text-align:center;padding:20px">هنوز امتیازی ثبت نشده.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;box-shadow:var(--shadow-sm)}
.ms-v{font-size:22px;font-weight:800}.ms-v small{font-size:12px;color:var(--muted);font-weight:600}.ms-l{font-size:12px;color:var(--muted)}
.rv{border:1px solid var(--border);border-radius:14px;padding:14px}
.hidden-rv{opacity:.55;border-style:dashed}
.stars span{color:var(--border);font-size:16px}.stars span.on{color:#F59E0B}
</style>
@endpush
