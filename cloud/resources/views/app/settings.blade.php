@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — تنظیمات')

@section('phone')
@php
    $prefs = $prefs ?? [];
    $targetSoc = (int) ($prefs['charge_target_soc'] ?? 80);
@endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.account') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">تنظیمات</div></div>
</div>

<div class="screen">
    <div class="sec-title" style="margin-top:6px">ظاهر برنامه</div>
    <div class="card card-p">
        <div class="srow"><span class="sr-ic ic-violet" data-ic="eye"></span><span class="l-title" style="flex:1">حالت تیره</span><span class="switch {{ ($prefs['dark_theme'] ?? false) ? 'on' : '' }}" data-sw data-pref="dark_theme" id="darkSw"><i></i></span></div>
        <div class="srow" style="border-bottom:none"><span class="sr-ic ic-blue" data-ic="bolt"></span><span class="l-title" style="flex:1">رنگ برنامه</span>
            <span class="acc-dots" id="accDots"></span>
        </div>
    </div>

    <div class="sec-title">زبان و واحدها</div>
    <div class="card card-p">
        <div class="srow"><span class="sr-ic ic-green" data-ic="check"></span><span class="l-title" style="flex:1">زبان</span><span class="seg-mini" id="lang" data-pref="lang"><button type="button" data-v="fa" class="{{ ($prefs['lang'] ?? 'fa') === 'fa' ? 'active' : '' }}">فارسی</button><button type="button" data-v="en" class="{{ ($prefs['lang'] ?? 'fa') === 'en' ? 'active' : '' }}">English</button></span></div>
        <div class="srow" style="border-bottom:none"><span class="sr-ic ic-orange" data-ic="leaf"></span><span class="l-title" style="flex:1">واحد انرژی</span><span class="seg-mini" id="energyUnit" data-pref="energy_unit"><button type="button" data-v="kwh" class="{{ ($prefs['energy_unit'] ?? 'kwh') === 'kwh' ? 'active' : '' }}">kWh</button><button type="button" data-v="pct" class="{{ ($prefs['energy_unit'] ?? 'kwh') === 'pct' ? 'active' : '' }}">درصد</button></span></div>
    </div>

    <div class="sec-title">اعلان‌ها</div>
    <div class="card card-p">
        <div class="srow"><span class="sr-ic ic-blue" data-ic="bell"></span><span class="l-title" style="flex:1">پوش اپلیکیشن</span><span class="switch {{ ($prefs['notify_push'] ?? true) ? 'on' : '' }}" data-sw data-pref="notify_push"><i></i></span></div>
        <div class="srow"><span class="sr-ic ic-green" data-ic="bolt"></span><span class="l-title" style="flex:1">پیامک پایان شارژ</span><span class="switch {{ ($prefs['notify_sms_end'] ?? true) ? 'on' : '' }}" data-sw data-pref="notify_sms_end"><i></i></span></div>
        <div class="srow" style="border-bottom:none"><span class="sr-ic ic-orange" data-ic="wallet"></span><span class="l-title" style="flex:1">هشدار کمبود موجودی</span><span class="switch {{ ($prefs['notify_low_balance'] ?? true) ? 'on' : '' }}" data-sw data-pref="notify_low_balance"><i></i></span></div>
    </div>

    <div class="sec-title">ترجیحات شارژ</div>
    <div class="card card-p">
        <div class="srow"><span class="sr-ic ic-blue" data-ic="bolt"></span><div class="l-main"><div class="l-title">حد توقف خودکار شارژ</div><div class="l-sub">شارژ تا این درصد ادامه می‌یابد</div></div><span class="seg-mini" id="targetSoc" data-pref="charge_target_soc"><button type="button" data-v="80" class="{{ $targetSoc === 80 ? 'active' : '' }}">۸۰٪</button><button type="button" data-v="90" class="{{ $targetSoc === 90 ? 'active' : '' }}">۹۰٪</button><button type="button" data-v="100" class="{{ $targetSoc === 100 ? 'active' : '' }}">۱۰۰٪</button></span></div>
        <div class="srow" style="border-bottom:none"><span class="sr-ic ic-green" data-ic="scan"></span><span class="l-title" style="flex:1">شارژ خودکار با پلاک</span><span class="switch {{ ($prefs['plate_auto_charge'] ?? true) ? 'on' : '' }}" data-sw data-pref="plate_auto_charge"><i></i></span></div>
    </div>

    <div class="center-col mt20 mb"><span class="fs12 muted" id="saveStatus">SafirCharge · نسخهٔ ۲٫۴٫۱</span></div>
</div>
@endsection

@push('styles')
<style>
.srow{display:flex;align-items:center;gap:13px;padding:13px 0;border-bottom:1px solid var(--border-2)}
.sr-ic{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;flex:none}.sr-ic svg{width:20px;height:20px}
.l-title{font-weight:700;font-size:13.5px}.l-sub{font-size:11px;color:var(--muted);margin-top:2px}
.switch{width:46px;height:26px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;cursor:pointer;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .18s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-20px)}
.seg-mini{display:flex;background:var(--surface-3);border-radius:10px;padding:3px;gap:2px}
.seg-mini button{border:none;background:transparent;color:var(--text-2);font-size:11.5px;font-weight:700;padding:6px 10px;border-radius:8px;font-family:inherit}
.seg-mini button.active{background:var(--surface);color:var(--text);box-shadow:var(--shadow-sm)}
.acc-dots{display:flex;gap:7px}
.acc-dot{width:24px;height:24px;border-radius:50%;border:2px solid var(--surface);box-shadow:0 0 0 1px var(--border);padding:0;cursor:pointer}
.acc-dot.on{box-shadow:0 0 0 2px var(--accent)}
.mb{margin-bottom:8px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var saveUrl=@json(route('app.settings.update'));
  var csrf=document.querySelector('meta[name="csrf-token"]');
  var statusEl=document.getElementById('saveStatus');
  var saveTimer=null;

  function savePrefs(payload){
    if(!csrf)return;
    clearTimeout(saveTimer);
    saveTimer=setTimeout(function(){
      fetch(saveUrl,{
        method:'POST',
        headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf.getAttribute('content'),'X-Requested-With':'XMLHttpRequest'},
        body:JSON.stringify(payload)
      }).then(function(r){return r.json();}).then(function(){
        if(statusEl)statusEl.textContent='تنظیمات ذخیره شد';
        setTimeout(function(){if(statusEl)statusEl.textContent='SafirCharge · نسخهٔ ۲٫۴٫۱';},2000);
      }).catch(function(){});
    },350);
  }

  var darkFromServer=@json((bool) ($prefs['dark_theme'] ?? false));
  var darkFromStorage=localStorage.getItem('cn-theme')==='dark';
  if(darkFromServer||darkFromStorage)document.getElementById('darkSw').classList.add('on');
  if(darkFromStorage&&!darkFromServer)document.documentElement.setAttribute('data-theme','dark');

  var ACC=[{v:'#0E6BA8',rgb:'14, 107, 168'},{v:'#16A34A',rgb:'22, 163, 74'},{v:'#F97316',rgb:'249, 115, 22'},{v:'#7C3AED',rgb:'124, 58, 237'},{v:'#0891B2',rgb:'8, 145, 178'}];
  var cur=localStorage.getItem('cn-accent')||@json($prefs['accent'] ?? '#0E6BA8');
  document.getElementById('accDots').innerHTML=ACC.map(function(a){return '<button type="button" class="acc-dot'+(a.v===cur?' on':'')+'" data-v="'+a.v+'" data-rgb="'+a.rgb+'" style="background:'+a.v+'"></button>';}).join('');
  document.querySelectorAll('.acc-dot').forEach(function(d){
    d.addEventListener('click',function(){
      var v=d.getAttribute('data-v'),rgb=d.getAttribute('data-rgb');
      document.documentElement.style.setProperty('--accent',v);
      document.documentElement.style.setProperty('--accent-rgb',rgb);
      localStorage.setItem('cn-accent',v);
      document.querySelectorAll('.acc-dot').forEach(function(x){x.classList.remove('on');});d.classList.add('on');
      savePrefs({accent:v});
    });
  });

  document.querySelectorAll('[data-sw]').forEach(function(s){
    s.addEventListener('click',function(){
      s.classList.toggle('on');
      var key=s.getAttribute('data-pref');
      var on=s.classList.contains('on');
      if(s.id==='darkSw'){
        document.documentElement.setAttribute('data-theme',on?'dark':'light');
        localStorage.setItem('cn-theme',on?'dark':'light');
      }
      if(key){var p={};p[key]=on;savePrefs(p);}
    });
  });

  document.querySelectorAll('.seg-mini[data-pref]').forEach(function(g){
    var key=g.getAttribute('data-pref');
    g.querySelectorAll('button').forEach(function(b){
      b.addEventListener('click',function(){
        g.querySelectorAll('button').forEach(function(x){x.classList.remove('active');});
        b.classList.add('active');
        if(key){var p={};p[key]=b.getAttribute('data-v');if(key==='charge_target_soc')p[key]=parseInt(p[key],10);savePrefs(p);}
      });
    });
  });
})();
</script>
@endpush
