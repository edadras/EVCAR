@extends('admin.layouts.app', ['page' => 'support', 'title' => 'دستیار هوشمند پشتیبانی', 'crumb' => 'مرکز پشتیبانی'])

@section('content')
@php
    use App\Support\Persian;
    $st = fn ($s) => match ($s) { 'escalated' => ['ارجاع به کارشناس', 'pill-orange'], 'closed' => ['بسته', 'pill'], default => ['فعال', 'pill-green'] };
@endphp
<div class="flex between items-center mt-8" style="margin-bottom:16px">
    <a href="{{ route('admin.support') }}" class="btn btn-sm">→ بازگشت به تیکت‌ها</a>
    <span class="pill {{ $enabled ? 'pill-green' : 'pill-amber' }}">{{ $enabled ? 'متصل به Claude · '.$model : 'کلید API تنظیم نشده — پاسخ از پرسش‌های متداول' }}</span>
</div>

<div class="kpi-grid">
    <div class="mini-stat"><div><div class="ms-v tnum">{{ Persian::digits($ai['week']) }}</div><div class="ms-l">گفتگو در ۷ روز</div></div></div>
    <div class="mini-stat"><div><div class="ms-v tnum">{{ Persian::digits($ai['escalated']) }}</div><div class="ms-l">ارجاع به کارشناس</div></div></div>
    <div class="mini-stat"><div><div class="ms-v tnum">{{ $ai['self_served_pct'] === null ? '—' : Persian::digits($ai['self_served_pct']).'٪' }}</div><div class="ms-l">حل بدون کارشناس</div></div></div>
    <div class="mini-stat"><div><div class="ms-v tnum">{{ Persian::money($ai['tokens']) }}</div><div class="ms-l">توکن مصرفی (۳۰ روز)</div></div></div>
</div>

<div class="grid col-2-1 mt-16">
    <div class="card">
        <div class="card-head">
            <h3>گفتگوها</h3>
            <div class="head-actions" style="gap:6px">
                @foreach(['' => 'همه', 'active' => 'فعال', 'escalated' => 'ارجاع‌شده', 'closed' => 'بسته'] as $k => $l)
                    <a class="btn btn-sm {{ request('status', '') === $k ? 'btn-primary' : '' }}" href="{{ route('admin.support.ai', array_filter(['status' => $k])) }}">{{ $l }}</a>
                @endforeach
            </div>
        </div>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>مشتری</th><th>موضوع</th><th>کانال</th><th>پیام</th><th>آخرین فعالیت</th><th>وضعیت</th><th>تیکت</th></tr></thead>
                <tbody>
                @forelse($conversations as $c)
                    <tr style="cursor:pointer" onclick="location.href='{{ route('admin.support.ai.show', $c->uuid) }}'">
                        <td class="cell-strong">{{ $c->user?->name ?? '—' }}<div class="cell-muted fs12 tnum">{{ Persian::digits($c->user?->phone ?? '') }}</div></td>
                        <td>{{ \Illuminate\Support\Str::limit($c->title ?? '—', 50) }}</td>
                        <td class="cell-muted">{{ $c->channel === 'app' ? 'اپ موبایل' : 'وب‌اپ' }}</td>
                        <td class="tnum">{{ Persian::digits($c->message_count) }}</td>
                        <td class="cell-muted">{{ Persian::digits($c->last_message_at?->locale('fa')->diffForHumans() ?? '—') }}</td>
                        <td><span class="pill {{ $st($c->status)[1] }}">{{ $st($c->status)[0] }}</span></td>
                        <td>@if($c->ticket)<a class="mono" href="{{ route('admin.support.ticket', $c->ticket) }}" onclick="event.stopPropagation()">{{ $c->ticket->ticket_number }}</a>@else<span class="muted">—</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="muted" style="text-align:center;padding:28px">هنوز گفتگویی با دستیار انجام نشده است.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($conversations->hasPages())<div class="card-pad">{{ $conversations->links() }}</div>@endif
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
        <form class="card" method="POST" action="{{ route('admin.support.ai.settings') }}">@csrf
            <div class="card-head"><div><h3>دستورالعمل اختصاصی دستیار</h3><div class="ch-sub">به راهنمای پایهٔ دستیار اضافه می‌شود (مثلاً کمپین‌ها، ساعت کاری، قوانین استرداد)</div></div></div>
            <div class="card-pad">
                <textarea class="finput" name="instructions" rows="9" maxlength="4000" style="width:100%;resize:vertical;min-height:180px" placeholder="مثلاً: تا پایان آبان ۲۰٪ تخفیف شارژ شبانه داریم؛ استرداد بالای ۵۰۰ هزار تومان فقط با تأیید کارشناس.">{{ $instructions }}</textarea>
                <button class="btn btn-primary mt-8" type="submit">ذخیره</button>
            </div>
        </form>

        <div class="card">
            <div class="card-head"><div><h3>پاسخ‌های آماده کارشناسان</h3><div class="ch-sub">در صفحهٔ پاسخ تیکت قابل انتخاب‌اند</div></div></div>
            <div class="card-pad" style="display:flex;flex-direction:column;gap:8px">
                @foreach($canned as $i => $c)
                    <div class="flex between items-center" style="gap:8px;border-bottom:1px solid var(--border-2);padding-bottom:6px">
                        <div><div class="fw7 fs13">{{ $c['title'] }}</div><div class="muted fs12">{{ \Illuminate\Support\Str::limit($c['text'], 70) }}</div></div>
                        <form method="POST" action="{{ route('admin.support.canned.destroy', $i) }}" onsubmit="return confirm('حذف شود؟')">@csrf @method('DELETE')<button class="btn btn-sm" type="submit">حذف</button></form>
                    </div>
                @endforeach
                <form method="POST" action="{{ route('admin.support.canned.store') }}" style="display:flex;flex-direction:column;gap:8px;margin-top:6px">@csrf
                    <input class="finput" name="title" maxlength="60" required placeholder="عنوان">
                    <textarea class="finput" name="text" rows="3" maxlength="2000" required placeholder="متن پاسخ"></textarea>
                    <button class="btn btn-sm" type="submit">+ افزودن پاسخ آماده</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;box-shadow:var(--shadow-sm)}
.ms-v{font-size:22px;font-weight:800}.ms-l{font-size:12px;color:var(--muted)}
</style>
@endpush
