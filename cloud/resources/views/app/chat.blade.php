@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — گفتگوی آنلاین')

@section('phone')
@php
use App\Support\Persian;
$activeTicket = $ticket ?? $tickets->firstWhere('status', 'open') ?? $tickets->first();
@endphp
<div class="appbar chat-bar" style="padding-top:2px">
    <a href="{{ route('app.support') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div class="chat-agent">
        <span class="ca-av" data-ic="headset"></span>
        <div>
            <div class="fw7 fs13">پشتیبانی SafirCharge</div>
            <div class="fs12" style="color:var(--green)"><span class="onl"></span>آنلاین · پاسخ فوری</div>
        </div>
    </div>
    <a href="tel:1570" class="ab-btn"><span data-ic="headset"></span></a>
</div>

<div class="chat-scroll" id="chat">
    <div class="chat-day">امروز</div>
    @if($activeTicket && $messages->isNotEmpty())
        @foreach($messages as $m)
        <div class="msg {{ $m->is_staff_reply ? 'bot' : 'me' }}">
            <div class="bub">{{ $m->message }}</div>
            <span class="time">{{ Persian::digits($m->created_at?->format('H:i') ?? '—') }}</span>
        </div>
        @endforeach
    @else
        <div class="msg bot"><div class="bub">سلام! به پشتیبانی SafirCharge خوش آمدید 👋 چطور می‌توانم کمکتان کنم؟</div><span class="time">{{ Persian::digits(now()->format('H:i')) }}</span></div>
        <div class="quick" id="quick">
            <button type="button">مشکل در شارژ دارم</button>
            <button type="button">کسر هزینهٔ اشتباه</button>
            <button type="button">کارت RFID کار نمی‌کند</button>
            <button type="button">سوال دربارهٔ تعرفه</button>
        </div>
    @endif
</div>

@if($activeTicket)
<form method="POST" action="{{ route('app.chat.store') }}" class="chat-input" id="chatForm">
@csrf
<input type="hidden" name="ticket_id" value="{{ $activeTicket->id }}">
<button type="button" class="ci-attach"><span data-ic="plus"></span></button>
<input name="message" id="msgInput" placeholder="پیام خود را بنویسید…" required>
<button type="submit" class="ci-send" id="sendBtn"><span data-ic="nav"></span></button>
</form>
@else
<div class="chat-input">
    <a href="{{ route('app.ticket.new') }}" class="btn btn-orange" style="flex:1;height:46px;display:flex;align-items:center;justify-content:center">ثبت درخواست برای گفتگو</a>
</div>
@endif
@endsection

@push('styles')
<style>
.chat-bar{background:var(--surface);border-bottom:1px solid var(--border);gap:10px}
.chat-agent{flex:1;display:flex;align-items:center;gap:10px}
.ca-av{width:40px;height:40px;border-radius:12px;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;flex:none}.ca-av svg{width:21px;height:21px}
.onl{display:inline-block;width:7px;height:7px;border-radius:50%;background:var(--green);margin-inline-end:5px}
.chat-scroll{flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:10px;background:var(--bg)}
.chat-day{text-align:center;font-size:11px;color:var(--muted);margin:4px 0 8px}
.msg{display:flex;flex-direction:column;max-width:80%}
.msg .bub{padding:11px 14px;font-size:13.5px;line-height:1.8;border-radius:18px}
.msg .time{font-size:10px;color:var(--muted);margin-top:3px}
.msg.bot{align-self:flex-start;align-items:flex-start}
.msg.bot .bub{background:var(--surface);border:1px solid var(--border);border-bottom-right-radius:6px;color:var(--text)}
.msg.me{align-self:flex-end;align-items:flex-end}
.msg.me .bub{background:var(--accent);color:#fff;border-bottom-left-radius:6px}
.msg.me .time{align-self:flex-end}
.quick{display:flex;flex-wrap:wrap;gap:8px;margin-top:4px}
.quick button{border:1px solid var(--accent);background:var(--accent-soft);color:var(--accent-strong);font-family:inherit;font-size:12.5px;font-weight:600;padding:8px 13px;border-radius:20px}
.chat-input{flex:none;display:flex;align-items:center;gap:9px;padding:12px 14px calc(14px + env(safe-area-inset-bottom,0));background:var(--surface);border-top:1px solid var(--border)}
.chat-input input{flex:1;height:46px;border:1.5px solid var(--border);background:var(--surface-2);border-radius:23px;padding:0 16px;font-family:inherit;font-size:14px;color:var(--text);outline:none}
.chat-input input:focus{border-color:var(--accent)}
.ci-attach{width:44px;height:44px;border-radius:50%;border:1px solid var(--border);background:var(--surface);color:var(--text-2);display:grid;place-items:center;flex:none}.ci-attach svg{width:20px;height:20px}
.ci-send{width:46px;height:46px;border-radius:50%;border:none;background:var(--accent);color:#fff;display:grid;place-items:center;flex:none}.ci-send svg{width:20px;height:20px;transform:scaleX(-1)}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var chat=document.getElementById('chat'),input=document.getElementById('msgInput'),form=document.getElementById('chatForm');
  if(!input||!form)return;
  document.querySelectorAll('#quick button').forEach(function(b){
    b.addEventListener('click',function(){input.value=b.textContent;input.focus();});
  });
  chat.scrollTop=chat.scrollHeight;
})();
</script>
@endpush
