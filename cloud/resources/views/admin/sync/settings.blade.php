@extends('admin.layouts.app', ['page' => 'sync', 'title' => 'تنظیمات همگام‌سازی', 'crumb' => 'سامانه و ارتباطات'])

@section('content')
@php
  use App\Support\Persian;
  $cfg = $syncConfig ?? ['sync_interval_seconds' => 30, 'batch_size' => 500, 'retry_enabled' => true, 'offline_queue_enabled' => true];
@endphp
<form method="POST" action="{{ route('admin.sync.settings.update') }}">@csrf

<div class="flex items-center gap-12 mt-8" style="margin-bottom:18px">
      <a href="/admin/sync" class="btn btn-sm"><span class="bic" data-ic="dots"></span> بازگشت به همگام‌سازی</a>
    </div>

    <div class="grid col-2-1">
      <div style="display:flex;flex-direction:column;gap:16px">
        <!-- استراتژی همگام‌سازی -->
        <div class="card">
          <div class="card-head"><div><h3>استراتژی همگام‌سازی</h3><div class="ch-sub">نحوهٔ ارسال رویدادها از کنترلر لبه به سرور مرکزی</div></div></div>
          <div class="card-pad">
            <div class="model-types" id="strategy">
              <button type="button" class="mt-card active" data-s="event"><span class="mc-ic ic-blue" data-ic="activity"></span><span class="fw7">رویدادمحور</span><span class="fs12 muted">ارسال آنی هر رویداد</span></button>
              <button type="button" class="mt-card" data-s="interval"><span class="mc-ic ic-green" data-ic="clock"></span><span class="fw7">زمان‌بندی‌شده</span><span class="fs12 muted">در بازه‌های منظم</span></button>
              <button type="button" class="mt-card" data-s="hybrid"><span class="mc-ic ic-violet" data-ic="refresh"></span><span class="fw7">ترکیبی</span><span class="fs12 muted">آنی + پشتیبان دوره‌ای</span></button>
            </div>
            <div class="form-grid mt-16">
              <div class="ffield"><label>بازهٔ همگام‌سازی (ثانیه)</label><input class="finput tnum" name="sync_interval_seconds" dir="ltr" style="text-align:right" value="{{ $cfg['sync_interval_seconds'] ?? 30 }}" required></div>
              <div class="ffield"><label>اندازهٔ دسته (batch)</label><input class="finput tnum" name="batch_size" dir="ltr" style="text-align:right" value="{{ $cfg['batch_size'] ?? 500 }}" required></div>
              <div class="ffield"><label>تلاش مجدد</label><label class="toggle-row" style="margin:0"><div class="fw7 fs13">فعال</div><input type="hidden" name="retry_enabled" value="0"><input type="checkbox" name="retry_enabled" value="1" {{ ($cfg['retry_enabled'] ?? true) ? 'checked' : '' }} style="width:18px;height:18px"></label></div>
              <div class="ffield"><label>صف آفلاین</label><label class="toggle-row" style="margin:0"><div class="fw7 fs13">فعال</div><input type="hidden" name="offline_queue_enabled" value="0"><input type="checkbox" name="offline_queue_enabled" value="1" {{ ($cfg['offline_queue_enabled'] ?? true) ? 'checked' : '' }} style="width:18px;height:18px"></label></div>
            </div>
          </div>
        </div>

        <!-- حالت آفلاین -->
        <div class="card">
          <div class="card-head"><h3>حالت آفلاین کنترلرها</h3></div>
          <div class="card-pad">
            <label class="toggle-row"><div><div class="fw7 fs13">اجازهٔ شارژ در حالت آفلاین</div><div class="fhint">ادامهٔ سرویس هنگام قطع ارتباط ایستگاه با سرور</div></div><span class="switch on" data-sw><i></i></span></label>
            <label class="toggle-row mt-12"><div><div class="fw7 fs13">ثبت‌نام محلی کاربر (OTP با مودم GSM)</div><div class="fhint">امکان عضویت کاربر جدید بدون اینترنت</div></div><span class="switch on" data-sw><i></i></span></label>
            <label class="toggle-row mt-12"><div><div class="fw7 fs13">احراز کارت RFID به‌صورت آفلاین</div><div class="fhint">اعتبارسنجی محلی کارت‌های مجاز</div></div><span class="switch on" data-sw><i></i></span></label>
            <div class="form-grid mt-16">
              <div class="ffield"><label>سقف تراکنش آفلاین هر کاربر</label><input class="finput tnum" dir="ltr" style="text-align:right" value="۳ تراکنش"></div>
              <div class="ffield"><label>حداکثر مدت کارکرد آفلاین</label><select class="fselect"><option>۶ ساعت</option><option selected>۲۴ ساعت</option><option>۷۲ ساعت</option><option>نامحدود</option></select></div>
            </div>
          </div>
        </div>

        <!-- حل تعارض -->
        <div class="card">
          <div class="card-head"><div><h3>حل تعارض داده‌ها</h3><div class="ch-sub">هنگام ناهماهنگی بین صف محلی و ابری</div></div></div>
          <div class="card-pad">
            <div class="radio-row" data-radio="conflict">
              <label class="rdo active"><span class="rdo-dot"></span><div><div class="fw7 fs13">اولویت با سرور مرکزی</div><div class="fhint">دادهٔ ابری معتبر است</div></div></label>
              <label class="rdo"><span class="rdo-dot"></span><div><div class="fw7 fs13">اولویت با کنترلر لبه</div><div class="fhint">دادهٔ محلی معتبر است</div></div></label>
              <label class="rdo"><span class="rdo-dot"></span><div><div class="fw7 fs13">جدیدترین زمان (timestamp)</div><div class="fhint">رکورد با زمان بالاتر</div></div></label>
            </div>
          </div>
        </div>
      </div>

      <!-- وضعیت + ذخیره -->
      <div style="display:flex;flex-direction:column;gap:16px;align-self:start;position:sticky;top:84px">
        <div class="card">
          <div class="card-head"><h3>وضعیت فعلی موتور</h3></div>
          <div class="card-pad">
            <div class="lrow"><div class="l-ic ic-green" data-ic="activity"></div><div class="l-main"><div class="l-title">موتور همگام‌سازی</div><div class="l-sub">رویدادمحور · فعال</div></div><span class="pill pill-green"><span class="pdot"></span>سالم</span></div>
            <div class="lrow"><div class="l-ic ic-blue" data-ic="refresh"></div><div class="l-main"><div class="l-title">بازهٔ Sync</div></div><b class="tnum">{{ Persian::num($cfg['sync_interval_seconds'] ?? 30) }} ثانیه</b></div>
            <div class="lrow"><div class="l-ic ic-orange" data-ic="activity"></div><div class="l-main"><div class="l-title">رویداد در صف</div></div><b class="tnum">{{ Persian::num($pendingQueue ?? 0) }}</b></div>
            <div class="lrow" style="border-bottom:none"><div class="l-ic ic-violet" data-ic="station"></div><div class="l-main"><div class="l-title">کنترلر آفلاین</div></div><b class="tnum">{{ Persian::num($offlineEdges ?? 0) }}</b></div>
          </div>
        </div>
        <button type="submit" class="btn btn-primary" style="height:48px"><span class="bic" data-ic="check"></span> ذخیرهٔ تنظیمات</button>
        <button type="button" class="btn"><span class="bic" data-ic="refresh"></span> همگام‌سازی فوری همه</button>
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
.ic-violet{background:color-mix(in srgb,var(--violet) 12%,white);color:var(--violet)}
html[data-theme="dark"] .ic-violet{background:color-mix(in srgb,var(--violet) 26%,#0e1a26);color:#b794f4}
.toggle-row{display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:12px;padding:13px 15px;cursor:pointer}
.switch{width:44px;height:25px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:19px;height:19px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .16s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-19px)}
.radio-row{display:flex;flex-direction:column;gap:10px}
.rdo{display:flex;align-items:flex-start;gap:11px;padding:13px 15px;border:1.5px solid var(--border);border-radius:12px;cursor:pointer;transition:all .12s}
.rdo.active{border-color:var(--accent);background:var(--accent-soft)}
.rdo-dot{width:20px;height:20px;border-radius:50%;border:2px solid var(--border);flex:none;margin-top:1px;display:grid;place-items:center}
.rdo.active .rdo-dot{border-color:var(--accent)}.rdo.active .rdo-dot::after{content:"";width:10px;height:10px;border-radius:50%;background:var(--accent)}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  document.querySelectorAll('#strategy .mt-card').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('#strategy .mt-card').forEach(function(x){x.classList.remove('active');});b.classList.add('active');});});
  document.querySelectorAll('[data-sw]').forEach(function(s){s.closest('.toggle-row').addEventListener('click',function(){s.classList.toggle('on');});});
  document.querySelectorAll('[data-radio] .rdo').forEach(function(r){r.addEventListener('click',function(){r.parentElement.querySelectorAll('.rdo').forEach(function(x){x.classList.remove('active');});r.classList.add('active');});});
})();
</script>
@endpush
