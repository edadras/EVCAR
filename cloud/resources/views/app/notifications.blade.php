@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — اعلان‌ها')

@section('phone')
@php
    use App\Support\Persian;
    $iconFor = function ($n) {
        return match ($n->type) {
            'session_complete', 'charging_complete' => ['ic' => 'check', 'c' => 'ic-green'],
            'session_start', 'charging_start' => ['ic' => 'bolt', 'c' => 'ic-blue'],
            'reward', 'bonus', 'gift' => ['ic' => 'gift', 'c' => 'ic-violet'],
            'wallet_low', 'wallet' => ['ic' => 'wallet', 'c' => 'ic-orange'],
            'station_new', 'station' => ['ic' => 'pin', 'c' => 'ic-green'],
            'security', 'login' => ['ic' => 'shield', 'c' => 'ic-blue'],
            default => ['ic' => 'bell', 'c' => 'ic-blue'],
        };
    };
    $timeFor = function ($n) {
        if (!$n->sent_at) {
            return '—';
        }
        if ($n->sent_at->isToday()) {
            return Persian::digits($n->sent_at->format('H:i'));
        }
        if ($n->sent_at->isYesterday()) {
            return 'دیروز · '.Persian::digits($n->sent_at->format('H:i'));
        }

        return Persian::digits($n->sent_at->diffForHumans());
    };
@endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.home') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">اعلان‌ها</div></div>
    <button class="ab-btn" id="readAll" type="button"><span data-ic="check"></span></button>
</div>

<div class="screen">
    <div class="muted fs12 fw7" style="margin:4px 0 10px">امروز</div>
    <div class="card card-p" id="today">
        @forelse($grouped['today'] ?? [] as $n)
            @php $style = $iconFor($n); @endphp
            <div class="nrow{{ $n->read_at ? '' : ' unread' }}">
                <div class="ni {{ $style['c'] }}"><span data-ic="{{ $style['ic'] }}"></span></div>
                <div class="l-main">
                    <div class="l-title">{{ $n->title }}</div>
                    <div class="n-body">{{ $n->body }}</div>
                    <div class="n-time">{{ $timeFor($n) }}</div>
                </div>
            </div>
        @empty
            <div class="muted fs13" style="padding:8px 0">اعلانی برای امروز نیست.</div>
        @endforelse
    </div>

    <div class="muted fs12 fw7" style="margin:20px 0 10px">پیش‌تر</div>
    <div class="card card-p" id="earlier">
        @forelse($grouped['earlier'] ?? [] as $n)
            @php $style = $iconFor($n); @endphp
            <div class="nrow{{ $n->read_at ? '' : ' unread' }}">
                <div class="ni {{ $style['c'] }}"><span data-ic="{{ $style['ic'] }}"></span></div>
                <div class="l-main">
                    <div class="l-title">{{ $n->title }}</div>
                    <div class="n-body">{{ $n->body }}</div>
                    <div class="n-time">{{ $timeFor($n) }}</div>
                </div>
            </div>
        @empty
            <div class="muted fs13" style="padding:8px 0">اعلان قدیمی‌تری نیست.</div>
        @endforelse
    </div>
</div>
@endsection

@push('styles')
<style>
.nrow{display:flex;align-items:flex-start;gap:12px;padding:13px 0;border-bottom:1px solid var(--border-2);position:relative}
.nrow:last-child{border-bottom:none}
.nrow .ni{width:42px;height:42px;border-radius:13px;display:grid;place-items:center;flex:none}
.nrow .ni svg{width:20px;height:20px}
.nrow .l-title{font-weight:700;font-size:14px}
.nrow .n-body{font-size:12.5px;color:var(--text-2);margin-top:3px;line-height:1.6}
.nrow .n-time{font-size:11px;color:var(--muted);margin-top:5px}
.nrow.unread::after{content:"";position:absolute;top:16px;inset-inline-end:0;width:8px;height:8px;border-radius:50%;background:var(--accent)}
.nrow.unread{padding-inline-end:16px}
</style>
@endpush

@push('scripts')
<script>
document.getElementById('readAll').addEventListener('click',function(){
  fetch(@json(route('app.notifications.read')),{
    method:'POST',
    headers:{'X-CSRF-TOKEN':document.querySelector('meta[name=csrf-token]').content,'Accept':'application/json'}
  }).then(function(){
    document.querySelectorAll('.nrow').forEach(function(r){r.classList.remove('unread');});
  });
});
</script>
@endpush
