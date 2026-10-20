@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — جزئیات درخواست')

@section('phone')
@php
use App\Support\Persian;
$presenter = app(\App\Services\App\MobileAppPresenter::class);
$categoryLabels = [
    'payment' => 'پرداخت',
    'charger' => 'شارژ',
    'rfid' => 'کارت RFID',
    'station' => 'ایستگاه',
    'account' => 'حساب کاربری',
    'other' => 'سایر',
];
@endphp
@if(isset($ticket) && $ticket)
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.support') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">درخواست {{ $ticket->ticket_number }}</div></div>
</div>

<div class="screen">
    @if(session('success'))
    <div class="card-lite" style="color:var(--green);margin-bottom:12px">{{ session('success') }}</div>
    @endif

    @php
        $statusPill = match($ticket->status) {
            'resolved' => 'pill-green',
            'closed' => 'pill-gray',
            default => 'pill-accent',
        };
    @endphp
    <div class="tk-hero rise">
        <div class="row between items-center">
            <span class="pill {{ $statusPill }}">{{ $presenter->ticketStatusLabel($ticket->status) }}</span>
            <span class="fs12 muted mono">{{ $ticket->ticket_number }}</span>
        </div>
        <div class="fw8 mt12" style="font-size:16px">{{ $ticket->subject }}</div>
        <div class="fs12 muted mt4">دستهٔ {{ $categoryLabels[$ticket->category] ?? $ticket->category }} · ثبت {{ $ticket->created_at?->diffForHumans() }}</div>
    </div>

    <div class="card card-p mt16">
        @if($ticket->station)
        <div class="irow"><span class="muted">جلسهٔ مرتبط</span><b>{{ $ticket->station->name }}</b></div>
        @endif
        <div class="irow"><span class="muted">اولویت</span><b>{{ $ticket->priority }}</b></div>
        <div class="irow" style="border-bottom:none"><span class="muted">کارشناس</span><b>{{ $ticket->assignee?->name ?? 'در انتظار تخصیص' }}</b></div>
    </div>

    <div class="sec-title">گفتگوی درخواست</div>
    <div class="thread">
        @foreach($messages as $m)
        <div class="tmsg {{ $m->is_staff_reply ? 'bot' : 'me' }}">
            @if($m->is_staff_reply)<div class="tname">پشتیبانی</div>@endif
            <div class="tbub">{{ $m->message }}</div>
            <span class="tt">{{ Persian::digits($m->created_at?->format('H:i') ?? '—') }}</span>
        </div>
        @endforeach
    </div>
</div>

<form method="POST" action="{{ route('app.chat.store') }}" class="chat-input">
@csrf
<input type="hidden" name="ticket_id" value="{{ $ticket->id }}">
<button type="button" class="ci-attach"><span data-ic="plus"></span></button>
<input name="message" id="msgInput" placeholder="پاسخ خود را بنویسید…" required>
<button type="submit" class="ci-send"><span data-ic="nav"></span></button>
</form>
@else
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.support') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">تیکت‌ها</div></div>
</div>
<div class="screen">
    @forelse(($tickets ?? collect()) as $t)
        <a href="{{ route('app.ticket', ['id' => $t->id]) }}" class="card-lite" style="display:block;margin-bottom:10px;text-decoration:none;color:inherit">
            <b>{{ $t->subject }}</b>
            <div class="muted fs12">{{ $t->ticket_number }} · {{ $presenter->ticketStatusLabel($t->status) }}</div>
        </a>
    @empty
        <p class="muted">تیکتی ندارید.</p>
    @endforelse
    <a href="{{ route('app.ticket.new') }}" class="btn btn-orange mt16" style="display:flex;height:48px;align-items:center;justify-content:center">درخواست جدید</a>
</div>
@endif
@endsection

@push('styles')
<style>
.tk-hero{padding:18px;border-radius:18px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-sm)}
.irow{display:flex;justify-content:space-between;font-size:13px;padding:9px 0;border-bottom:1px solid var(--border-2)}
.thread{display:flex;flex-direction:column;gap:10px}
.tmsg{display:flex;flex-direction:column;max-width:82%}
.tmsg .tbub{padding:11px 14px;font-size:13.5px;line-height:1.8;border-radius:16px}
.tmsg .tt{font-size:10px;color:var(--muted);margin-top:3px}
.tmsg .tname{font-size:11px;color:var(--accent-strong);font-weight:700;margin-bottom:3px}
.tmsg.me{align-self:flex-end;align-items:flex-end}
.tmsg.me .tbub{background:var(--accent);color:#fff;border-bottom-left-radius:5px}
.tmsg.bot{align-self:flex-start}
.tmsg.bot .tbub{background:var(--surface);border:1px solid var(--border);border-bottom-right-radius:5px}
.chat-input{flex:none;display:flex;align-items:center;gap:9px;padding:12px 14px calc(14px + env(safe-area-inset-bottom,0));background:var(--surface);border-top:1px solid var(--border)}
.chat-input input{flex:1;height:46px;border:1.5px solid var(--border);background:var(--surface-2);border-radius:23px;padding:0 16px;font-family:inherit;font-size:14px;color:var(--text);outline:none}
.chat-input input:focus{border-color:var(--accent)}
.ci-attach{width:44px;height:44px;border-radius:50%;border:1px solid var(--border);background:var(--surface);color:var(--text-2);display:grid;place-items:center;flex:none}.ci-attach svg{width:20px;height:20px}
.ci-send{width:46px;height:46px;border-radius:50%;border:none;background:var(--accent);color:#fff;display:grid;place-items:center;flex:none}.ci-send svg{width:20px;height:20px;transform:scaleX(-1)}
</style>
@endpush
