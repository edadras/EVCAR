@extends('app.layouts.mobile', ['tab' => 'account'])

@section('title', 'SafirCharge — سوابق شارژ')

@section('phone')
@php use App\Support\Persian; @endphp
<div class="appbar" style="padding-top:2px">
    <div style="flex:1"><div class="ab-title">سوابق شارژ</div></div>
    <button class="ab-btn" type="button"><span data-ic="download"></span></button>
</div>

<div class="screen">
    <div class="summary rise">
        <div class="sm-item"><div class="sm-v tnum">{{ Persian::digits($monthStats['sessions']) }}</div><div class="sm-l">جلسه</div></div>
        <div class="sm-sep"></div>
        <div class="sm-item"><div class="sm-v tnum">{{ $monthStats['energy'] }} <small>kWh</small></div><div class="sm-l">انرژی</div></div>
        <div class="sm-sep"></div>
        <div class="sm-item"><div class="sm-v tnum">{{ $monthStats['cost'] }}</div><div class="sm-l">هزینه (ت)</div></div>
    </div>

    <div class="eco rise" style="animation-delay:.05s">
        <div class="eco-ic" data-ic="leaf"></div>
        <div style="flex:1">
            <div class="fw7">{{ $monthStats['co2_kg'] }} کیلوگرم CO₂ کمتر</div>
            <div class="fs12" style="opacity:.85">معادل کاشت {{ $monthStats['trees'] }} اصله درخت در این ماه 🌱</div>
        </div>
    </div>

    <div class="sec-title">جلسات اخیر</div>
    <div class="tx-search">
        <span data-ic="search"></span>
        <input id="histSearch" placeholder="جستجو در جلسات (ایستگاه، تاریخ، روش…)">
    </div>
    <div style="display:flex;flex-direction:column;gap:11px" id="list"></div>
    <div class="tx-empty" id="histEmpty" style="display:none">جلسه‌ای با این جستجو یافت نشد.</div>
</div>
@endsection

@push('styles')
<style>
.summary{display:flex;align-items:center;background:var(--surface);border:1px solid var(--border);border-radius:18px;padding:16px;box-shadow:var(--shadow-sm)}
.sm-item{flex:1;text-align:center}
.sm-v{font-size:21px;font-weight:800}.sm-v small{font-size:12px;font-weight:600;color:var(--text-2)}
.sm-l{font-size:11.5px;color:var(--muted);margin-top:2px}
.sm-sep{width:1px;align-self:stretch;background:var(--border-2)}

.eco{display:flex;align-items:center;gap:13px;margin-top:14px;padding:15px;border-radius:18px;color:#fff;background:linear-gradient(120deg,#16A34A,#34d399)}
.eco-ic{width:46px;height:46px;border-radius:14px;background:rgba(255,255,255,.2);display:grid;place-items:center;flex:none}
.eco-ic svg{width:23px;height:23px}

.hist{background:var(--surface);border:1px solid var(--border);border-radius:16px;padding:14px;box-shadow:var(--shadow-sm)}
.hist .h-ic{width:44px;height:44px;border-radius:13px;display:grid;place-items:center;flex:none}
.hist .h-ic svg{width:21px;height:21px}
.hist .h-stats{display:flex;gap:16px;margin-top:11px;padding-top:11px;border-top:1px solid var(--border-2)}
.hist .h-stats>div{font-size:12px;color:var(--muted)}
.hist .h-stats b{display:block;color:var(--text);font-size:13.5px;font-weight:700;margin-top:1px}
.tx-search{position:relative;margin-bottom:14px}
.tx-search input{width:100%;height:46px;border:1.5px solid var(--border);background:var(--surface-2);border-radius:14px;padding:0 44px 0 14px;font-family:inherit;font-size:13.5px;color:var(--text);outline:none}
.tx-search input:focus{border-color:var(--accent);background:var(--surface)}
.tx-search span[data-ic]{position:absolute;inset-inline-start:14px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:var(--muted);display:inline-grid}.tx-search svg{width:18px;height:18px}
.tx-empty{text-align:center;color:var(--muted);font-size:13px;padding:24px 0}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var H=@json($sessionsJs);
  document.getElementById('list').innerHTML=H.length?H.map(function(h){
    var ok=h.st[1]==='pill-green';
    return '<div class="hist">'+
      '<div class="row">'+
        '<div class="h-ic '+(ok?'ic-blue':'ic-red')+'">'+window.icon('bolt')+'</div>'+
        '<div style="flex:1"><div class="row between"><span class="fw7" style="font-size:14.5px">'+h.n+'</span><span class="pill '+h.st[1]+'">'+h.st[0]+'</span></div>'+
        '<div class="fs12 muted mt4">'+h.date+' · '+h.m+'</div></div>'+
      '</div>'+
      '<div class="h-stats">'+
        '<div>انرژی<b class="tnum">'+h.en+'</b></div>'+
        '<div>مدت<b class="tnum">'+h.dur+'</b></div>'+
        '<div style="margin-inline-start:auto;text-align:end">هزینه<b class="tnum">'+h.amt+' ت</b></div>'+
      '</div>'+
    '</div>';
  }).join(''):'<div class="tx-empty">جلسه‌ای ثبت نشده است.</div>';

  var se=document.getElementById('histSearch');
  se.addEventListener('input',function(){
    var q=se.value.trim().toLowerCase();var any=false;
    document.querySelectorAll('#list .hist').forEach(function(el){
      var show=!q||(el.textContent||'').toLowerCase().indexOf(q)>-1;
      el.style.display=show?'':'none';if(show)any=true;
    });
    document.getElementById('histEmpty').style.display=any?'none':'block';
  });
})();
</script>
@endpush
