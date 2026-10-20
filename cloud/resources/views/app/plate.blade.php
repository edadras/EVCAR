@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — ثبت پلاک')

@section('phone')
@php
use App\Support\Persian;
$vehicle = $vehicles->firstWhere('is_default', true) ?? $vehicles->first();
$d2 = $vehicle ? Persian::digits($vehicle->plate_two) : '۱۲';
$letter = $vehicle?->plate_letter ?? 'س';
$d3 = $vehicle ? Persian::digits($vehicle->plate_three) : '۳۴۵';
$prov = $vehicle ? Persian::digits($vehicle->plate_province) : '۱۱';
@endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.account') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">شماره پلاک خودرو</div></div>
</div>

<div class="screen">
    @if(session('success'))
    <div class="note-box mb12" style="border-color:var(--green);color:var(--green)">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="note-box mb12" style="border-color:var(--red);color:var(--red)">{{ $errors->first() }}</div>
    @endif
    <div class="plate-preview rise">
        <div id="bigPlate"></div>
        <div class="fs12 muted mt12">پیش‌نمایش پلاک شما</div>
    </div>

    <form method="POST" action="{{ route('app.plate.update') }}" id="plateForm">
    @csrf
    <div class="sec-title">ویرایش پلاک</div>
    <div class="card card-p">
        <div class="plate-edit" id="plateEdit">
            <span class="pe-flag"><i></i><i></i><i></i></span>
            <span class="pe-seg"><input class="pe-d2 tnum" name="plate_two" maxlength="2" value="{{ $d2 }}" inputmode="numeric" required><small>دو رقم</small></span>
            <span class="pe-seg">
                <select class="pe-let" name="plate_letter">
                    @foreach(['الف','ب','ج','د','س','ص','ط','ق','ل','م','ن','و','ه','ی','معلولین'] as $l)
                        <option @selected($letter === $l)>{{ $l }}</option>
                    @endforeach
                </select>
                <small>حرف</small>
            </span>
            <span class="pe-seg"><input class="pe-d3 tnum" name="plate_three" maxlength="3" value="{{ $d3 }}" inputmode="numeric" required><small>سه رقم</small></span>
            <span class="pe-seg"><input class="pe-prov tnum" name="plate_province" maxlength="2" value="{{ $prov }}" inputmode="numeric" required><small>استان</small></span>
        </div>
    </div>
    </form>

    <div class="auto-card mt16">
        <div class="row" style="gap:10px">
            <span class="ac-ic" data-ic="scan"></span>
            <div style="flex:1"><div class="fw7 fs13">فعال‌سازی شارژ خودکار با این پلاک</div></div>
            <span class="switch {{ $vehicle ? 'on' : '' }}" data-sw id="autoSw"><i></i></span>
        </div>
        <div class="fs12 muted mt12" style="line-height:1.7">با ثبت پلاک، در ایستگاه‌های مجهز به دوربین تشخیص پلاک، شارژ به‌صورت خودکار آغاز و هزینه از کیف پول کسر می‌شود. نیازی به اسکن QR یا کارت RFID نیست.</div>
    </div>

    <div class="note-box mt16">
        <span data-ic="shield"></span>
        <span>پلاک شما فقط برای شناسایی خودرو و شارژ خودکار استفاده می‌شود و محرمانه باقی می‌ماند.</span>
    </div>
</div>

<div class="savebar">
    <button type="submit" form="plateForm" class="btn"><span data-ic="check"></span> ثبت پلاک</button>
</div>
@endsection

@push('styles')
<style>
.plate-preview{display:flex;flex-direction:column;align-items:center;padding:24px;border-radius:20px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-sm)}
.plate-edit{display:flex;align-items:stretch;height:74px;border:2px solid var(--border);border-radius:13px;overflow:hidden;background:var(--surface-2);direction:ltr}
.plate-edit:focus-within{border-color:var(--accent)}
.pe-flag{width:30px;background:#0b5fa5;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;padding-bottom:7px;gap:2px}
.pe-flag i{width:15px;height:3px;border-radius:1px}
.pe-flag i:nth-child(1){background:#16A34A}.pe-flag i:nth-child(2){background:#fff}.pe-flag i:nth-child(3){background:#DC2626}
.pe-seg{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;border-left:1px solid var(--border)}
.pe-seg:last-child{border-left:none}
.pe-seg input,.pe-seg select{border:none;background:transparent;font-family:inherit;font-weight:800;font-size:21px;text-align:center;color:var(--text);outline:none;width:100%;padding:0 4px}
.pe-seg .pe-let{font-size:17px;color:var(--accent)}
.pe-seg small{font-size:9.5px;color:var(--muted);font-weight:600}
.auto-card{padding:16px;border-radius:18px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-sm)}
.ac-ic{display:inline-grid;width:20px;height:20px;color:var(--accent);flex:none}.ac-ic svg{width:20px;height:20px}
.switch{width:46px;height:26px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;cursor:pointer;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .18s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-20px)}
.note-box{display:flex;align-items:flex-start;gap:10px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:14px;padding:13px;font-size:12px;color:var(--text-2);line-height:1.7}
.note-box svg{width:19px;height:19px;color:var(--green);flex:none;margin-top:1px}
.savebar{position:absolute;bottom:0;left:0;right:0;background:color-mix(in srgb,var(--surface) 94%,transparent);backdrop-filter:blur(14px);border-top:1px solid var(--border);padding:14px 20px calc(20px + env(safe-area-inset-bottom,0));z-index:20}
.savebar .btn svg{width:18px;height:18px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var pe=document.getElementById('plateEdit');
  var d2=pe.querySelector('.pe-d2'),let_=pe.querySelector('.pe-let'),d3=pe.querySelector('.pe-d3'),prov=pe.querySelector('.pe-prov');
  function render(){
    document.getElementById('bigPlate').innerHTML=window.plate({d2:d2.value||'۱۲','let':let_.value,d3:d3.value||'۳۴۵',prov:prov.value||'۱۱',size:'lg'});
  }
  [d2,d3,prov].forEach(function(el){el.addEventListener('input',render);});
  let_.addEventListener('change',render);
  render();
  document.querySelectorAll('[data-sw]').forEach(function(s){s.addEventListener('click',function(){s.classList.toggle('on');});});
})();
</script>
@endpush
