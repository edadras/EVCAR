@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — پشتیبانی و تماس با ما')

@section('phone')
@php
use App\Support\Persian;
$presenter = app(\App\Services\App\MobileAppPresenter::class);
$categoryIcons = [
    'payment' => ['ic-orange', 'wallet'],
    'charger' => ['ic-blue', 'bolt'],
    'rfid' => ['ic-green', 'chip'],
    'station' => ['ic-violet', 'pin'],
    'account' => ['ic-blue', 'user'],
    'other' => ['ic-orange', 'receipt'],
];
@endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.account') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">پشتیبانی و تماس با ما</div></div>
</div>

<div class="screen">
    <div class="sup-banner rise">
        <div class="sb-ic" data-ic="headset"></div>
        <div style="flex:1">
            <div class="fw8" style="font-size:16px">چطور می‌توانیم کمک کنیم؟</div>
            <div class="fs12" style="opacity:.9">پشتیبانی ۲۴ ساعته، ۷ روز هفته</div>
        </div>
    </div>

    <div class="contact-grid">
        <a class="contact-item" href="tel:{{ $contact['phone'] }}"><span class="ci-ic ic-green" data-ic="headset"></span><div class="fw7 fs13">تماس تلفنی</div><div class="fs12 muted tnum">{{ Persian::digits($contact['phone']) }}</div></a>
        <a class="contact-item" href="{{ route('app.chat') }}"><span class="ci-ic ic-blue" data-ic="bolt"></span><div class="fw7 fs13">گفتگوی آنلاین</div><div class="fs12 muted">پاسخ فوری</div></a>
    </div>

    <div class="sec-title">درخواست‌های من <a href="{{ route('app.ticket.new') }}">جدید +</a></div>
    <div class="card card-p">
        @forelse($tickets->take(5) as $t)
            @php
                $ic = $categoryIcons[$t->category] ?? ['ic-orange', 'bolt'];
                $pill = match($t->status) {
                    'resolved' => 'pill-green',
                    'closed' => 'pill-gray',
                    default => 'pill-accent',
                };
                $statusLabel = $presenter->ticketStatusLabel($t->status);
            @endphp
            <a class="tk" href="{{ route('app.ticket', ['id' => $t->id]) }}" @if($loop->last) style="border-bottom:none" @endif>
                <span class="tk-ic {{ $ic[0] }}" data-ic="{{ $ic[1] }}"></span>
                <div class="l-main">
                    <div class="l-title">{{ $t->subject }}</div>
                    <div class="l-sub">{{ $t->ticket_number }} · {{ $t->created_at?->diffForHumans() }}</div>
                </div>
                <span class="pill {{ $pill }}">{{ $statusLabel }}</span>
                <span class="chev" data-ic="chevron"></span>
            </a>
        @empty
            <p class="muted fs13" style="padding:8px 0">درخواستی ثبت نشده است.</p>
        @endforelse
    </div>

    <div class="sec-title">سوالات متداول</div>
    <div class="card card-p" id="faq">
        @foreach($contact['faq'] as $i => $item)
        <div class="faq-item" @if($loop->last) style="border-bottom:none" @endif>
            <button type="button" class="faq-q">{{ $item['q'] }}<span class="faq-x" data-ic="plus"></span></button>
            <div class="faq-a">{{ $item['a'] }}</div>
        </div>
        @endforeach
    </div>

    <div class="social mt20 mb">
        <span class="muted fs12">ما را دنبال کنید</span>
        <div class="row mt12" style="gap:10px;justify-content:center">
            <span class="soc">in</span><span class="soc">t</span><span class="soc">@</span>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.sup-banner{display:flex;align-items:center;gap:14px;padding:18px;border-radius:20px;color:#fff;background:linear-gradient(135deg,var(--accent),color-mix(in srgb,var(--accent) 50%,var(--orange)));box-shadow:0 14px 30px rgba(var(--accent-rgb),.35)}
.sb-ic{width:50px;height:50px;border-radius:15px;background:rgba(255,255,255,.2);display:grid;place-items:center;flex:none}.sb-ic svg{width:25px;height:25px}
.contact-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}
.contact-item{text-align:center;padding:16px;border-radius:16px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-sm);text-decoration:none;color:inherit}
.ci-ic{width:46px;height:46px;border-radius:14px;display:grid;place-items:center;margin:0 auto 10px}.ci-ic svg{width:22px;height:22px}
.tk{display:flex;align-items:center;gap:12px;padding:13px 0;border-bottom:1px solid var(--border-2);text-decoration:none;color:inherit}
.tk-ic{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;flex:none}.tk-ic svg{width:19px;height:19px}
.l-title{font-weight:700;font-size:13.5px}.l-sub{font-size:11.5px;color:var(--muted);margin-top:2px}
.chev{display:inline-grid;width:18px;height:18px;color:var(--muted);flex:none}.chev svg{width:18px;height:18px}
.faq-item{border-bottom:1px solid var(--border-2)}
.faq-q{width:100%;display:flex;align-items:center;justify-content:space-between;gap:10px;background:none;border:none;padding:14px 0;font-family:inherit;font-size:13.5px;font-weight:700;color:var(--text);text-align:start}
.faq-x{display:inline-grid;width:20px;height:20px;color:var(--muted);flex:none;transition:transform .2s}.faq-x svg{width:20px;height:20px}
.faq-item.open .faq-x{transform:rotate(45deg)}
.faq-a{max-height:0;overflow:hidden;font-size:12.5px;color:var(--text-2);line-height:1.8;transition:max-height .25s,padding .25s}
.faq-item.open .faq-a{max-height:220px;padding-bottom:14px}
.social{text-align:center}
.soc{width:42px;height:42px;border-radius:13px;background:var(--surface-3);color:var(--text-2);display:grid;place-items:center;font-weight:800;font-size:15px}
.row{display:flex}.mb{margin-bottom:8px}
</style>
@endpush

@push('scripts')
<script>
document.querySelectorAll('.faq-q').forEach(function(q){
  q.addEventListener('click',function(){q.parentElement.classList.toggle('open');});
});
</script>
@endpush
