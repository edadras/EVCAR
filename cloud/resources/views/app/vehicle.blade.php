@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — خودروهای من')

@section('phone')
@php
use App\Support\Persian;
use App\Models\EvCatalogVehicle;
$plateLetters = ['الف','ب','ج','د','س','ص','ط','ق','ل','م','ن','و','ه','ی','معلولین'];
$catalogModels = EvCatalogVehicle::query()
    ->where('is_active', true)
    ->orderBy('name')
    ->pluck('name')
    ->all();
$modelOptions = collect($catalogModels)
    ->merge($vehicles->map(fn ($v) => trim("{$v->brand} {$v->model}")))
    ->unique()
    ->filter()
    ->values();
@endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.account') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">خودروهای من</div></div>
</div>

<div class="screen">
    @if(session('success'))
    <div class="flash ok">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="flash err">{{ $errors->first() }}</div>
    @endif

    <div class="fs12 muted" style="margin-bottom:12px">خودروی پیش‌فرض برای شارژ خودکار با پلاک استفاده می‌شود.</div>

    <div style="display:flex;flex-direction:column;gap:12px">
        @forelse($vehicles as $v)
        <div class="gveh {{ $v->is_default ? 'default' : '' }}">
            <div class="gv-top">
                <div class="gv-ic" data-ic="car"></div>
                <div style="flex:1;min-width:0">
                    <div class="fw7" style="font-size:15px">{{ trim("{$v->brand} {$v->model}") }}</div>
                    <div class="fs12 muted mt4">CCS2 · {{ Persian::digits((int) $v->battery_kwh) }} kWh</div>
                </div>
                @if($v->is_default)
                <span class="pill pill-accent gv-badge">پیش‌فرض</span>
                @endif
            </div>
            <div class="gv-bottom">
                <span class="plate-wrap">@include('app.partials.plate', ['v' => $v])</span>
                <div class="gv-actions">
                    @if(!$v->is_default)
                    <form method="POST" action="{{ route('app.vehicle.default', $v) }}" class="inline-form">
                        @csrf
                        <button type="submit" class="set-def">پیش‌فرض</button>
                    </form>
                    @endif
                    <button type="button" class="gv-edit" data-edit
                        data-id="{{ $v->id }}"
                        data-brand-model="{{ trim("{$v->brand} {$v->model}") }}"
                        data-plate-two="{{ Persian::digits($v->plate_two) }}"
                        data-plate-letter="{{ $v->plate_letter }}"
                        data-plate-three="{{ Persian::digits($v->plate_three) }}"
                        data-plate-province="{{ Persian::digits($v->plate_province) }}"
                        data-battery="{{ (int) $v->battery_kwh }}"
                        data-default="{{ $v->is_default ? '1' : '0' }}">ویرایش</button>
                    <form method="POST" action="{{ route('app.vehicle.destroy', $v) }}" class="inline-form" onsubmit="return confirm('این خودرو حذف شود؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="gv-del" title="حذف">✕</button>
                    </form>
                </div>
            </div>
        </div>
        @empty
        <p class="muted fs13">خودرویی ثبت نشده است.</p>
        @endforelse
    </div>

    <button type="button" class="add-veh mt16" id="addBtn"><span data-ic="plus"></span> افزودن خودروی جدید</button>

    @include('app.partials.change-history', ['auditRows' => $auditRows ?? []])
</div>
@endsection

@push('overlays')
<div class="sheet-overlay veh-sheet" id="sheet">
    <form method="POST" action="{{ route('app.vehicle.store') }}" id="vehForm" class="veh-sheet-form">
        @csrf
        <input type="hidden" name="_method" id="formMethod" value="POST" disabled>
        <div class="sheet" id="vehSheetPanel">
            <div class="sheet-grab"></div>
            <div class="sheet-head">
                <h3 id="sheetTitle">افزودن خودرو</h3>
                <button type="button" class="ab-btn" id="closeSheet"><span data-ic="back"></span></button>
            </div>

            <label class="fld-l" for="modelSel">برند و مدل</label>
            <select class="input" name="brand_model" id="modelSel" required>
                @foreach($modelOptions as $m)
                <option value="{{ $m }}">{{ $m }}</option>
                @endforeach
            </select>

            <label class="fld-l mt16" for="batteryInput">ظرفیت باتری (kWh)</label>
            <input class="input tnum" type="number" name="battery_kwh" id="batteryInput" min="10" max="200" value="60" inputmode="numeric">

            <label class="fld-l mt16">شماره پلاک</label>
            <div class="plate-edit" id="plateEdit">
                <span class="pe-flag" aria-hidden="true"><i></i><i></i><i></i></span>
                <span class="pe-seg"><input class="pe-d2 tnum" id="plateTwo" name="plate_two" maxlength="2" value="۱۲" inputmode="numeric" autocomplete="off" required><small>دو رقم</small></span>
                <span class="pe-seg">
                    <select class="pe-let" id="plateLetter" name="plate_letter">
                        @foreach($plateLetters as $l)
                        <option @selected($l === 'س')>{{ $l }}</option>
                        @endforeach
                    </select>
                    <small>حرف</small>
                </span>
                <span class="pe-seg"><input class="pe-d3 tnum" id="plateThree" name="plate_three" maxlength="3" value="۳۴۵" inputmode="numeric" autocomplete="off" required><small>سه رقم</small></span>
                <span class="pe-seg"><input class="pe-prov tnum" id="plateProvince" name="plate_province" maxlength="2" value="۱۱" inputmode="numeric" autocomplete="off" required><small>استان</small></span>
            </div>

            <input type="hidden" name="is_default" value="0" id="isDefault">
            <div class="set-default">
                <span class="fw7 fs13">تنظیم به‌عنوان خودروی پیش‌فرض</span>
                <span class="switch" id="defSw" role="switch" tabindex="0" aria-checked="false"><i></i></span>
            </div>

            <button type="submit" class="btn btn-orange sheet-cta" id="saveBtn"><span data-ic="check"></span> ذخیره خودرو</button>
        </div>
    </form>
</div>
@endpush

@push('styles')
<style>
.flash{padding:12px 14px;border-radius:12px;font-size:13px;margin-bottom:12px}
.flash.ok{background:var(--green-soft);color:var(--green);border:1px solid color-mix(in srgb,var(--green) 25%,transparent)}
.flash.err{background:var(--red-soft);color:var(--red);border:1px solid color-mix(in srgb,var(--red) 25%,transparent)}
.gveh{display:flex;flex-direction:column;gap:12px;padding:16px;border:1.5px solid var(--border);border-radius:18px;background:var(--surface);box-shadow:var(--shadow-sm)}
.gveh.default{border-color:var(--accent)}
.gv-top{display:flex;align-items:center;gap:13px}
.gv-ic{width:48px;height:48px;border-radius:14px;display:grid;place-items:center;flex:none;background:var(--accent-soft);color:var(--accent-strong)}.gv-ic svg{width:24px;height:24px}
.gv-badge{margin-inline-start:auto;flex:none}
.gv-bottom{display:flex;align-items:center;justify-content:space-between;gap:10px;padding-top:12px;border-top:1px solid var(--border-2);flex-wrap:wrap}
.gv-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-inline-start:auto}
.inline-form{display:inline;margin:0}
.gv-del{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:var(--surface);color:var(--red);display:grid;place-items:center;flex:none;cursor:pointer;font-family:inherit;font-size:14px;line-height:1}
.gv-edit{border:1px solid var(--border);background:var(--surface-2);color:var(--text);font-family:inherit;font-weight:700;font-size:12px;padding:7px 12px;border-radius:9px;cursor:pointer}
.set-def{border:1px solid var(--accent);background:var(--accent-soft);color:var(--accent-strong);font-family:inherit;font-weight:700;font-size:12px;padding:7px 12px;border-radius:9px;cursor:pointer}
.add-veh{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;height:50px;border-radius:15px;border:1.5px dashed var(--border);background:var(--surface-2);color:var(--accent);font-weight:700;font-size:14px;cursor:pointer;font-family:inherit}
.add-veh svg{width:18px;height:18px}
.veh-sheet{position:fixed;inset:0;background:rgba(8,16,24,.5);backdrop-filter:blur(2px);display:flex;align-items:flex-end;justify-content:center;opacity:0;visibility:hidden;transition:opacity .22s;z-index:200}
.veh-sheet.open{opacity:1;visibility:visible}
.veh-sheet-form{width:100%;max-width:var(--phone-w);transform:translateY(100%);transition:transform .28s cubic-bezier(.2,.7,.3,1)}
.veh-sheet.open .veh-sheet-form{transform:none}
.veh-sheet .sheet{width:100%;max-height:min(92dvh,820px);overflow-y:auto;-webkit-overflow-scrolling:touch;background:var(--surface);border-radius:26px 26px 0 0;padding:10px 20px calc(20px + env(safe-area-inset-bottom,0))}
.sheet-grab{width:42px;height:5px;border-radius:5px;background:var(--border);margin:4px auto 12px}
.sheet-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}.sheet-head h3{font-size:17px}
.fld-l{display:block;font-size:12.5px;color:var(--muted);font-weight:600;margin-bottom:8px}
.input{width:100%;border:1.5px solid var(--border);background:var(--surface-2);border-radius:13px;padding:0 14px;height:50px;font-family:inherit;font-size:14px;color:var(--text);outline:none;appearance:none}
.input:focus{border-color:var(--accent)}
.plate-edit{display:flex;align-items:stretch;min-height:74px;border:2px solid var(--border);border-radius:13px;overflow:hidden;background:var(--surface-2);direction:ltr;touch-action:manipulation}
.plate-edit:focus-within{border-color:var(--accent)}
.pe-flag{width:30px;flex:none;background:#0b5fa5;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;padding-bottom:6px;gap:2px;pointer-events:none}
.pe-flag i{width:14px;height:3px;border-radius:1px}.pe-flag i:nth-child(1){background:#16A34A}.pe-flag i:nth-child(2){background:#fff}.pe-flag i:nth-child(3){background:#DC2626}
.pe-seg{flex:1;min-width:0;display:flex;flex-direction:column;align-items:stretch;justify-content:center;gap:2px;border-left:1px solid var(--border);padding:6px 2px 4px;position:relative}
.pe-seg:last-child{border-left:none}
.pe-seg input,.pe-seg select{position:relative;z-index:1;flex:1;min-height:36px;border:none;background:transparent;font-family:inherit;font-weight:800;font-size:21px;text-align:center;color:var(--text);outline:none;width:100%;padding:0 4px;-webkit-appearance:none;appearance:none;touch-action:manipulation}
.pe-seg .pe-let{font-size:17px;color:var(--accent)}
.pe-seg small{position:relative;z-index:0;font-size:9px;color:var(--muted);font-weight:600;text-align:center;pointer-events:none;user-select:none}
.set-default{display:flex;align-items:center;justify-content:space-between;margin-top:18px;padding:13px 15px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:13px}
.switch{width:46px;height:26px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;cursor:pointer}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .18s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-20px)}
.sheet-cta{width:100%;height:52px;margin-top:20px;font-size:15px}
.hist-card{border:1px solid var(--border);border-radius:16px;background:var(--surface);padding:4px 14px;margin-bottom:24px}
.hist-row{display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-2)}
.hist-row:last-child{border-bottom:none}
.hist-ic{width:36px;height:36px;border-radius:10px;display:grid;place-items:center;flex:none}.hist-ic svg{width:17px;height:17px}
.hist-ok{background:var(--green-soft);color:var(--green)}
.hist-warn{background:var(--red-soft);color:var(--red)}
.hist-info{background:var(--accent-soft);color:var(--accent-strong)}
.hist-main{flex:1;min-width:0}
.hist-time{flex:none;white-space:nowrap}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var sheet=document.getElementById('sheet');
  var panel=document.getElementById('vehSheetPanel');
  var form=document.getElementById('vehForm');
  var storeUrl=@json(route('app.vehicle.store'));
  var updateTpl=@json(route('app.vehicle.update', ['vehicle' => '__ID__']));
  var defSw=document.getElementById('defSw');
  var isDefault=document.getElementById('isDefault');
  var formMethod=document.getElementById('formMethod');
  var plateTwo=document.getElementById('plateTwo');
  var plateLetter=document.getElementById('plateLetter');
  var plateThree=document.getElementById('plateThree');
  var plateProvince=document.getElementById('plateProvince');

  function setDefaultSwitch(on){
    defSw.classList.toggle('on',on);
    defSw.setAttribute('aria-checked',on?'true':'false');
    isDefault.value=on?'1':'0';
  }

  function setPlateField(el,value){
    if(!el||value==null)return;
    el.value=value;
    if(el.tagName==='SELECT'){
      if(![].some.call(el.options,function(o){return o.value===value;})){
        var opt=document.createElement('option');
        opt.value=value;
        opt.textContent=value;
        el.appendChild(opt);
      }
      el.value=value;
    }
  }

  function normalizeDigits(value,max){
    return String(value||'').replace(/[^\d۰-۹]/g,'').slice(0,max);
  }

  function bindPlateInputs(){
    plateTwo.addEventListener('input',function(){plateTwo.value=normalizeDigits(plateTwo.value,2);});
    plateThree.addEventListener('input',function(){plateThree.value=normalizeDigits(plateThree.value,3);});
    plateProvince.addEventListener('input',function(){plateProvince.value=normalizeDigits(plateProvince.value,2);});
  }

  function batteryValue(raw){
    var n=parseInt(raw||'60',10);
    return (isNaN(n)||n<10)?60:n;
  }

  function openSheet(){
    sheet.classList.add('open');
    document.body.style.overflow='hidden';
    document.querySelectorAll('#sheet [data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  }

  function closeSheet(){
    sheet.classList.remove('open');
    document.body.style.overflow='';
  }

  function openAdd(){
    document.getElementById('sheetTitle').textContent='افزودن خودرو';
    document.getElementById('saveBtn').innerHTML='<span data-ic="check"></span> افزودن خودرو';
    form.action=storeUrl;
    formMethod.disabled=true;
    formMethod.value='POST';
    document.getElementById('modelSel').selectedIndex=0;
    document.getElementById('batteryInput').value='60';
    setPlateField(plateTwo,'۱۲');
    setPlateField(plateLetter,'س');
    setPlateField(plateThree,'۳۴۵');
    setPlateField(plateProvince,'۱۱');
    setDefaultSwitch(true);
    openSheet();
  }

  function openEdit(btn){
    document.getElementById('sheetTitle').textContent='ویرایش خودرو';
    document.getElementById('saveBtn').innerHTML='<span data-ic="check"></span> ذخیره تغییرات';
    form.action=updateTpl.replace('__ID__',btn.getAttribute('data-id'));
    formMethod.disabled=false;
    formMethod.value='PUT';
    var model=btn.getAttribute('data-brand-model');
    var sel=document.getElementById('modelSel');
    var found=false;
    for(var i=0;i<sel.options.length;i++){if(sel.options[i].value===model){sel.selectedIndex=i;found=true;break;}}
    if(!found){var o=document.createElement('option');o.value=model;o.textContent=model;sel.appendChild(o);sel.value=model;}
    document.getElementById('batteryInput').value=String(batteryValue(btn.getAttribute('data-battery')));
    setPlateField(plateTwo,btn.getAttribute('data-plate-two'));
    setPlateField(plateLetter,btn.getAttribute('data-plate-letter'));
    setPlateField(plateThree,btn.getAttribute('data-plate-three'));
    setPlateField(plateProvince,btn.getAttribute('data-plate-province'));
    setDefaultSwitch(btn.getAttribute('data-default')==='1');
    openSheet();
  }

  bindPlateInputs();
  document.getElementById('addBtn').addEventListener('click',openAdd);
  document.querySelectorAll('[data-edit]').forEach(function(btn){btn.addEventListener('click',function(){openEdit(btn);});});
  document.getElementById('closeSheet').addEventListener('click',closeSheet);
  sheet.addEventListener('click',function(e){if(e.target===sheet)closeSheet();});
  panel.addEventListener('click',function(e){e.stopPropagation();});
  defSw.addEventListener('click',function(e){e.preventDefault();setDefaultSwitch(!defSw.classList.contains('on'));});
  document.querySelectorAll('.hist-ic[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
})();
</script>
@endpush
