@extends('admin.layouts.app', ['page' => 'support', 'title' => 'گفتگوی دستیار هوشمند', 'crumb' => 'مرکز پشتیبانی'])

@section('content')
@php
    use App\Support\Persian;
    $toolNames = ['get_account_summary' => 'خلاصهٔ حساب', 'get_recent_sessions' => 'جلسات شارژ', 'get_wallet_transactions' => 'تراکنش‌ها', 'find_stations' => 'جستجوی ایستگاه', 'get_tariffs' => 'تعرفه‌ها', 'list_my_tickets' => 'تیکت‌ها', 'create_support_ticket' => 'ثبت تیکت'];
@endphp
<div class="flex between items-center mt-8" style="margin-bottom:16px">
    <a href="{{ route('admin.support.ai') }}" class="btn btn-sm">→ همهٔ گفتگوها</a>
    @if($conversation->ticket)<a class="btn btn-sm btn-primary" href="{{ route('admin.support.ticket', $conversation->ticket) }}">تیکت {{ $conversation->ticket->ticket_number }}</a>@endif
</div>
<div class="grid col-2-1">
    <div class="card">
        <div class="card-head"><div><h3>{{ $conversation->title ?? 'گفتگو' }}</h3><div class="ch-sub">{{ $conversation->channel === 'app' ? 'اپ موبایل' : 'وب‌اپ' }} · {{ Persian::digits($conversation->created_at?->format('Y/m/d H:i') ?? '') }}</div></div></div>
        <div class="card-pad thread">
            @forelse($conversation->messages->whereNotNull('display_text') as $m)
                <div class="tm {{ $m->role === 'user' ? 'cust' : 'bot' }}">
                    <div class="tm-head"><b>{{ $m->role === 'user' ? ($conversation->user?->name ?? 'مشتری') : '✦ دستیار' }}@if($m->source === 'fallback') <span class="pill pill-amber">پاسخ پیش‌فرض</span>@endif</b><span class="muted fs12">{{ Persian::digits($m->created_at?->format('H:i') ?? '') }}</span></div>
                    <div class="tm-body">{{ $m->display_text }}</div>
                    @if(! empty($m->tools_used))<div class="muted fs12 mt-8">🔎 {{ collect($m->tools_used)->map(fn ($t) => $toolNames[$t] ?? $t)->join('، ') }}</div>@endif
                </div>
            @empty
                <div class="muted">پیامی ثبت نشده است.</div>
            @endforelse
        </div>
    </div>
    <div class="card">
        <div class="card-head"><h3>جزئیات</h3></div>
        <div class="card-pad fs13" style="display:flex;flex-direction:column;gap:8px">
            <div class="flex between"><span class="muted">مشتری</span><b>{{ $conversation->user?->name ?? '—' }}</b></div>
            <div class="flex between"><span class="muted">موبایل</span><span class="tnum">{{ Persian::digits($conversation->user?->phone ?? '') }}</span></div>
            <div class="flex between"><span class="muted">وضعیت</span><span>{{ ['escalated' => 'ارجاع به کارشناس', 'closed' => 'بسته'][$conversation->status] ?? 'فعال' }}</span></div>
            <div class="flex between"><span class="muted">تعداد پیام</span><span class="tnum">{{ Persian::digits($conversation->message_count) }}</span></div>
            <div class="flex between"><span class="muted">توکن ورودی / خروجی</span><span class="tnum">{{ Persian::money($conversation->input_tokens) }} / {{ Persian::money($conversation->output_tokens) }}</span></div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.thread{display:flex;flex-direction:column;gap:12px}
.tm{border-radius:14px;padding:12px 14px;max-width:88%}
.tm.cust{background:var(--surface-2);border:1px solid var(--border);align-self:flex-start}
.tm.bot{background:var(--accent-soft);align-self:flex-end}
.tm-head{display:flex;gap:10px;justify-content:space-between;margin-bottom:6px;font-size:13px}
.tm-body{white-space:pre-wrap;line-height:1.9;font-size:13.5px}
</style>
@endpush
