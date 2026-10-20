@php
    use App\Support\Persian;
    $tariff = $tariff ?? null;
    $modelKey = match($tariff?->pricing_model) {
        'per_minute' => 'time',
        'mixed' => 'hybrid',
        default => 'energy',
    };
    $priceToman = $tariff
        ? ($tariff->pricing_model === 'per_minute'
            ? (int) round($tariff->price_per_minute / 10)
            : (int) round($tariff->price_per_kwh / 10))
        : 3200;
@endphp
@extends('admin.layouts.app', ['page' => 'tariffs', 'title' => $tariff ? 'ویرایش تعرفه' : 'ایجاد تعرفهٔ جدید', 'crumb' => 'تعرفه‌ها و RFID'])

@section('content')
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <a href="{{ route('admin.tariffs.index') }}" class="btn btn-sm"><span class="bic" data-ic="dots"></span> بازگشت به تعرفه‌ها</a>
      @if($tariff)
      <form method="POST" action="{{ route('admin.tariffs.destroy', $tariff) }}" onsubmit="return confirm('این تعرفه حذف شود؟ جلسات قبلی همچنان به آن ارجاع دارند.');">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-sm" style="color:var(--red);border-color:var(--red-soft)"><span class="bic" data-ic="x"></span> حذف تعرفه</button>
      </form>
      @endif
    </div>

<form method="POST" action="{{ $tariff ? route('admin.tariffs.update', $tariff) : route('admin.tariffs.store') }}">@csrf
@if($tariff)@method('PUT')@endif

    <div class="grid col-2-1">
      <div style="display:flex;flex-direction:column;gap:16px">
        <input type="hidden" name="pricing_model" id="pricingModel" value="{{ old('pricing_model', $tariff?->pricing_model ?? 'per_kwh') }}">
        <input type="hidden" name="price_per_kwh" id="priceKwhHidden" value="{{ old('price_per_kwh', $tariff?->price_per_kwh ?? 32000) }}">
        <input type="hidden" name="price_per_minute" id="priceMinHidden" value="{{ old('price_per_minute', $tariff?->price_per_minute ?? 0) }}">
        <input type="hidden" name="status" id="statusHidden" value="{{ old('status', $tariff?->status ?? 'active') }}">
        <!-- مدل -->
        <div class="card">
          <div class="card-head"><h3>مدل قیمت‌گذاری</h3></div>
          <div class="card-pad">
            <div class="model-types" id="modelTypes">
              <button type="button" class="mt-card {{ $modelKey === 'energy' ? 'active' : '' }}" data-m="energy"><span class="mc-ic ic-blue" data-ic="bolt"></span><span class="fw7">انرژی‌محور</span><span class="fs12 muted">بر اساس کیلووات‌ساعت</span></button>
              <button type="button" class="mt-card {{ $modelKey === 'time' ? 'active' : '' }}" data-m="time"><span class="mc-ic ic-orange" data-ic="clock"></span><span class="fw7">زمان‌محور</span><span class="fs12 muted">بر اساس دقیقه</span></button>
              <button type="button" class="mt-card {{ $modelKey === 'hybrid' ? 'active' : '' }}" data-m="hybrid"><span class="mc-ic ic-violet" data-ic="sliders"></span><span class="fw7">ترکیبی</span><span class="fs12 muted">انرژی + اشغال</span></button>
            </div>
          </div>
        </div>

        <!-- مشخصات -->
        <div class="card">
          <div class="card-head"><h3>مشخصات و قیمت</h3></div>
          <div class="card-pad">
            <div class="form-grid">
              <div class="ffield col-span"><label>نام تعرفه <span class="req">*</span></label><input class="finput" name="name" id="tName" value="{{ old('name', $tariff?->name) }}" placeholder="مثلاً تعرفهٔ سریع شهری" required></div>
              <div class="ffield"><label>کد تعرفه</label><input class="finput tnum" name="code" dir="ltr" style="text-align:right" value="{{ old('code', $tariff?->code) }}" placeholder="TRF-001"></div>
              <div class="ffield"><label>قیمت پایه (تومان) <span class="req">*</span></label><input class="finput tnum" id="tPrice" dir="ltr" style="text-align:right" value="{{ Persian::num($priceToman) }}" required></div>
              <div class="ffield"><label>واحد</label><select class="fselect" id="tUnit"><option @selected($modelKey !== 'time')>تومان / kWh</option><option @selected($modelKey === 'time')>تومان / دقیقه</option></select></div>
              <div class="ffield"><label>وضعیت</label>
                <select class="fselect" id="tStatus">
                  <option value="active" @selected(old('status', $tariff?->status ?? 'active') === 'active')>فعال</option>
                  <option value="inactive" @selected(old('status', $tariff?->status) === 'inactive')>غیرفعال</option>
                  <option value="draft" @selected(old('status', $tariff?->status) === 'draft')>پیش‌نویس</option>
                </select>
              </div>
              <div class="ffield"><label class="toggle-row" style="margin:0;padding:12px 0"><span>تعرفهٔ پیش‌فرض شبکه</span><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $tariff?->is_default)) style="width:18px;height:18px"></label></div>
@php
    $peakRule = $tariff?->rules?->firstWhere('rule_type', 'peak');
    $offRule = $tariff?->rules?->firstWhere('rule_type', 'off_peak');
    $tMeta = $tariff?->meta ?? [];
@endphp
              <div class="ffield"><label>ضریب اوج مصرف (خالی = ندارد)</label><input class="finput tnum" name="peak_multiplier" type="number" step="0.01" min="0" dir="ltr" style="text-align:right" value="{{ old('peak_multiplier', $peakRule?->multiplier) }}" placeholder="1.40"></div>
              <div class="ffield"><label>ساعت اوج (شروع–پایان)</label><div class="row" style="gap:6px"><input class="finput tnum" name="peak_start" type="time" value="{{ old('peak_start', $peakRule ? substr((string) $peakRule->start_time, 0, 5) : '17:00') }}"><input class="finput tnum" name="peak_end" type="time" value="{{ old('peak_end', $peakRule ? substr((string) $peakRule->end_time, 0, 5) : '23:00') }}"></div></div>
              <div class="ffield"><label>ضریب کم‌باری (خالی = ندارد)</label><input class="finput tnum" name="offpeak_multiplier" type="number" step="0.01" min="0" dir="ltr" style="text-align:right" value="{{ old('offpeak_multiplier', $offRule?->multiplier) }}" placeholder="0.65"></div>
              <div class="ffield"><label>ساعت کم‌باری (شروع–پایان)</label><div class="row" style="gap:6px"><input class="finput tnum" name="offpeak_start" type="time" value="{{ old('offpeak_start', $offRule ? substr((string) $offRule->start_time, 0, 5) : '00:00') }}"><input class="finput tnum" name="offpeak_end" type="time" value="{{ old('offpeak_end', $offRule ? substr((string) $offRule->end_time, 0, 5) : '06:00') }}"></div></div>
              <div class="ffield"><label>هزینهٔ اشغال پس از پایان شارژ (ریال/دقیقه)</label><input class="finput tnum" name="idle_fee_per_minute" type="number" min="0" dir="ltr" style="text-align:right" value="{{ old('idle_fee_per_minute', $tMeta['idle_fee_per_minute'] ?? '') }}" placeholder="0"></div>
              <div class="ffield"><label>زمان مجاز رایگان (دقیقه)</label><input class="finput tnum" name="idle_grace_minutes" type="number" min="0" max="240" dir="ltr" style="text-align:right" value="{{ old('idle_grace_minutes', $tMeta['idle_grace_minutes'] ?? 10) }}"></div>
              <div class="ffield"><label>تخفیف اعضای سازمانی (٪)</label><input class="finput tnum" name="member_discount_percent" type="number" step="0.5" min="0" max="100" dir="ltr" style="text-align:right" value="{{ old('member_discount_percent', $tariff?->member_discount_percent ?? 0) }}"></div>
            </div>
          </div>
        </div>

        <!-- بازه ساعتی -->
        <div class="card">
          <div class="card-head"><div><h3>بازه‌های زمانی قیمت</h3><div class="ch-sub">قیمت‌گذاری اوج / عادی / کم‌باری</div></div></div>
          <div class="card-pad">
            <div class="time-row"><span class="tr-dot" style="background:var(--red)"></span><span class="fw7 fs13" style="flex:1">اوج مصرف</span><input class="finput tnum mini" dir="ltr" value="۱۷:۰۰–۲۲:۰۰"><input class="finput tnum mini" dir="ltr" value="۴٬۵۰۰"></div>
            <div class="time-row"><span class="tr-dot" style="background:var(--accent)"></span><span class="fw7 fs13" style="flex:1">عادی</span><input class="finput tnum mini" dir="ltr" value="۰۶:۰۰–۱۷:۰۰"><input class="finput tnum mini" dir="ltr" value="۳٬۲۰۰"></div>
            <div class="time-row"><span class="tr-dot" style="background:var(--green)"></span><span class="fw7 fs13" style="flex:1">کم‌باری</span><input class="finput tnum mini" dir="ltr" value="۰۰:۰۰–۰۶:۰۰"><input class="finput tnum mini" dir="ltr" value="۲٬۱۰۰"></div>
          </div>
        </div>

        <!-- اعمال -->
        <div class="card">
          <div class="card-head"><h3>اعمال تعرفه</h3></div>
          <div class="card-pad">
            <div class="form-grid">
              <div class="ffield"><label>نوع کانکتور</label><select class="fselect"><option>همه</option><option>DC سریع</option><option>AC</option></select></div>
              <div class="ffield"><label>اعمال بر ایستگاه‌ها</label><select class="fselect"><option>همهٔ ایستگاه‌ها</option><option>انتخابی</option><option>بر اساس اپراتور</option></select></div>
            </div>
            <label class="toggle-row mt-8"><div><div class="fw7 fs13">هزینهٔ اشغال پس از پایان شارژ</div><div class="fhint">۵۰۰ تومان به ازای هر دقیقه پس از شارژ کامل</div></div><span class="switch on" data-sw><i></i></span></label>
          </div>
        </div>
      </div>

      <!-- پیش‌نمایش -->
      <div style="display:flex;flex-direction:column;gap:16px;align-self:start;position:sticky;top:84px">
        <div class="card">
          <div class="card-head"><h3>پیش‌نمایش تعرفه</h3></div>
          <div class="card-pad">
            <div class="tariff-card" style="box-shadow:none;border-color:var(--border-2)">
              <div class="flex between items-center"><span class="pill pill-accent" id="pvBadge">انرژی‌محور</span></div>
              <h3 class="mt-12" id="pvName" style="font-size:16px">{{ $tariff?->name ?? 'تعرفهٔ جدید' }}</h3>
              <div class="muted fs12" id="pvDesc">{{ $tariff?->code ?? 'شارژ سریع شهری' }}</div>
              <div class="tprice tnum"><span id="pvPrice">{{ Persian::num($priceToman) }}</span> <small id="pvUnit">{{ $modelKey === 'time' ? 'تومان / دقیقه' : 'تومان / kWh' }}</small></div>
              <div class="tmeta"><span>{{ Persian::num($tariff?->charging_sessions_count ?? 0) }} جلسه</span><span class="pill {{ ($tariff?->status ?? 'draft') === 'active' ? 'pill-green' : 'pill-amber' }}" id="pvStatus">{{ ($tariff?->status ?? 'draft') === 'active' ? 'فعال' : ($tariff?->status === 'inactive' ? 'غیرفعال' : 'پیش‌نویس') }}</span></div>
            </div>
            <div class="legend mt-16">
              <div class="li"><span class="sw" style="background:var(--red)"></span>اوج (۱۷–۲۲)<b class="tnum" style="margin-inline-start:auto">۴٬۵۰۰</b></div>
              <div class="li"><span class="sw" style="background:var(--accent)"></span>عادی<b class="tnum" style="margin-inline-start:auto">۳٬۲۰۰</b></div>
              <div class="li"><span class="sw" style="background:var(--green)"></span>کم‌باری<b class="tnum" style="margin-inline-start:auto">۲٬۱۰۰</b></div>
            </div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="height:48px"><span class="bic" data-ic="{{ $tariff ? 'check' : 'plus' }}"></span> {{ $tariff ? 'ذخیرهٔ تغییرات' : 'ثبت و فعال‌سازی تعرفه' }}</button>
      </div>
    </div>
</form>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.model-types{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.mt-card{display:flex;flex-direction:column;align-items:center;gap:6px;text-align:center;padding:16px 8px;border:1.5px solid var(--border);border-radius:14px;background:var(--surface);font-family:inherit}
.mt-card.active{border-color:var(--accent);background:var(--accent-soft)}
.mc-ic{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;margin-bottom:3px}.mc-ic svg{width:21px;height:21px}
.time-row{display:flex;align-items:center;gap:11px;padding:9px 0;border-bottom:1px solid var(--border-2)}
.time-row:last-child{border-bottom:none}
.tr-dot{width:11px;height:11px;border-radius:50%;flex:none}
.finput.mini{width:120px;height:38px;font-size:12.5px;flex:none}
.toggle-row{display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:12px;padding:13px 15px;cursor:pointer}
.switch{width:44px;height:25px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:19px;height:19px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .16s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-19px)}
.tariff-card{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:18px}
.tariff-card h3{font-size:16px}
.tprice{font-size:26px;font-weight:800;margin:14px 0 4px}.tprice small{font-size:13px;font-weight:600;color:var(--text-2)}
.tmeta{display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid var(--border-2);font-size:12px;color:var(--muted)}
.legend .li{display:flex;align-items:center;gap:9px;font-size:12.5px}.legend .sw{width:11px;height:11px;border-radius:4px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  var badge={energy:['انرژی‌محور','pill-accent','شارژ سریع شهری'],time:['زمان‌محور','pill-orange','شارژ مقصد'],hybrid:['ترکیبی','pill','انرژی + اشغال']};
  var modelMap={energy:'per_kwh',time:'per_minute',hybrid:'mixed'};
  function parseNum(v){return parseInt(String(v).replace(/[^0-9]/g,''),10)||0;}
  function syncPrice(){
    var toman=parseNum(document.getElementById('tPrice').value);
    var model=document.getElementById('pricingModel').value;
    document.getElementById('priceKwhHidden').value=model==='per_minute'?0:toman*10;
    document.getElementById('priceMinHidden').value=model==='per_minute'?toman*10:0;
  }
  document.querySelectorAll('#modelTypes .mt-card').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#modelTypes .mt-card').forEach(function(x){x.classList.remove('active');});b.classList.add('active');
      var key=b.getAttribute('data-m');
      document.getElementById('pricingModel').value=modelMap[key]||'per_kwh';
      var m=badge[key];
      var pb=document.getElementById('pvBadge');pb.textContent=m[0];pb.className='pill '+m[1];
      document.getElementById('pvDesc').textContent=m[2];
      syncPrice();
    });
  });
  document.getElementById('tName').addEventListener('input',function(){document.getElementById('pvName').textContent=this.value||'تعرفهٔ جدید';});
  document.getElementById('tPrice').addEventListener('input',function(){document.getElementById('pvPrice').textContent=this.value||'۰';syncPrice();});
  syncPrice();
  document.getElementById('tUnit').addEventListener('change',function(){document.getElementById('pvUnit').textContent=this.value;});
  document.getElementById('tStatus').addEventListener('change',function(){
    document.getElementById('statusHidden').value=this.value;
    var pill=document.getElementById('pvStatus');
    var labels={active:['فعال','pill-green'],inactive:['غیرفعال','pill-amber'],draft:['پیش‌نویس','pill-amber']};
    var l=labels[this.value]||labels.draft;
    pill.textContent=l[0];pill.className='pill '+l[1];
  });
  document.querySelectorAll('[data-sw]').forEach(function(s){s.closest('.toggle-row').addEventListener('click',function(){s.classList.toggle('on');});});
  var initKey='{{ $modelKey }}';
  if(initKey&&badge[initKey]){
    var m=badge[initKey];
    var pb=document.getElementById('pvBadge');pb.textContent=m[0];pb.className='pill '+m[1];
    document.getElementById('pvDesc').textContent=document.querySelector('input[name=code]')?.value||m[2];
    syncPrice();
  }
})();
</script>
@endpush
