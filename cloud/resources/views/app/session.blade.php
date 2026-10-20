@extends('app.layouts.mobile', ['tab' => 'account', 'noTab' => true])

@section('title', 'SafirCharge — رسید جلسهٔ شارژ')

@section('phone')
@php use App\Support\Persian; @endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.history') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">رسید جلسهٔ شارژ</div></div>
    <button class="ab-btn" type="button" onclick="window.print()" title="چاپ / PDF"><span data-ic="download"></span></button>
</div>

<div class="screen" style="padding-bottom:40px">
    @if(session('success'))
    <div class="card-lite" style="color:var(--green);margin-bottom:12px">{{ session('success') }}</div>
    @endif

    <div class="rc-head rise">
        <div class="rc-ic {{ $r['done'] ? 'ic-green' : 'ic-amber' }}" data-ic="{{ $r['done'] ? 'check' : 'bolt' }}"></div>
        <div class="fs12 muted mt4">{{ $r['done'] ? 'پرداخت‌شده از کیف پول' : 'جلسه در جریان' }}</div>
        <div class="rc-total tnum">{{ Persian::money($r['total']) }} <small>تومان</small></div>
        <div class="fw7">{{ $r['station']['name'] }}</div>
        <div class="fs12 muted">{{ $r['started_label'] }} تا {{ $r['stopped_label'] }}</div>
    </div>

    <div class="rc-grid">
        <div><span>انرژی</span><b class="tnum">{{ Persian::kwh($r['energy_kwh'], 2) }} kWh</b></div>
        <div><span>مدت</span><b class="tnum">{{ Persian::digits($r['duration_min']) }} دقیقه</b></div>
        <div><span>میانگین توان</span><b class="tnum">{{ $r['avg_power_kw'] !== null ? Persian::kwh($r['avg_power_kw']).' kW' : '—' }}</b></div>
        <div><span>CO₂ کمتر</span><b class="tnum">{{ Persian::kwh($r['co2_kg']) }} kg</b></div>
    </div>

    @if(count($r['curve']) > 1)
    <div class="sec-title">نمودار توان شارژ</div>
    <div class="card card-p"><svg id="curve" viewBox="0 0 320 120" width="100%" height="120" preserveAspectRatio="none"></svg>
        <div class="row between fs12 muted"><span>شروع</span><span>{{ Persian::digits($r['duration_min']) }} دقیقه</span></div></div>
    @endif

    <div class="sec-title">جزئیات هزینه</div>
    <div class="card card-p">
        @foreach($r['lines'] as $l)
        <div class="rc-line"><span>{{ $l['label'] }}</span><b class="tnum" @if($l['amount'] < 0) style="color:var(--green)" @endif>{{ $l['amount'] < 0 ? '−' : '' }}{{ Persian::money(abs($l['amount'])) }} ت</b></div>
        @endforeach
        <div class="rc-line total"><span>جمع کل</span><b class="tnum">{{ Persian::money($r['total']) }} ت</b></div>
        <div class="fs12 muted mt4">نرخ انرژی: {{ Persian::money($r['price_per_kwh']) }} ت/kWh @if($r['rule'])({{ $r['rule'] }})@endif</div>
    </div>

    <div class="sec-title">مشخصات</div>
    <div class="card card-p fs13">
        <div class="rc-line"><span>ایستگاه</span><b>{{ $r['station']['name'] }}</b></div>
        @if($r['station']['address'])<div class="rc-line"><span>آدرس</span><b>{{ $r['station']['address'] }}</b></div>@endif
        <div class="rc-line"><span>شارژر / کانکتور</span><b class="mono">{{ $r['charger'] ?? '—' }} · {{ Persian::digits($r['connector'] ?? '—') }}</b></div>
        @if($r['vehicle'])<div class="rc-line"><span>خودرو</span><b>{{ $r['vehicle'] }}</b></div>@endif
        <div class="rc-line"><span>شناسهٔ جلسه</span><b class="mono fs12">{{ \Illuminate\Support\Str::limit($r['uuid'], 13, '') }}</b></div>
    </div>

    @if($r['review'])
    <div class="sec-title">امتیاز شما</div>
    <div class="card card-p">
        <div class="rv-stars big">@for($i = 1; $i <= 5; $i++)<span class="{{ $i <= $r['review']['rating'] ? 'on' : '' }}">★</span>@endfor</div>
        @if($r['review']['comment'])<div class="fs13 mt4">{{ $r['review']['comment'] }}</div>@endif
        @if($r['review']['tags'])<div class="fs12 muted mt4">{{ implode(' · ', $r['review']['tags']) }}</div>@endif
        @if($r['review']['reply'])<div class="fs12 mt4" style="background:var(--surface-2);padding:8px 10px;border-radius:10px"><b>پاسخ SafirCharge:</b> {{ $r['review']['reply'] }}</div>@endif
    </div>
    @elseif($r['can_review'])
    <div class="sec-title">تجربهٔ شما چطور بود؟</div>
    <form class="card card-p" method="POST" action="{{ route('app.session.review', $r['uuid']) }}" id="rvForm">@csrf
        <input type="hidden" name="rating" id="rvRating" required>
        <div class="rv-pick" id="rvPick">@for($i = 1; $i <= 5; $i++)<button type="button" data-v="{{ $i }}">★</button>@endfor</div>
        <div class="rv-chips">@foreach($r['tags'] as $k => $label)<label><input type="checkbox" name="tags[]" value="{{ $k }}"><span>{{ $label }}</span></label>@endforeach</div>
        <textarea name="comment" rows="3" maxlength="600" class="rv-text" placeholder="نظر شما (اختیاری)"></textarea>
        <button class="btn btn-primary mt4" type="submit" id="rvSubmit" disabled>ثبت امتیاز</button>
    </form>
    @endif

    <div class="row" style="gap:10px;margin-top:16px">
        <a class="btn" style="flex:1" href="{{ route('app.ticket.new', ['session' => $r['uuid']]) }}"><span data-ic="headset"></span> مشکل در این جلسه</a>
        @if($r['station']['code'])<a class="btn" style="flex:1" href="{{ route('app.charger', ['station' => $r['station']['code']]) }}"><span data-ic="bolt"></span> شارژ دوباره</a>@endif
    </div>
</div>
@endsection

@push('styles')
<style>
.rc-head{text-align:center;background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:20px 16px;box-shadow:var(--shadow-sm)}
.rc-ic{width:54px;height:54px;border-radius:16px;display:grid;place-items:center;margin:0 auto}.rc-ic svg{width:26px;height:26px}
.rc-total{font-size:30px;font-weight:800;margin:6px 0}.rc-total small{font-size:13px;color:var(--muted);font-weight:600}
.rc-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}
.rc-grid>div{background:var(--surface);border:1px solid var(--border);border-radius:14px;padding:12px}
.rc-grid span{display:block;font-size:11.5px;color:var(--muted)}.rc-grid b{font-size:15px}
.rc-line{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid var(--border-2);font-size:13px}
.rc-line span{color:var(--muted)}.rc-line.total{border-bottom:none;font-size:15px}.rc-line.total span{color:var(--text);font-weight:700}
.rv-stars span{color:var(--border);font-size:16px}.rv-stars span.on{color:#F59E0B}.rv-stars.big span{font-size:24px}
.rv-pick{display:flex;justify-content:center;gap:6px;direction:ltr}
.rv-pick button{border:none;background:none;font-size:36px;color:var(--border);cursor:pointer;line-height:1}
.rv-pick button.on{color:#F59E0B}
.rv-chips{display:flex;flex-wrap:wrap;gap:7px;margin:12px 0}
.rv-chips label{cursor:pointer}.rv-chips input{display:none}
.rv-chips span{display:inline-block;padding:7px 12px;border-radius:18px;border:1px solid var(--border);font-size:12.5px;font-weight:600}
.rv-chips input:checked+span{background:var(--accent);border-color:var(--accent);color:#fff}
.rv-text{width:100%;border:1.5px solid var(--border);background:var(--surface-2);border-radius:13px;padding:10px 12px;font-family:inherit;font-size:13.5px;color:var(--text)}
.btn svg{width:17px;height:17px}
@media print{.appbar,.rv-pick,#rvForm,.row:last-child{display:none!important}}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var curve=@json($r['curve']);
  var svg=document.getElementById('curve');
  if(svg&&curve.length>1){
    var W=320,H=120,max=Math.max.apply(null,curve.map(function(p){return p.kw;}))||1,tmax=curve[curve.length-1].t||1;
    var pts=curve.map(function(p){return [(p.t/tmax)*W,H-6-(p.kw/max)*(H-16)];});
    var line=pts.map(function(p,i){return (i?'L':'M')+p[0].toFixed(1)+' '+p[1].toFixed(1);}).join(' ');
    svg.innerHTML='<path d="'+line+' L'+W+' '+H+' L0 '+H+' Z" fill="var(--accent)" opacity=".14"/><path d="'+line+'" fill="none" stroke="var(--accent)" stroke-width="2.4" stroke-linejoin="round"/>'+
      '<text x="4" y="12" font-size="10" fill="var(--muted)">'+String(max).replace(/\d/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d];})+' kW</text>';
  }
  var pick=document.getElementById('rvPick');
  if(pick){
    var input=document.getElementById('rvRating'),btn=document.getElementById('rvSubmit');
    pick.querySelectorAll('button').forEach(function(b){b.addEventListener('click',function(){
      var v=+b.dataset.v;input.value=v;btn.disabled=false;
      pick.querySelectorAll('button').forEach(function(x){x.classList.toggle('on',+x.dataset.v<=v);});
    });});
  }
})();
</script>
@endpush
