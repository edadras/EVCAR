@extends('admin.layouts.app', ['page' => 'notifications', 'title' => 'ارسال اعلان جدید', 'crumb' => 'اعلان‌ها'])

@section('content')
<form method="POST" action="{{ route('admin.notifications.store') }}">@csrf
@php use App\Support\Persian; @endphp

<div class="flex items-center gap-12 mt-8" style="margin-bottom:18px">
      <a href="/admin/notifications" class="btn btn-sm"><span class="bic" data-ic="dots"></span> بازگشت به اعلان‌ها</a>
    </div>

    <div class="grid col-2-1">
      <div style="display:flex;flex-direction:column;gap:16px">
        <!-- محتوا -->
        <div class="card">
          <div class="card-head"><div><h3>محتوای اعلان</h3></div></div>
          <div class="card-pad">
            <div class="form-grid">
              <input type="hidden" name="channel" id="channelInput" value="push">
              <input type="hidden" name="audience" id="audienceInput" value="all">
              <div class="ffield col-span"><label>عنوان <span class="req">*</span></label><input class="finput" name="title" id="nTitle" maxlength="120" required placeholder="عنوان اعلان"></div>
              <div class="ffield col-span"><label>متن پیام <span class="req">*</span></label><textarea class="ftext" name="body" id="nBody" maxlength="500" required placeholder="متن پیام…"></textarea><div class="fhint" id="cnt">۰ / ۵۰۰ کاراکتر</div></div>
              <div class="ffield"><label>آیکون / نوع</label><select class="fselect" name="type" id="nType"><option value="bolt">⚡ اطلاع‌رسانی</option><option value="coins">🎁 پیشنهاد و تخفیف</option><option value="alert">⚠️ هشدار</option><option value="leaf">🌱 خبر سبز</option></select></div>
              <div class="ffield"><label>پیوند مقصد (در اپ)</label><select class="fselect" name="deep_link"><option value="">بدون پیوند</option><option value="map">صفحهٔ نقشه</option><option value="wallet">کیف پول</option><option value="plans">صفحهٔ طرح‌ها</option></select></div>
            </div>
          </div>
        </div>

        <!-- مخاطبان -->
        <div class="card">
          <div class="card-head"><div><h3>مخاطبان</h3><div class="ch-sub">انتخاب گروه هدف</div></div></div>
          <div class="card-pad">
            <div class="aud-grid" id="audGrid">
              @php $counts = $audienceCounts ?? []; @endphp
              <button type="button" class="aud active" data-aud="all" data-n="{{ $counts['all'] ?? 0 }}"><span class="fw7">همهٔ کاربران</span><span class="fs12 muted">{{ Persian::num($counts['all'] ?? 0) }} نفر</span></button>
              <button type="button" class="aud" data-aud="active" data-n="{{ $counts['active'] ?? 0 }}"><span class="fw7">کاربران فعال</span><span class="fs12 muted">{{ Persian::num($counts['active'] ?? 0) }} نفر</span></button>
              <button type="button" class="aud" data-aud="org" data-n="{{ $counts['org'] ?? 0 }}"><span class="fw7">سازمانی</span><span class="fs12 muted">{{ Persian::num($counts['org'] ?? 0) }} نفر</span></button>
              <button type="button" class="aud" data-aud="tehran" data-n="{{ $counts['tehran'] ?? 0 }}"><span class="fw7">کاربران تهران</span><span class="fs12 muted">{{ Persian::num($counts['tehran'] ?? 0) }} نفر</span></button>
              <button type="button" class="aud" data-aud="low_balance" data-n="{{ $counts['low_balance'] ?? 0 }}"><span class="fw7">موجودی کم</span><span class="fs12 muted">{{ Persian::num($counts['low_balance'] ?? 0) }} نفر</span></button>
            </div>
          </div>
        </div>

        <!-- کانال + زمان -->
        <div class="card">
          <div class="card-head"><div><h3>کانال و زمان‌بندی</h3></div></div>
          <div class="card-pad">
            <div class="ffield" style="margin-bottom:14px"><label>کانال‌های ارسال</label>
              <div class="flex gap-8" id="chans">
                <button type="button" class="chan-chip on" data-c="push">پوش</button>
                <button type="button" class="chan-chip" data-c="sms">پیامک</button>
                <button type="button" class="chan-chip" data-c="wa">واتس‌اپ</button>
                <button type="button" class="chan-chip" data-c="email">ایمیل</button>
              </div>
            </div>
            <div class="radio-row" data-radio="time">
              <label class="rdo active"><span class="rdo-dot"></span><div><div class="fw7 fs13">ارسال فوری</div><div class="fhint">بلافاصله پس از تأیید</div></div></label>
              <label class="rdo"><span class="rdo-dot"></span><div><div class="fw7 fs13">زمان‌بندی‌شده</div><div class="fhint">تعیین تاریخ و ساعت ارسال</div></div></label>
            </div>
          </div>
        </div>
      </div>

      <!-- پیش‌نمایش گوشی -->
      <div style="display:flex;flex-direction:column;gap:16px;align-self:start;position:sticky;top:84px">
        <div class="card">
          <div class="card-head"><h3>پیش‌نمایش زنده</h3></div>
          <div class="card-pad" style="display:flex;justify-content:center">
            <div class="mini-phone">
              <div class="mp-status"><span class="tnum">۹:۴۱</span><span>SafirCharge</span></div>
              <div class="mp-notif">
                <div class="mp-ic" id="pvIc"></div>
                <div style="flex:1;min-width:0">
                  <div class="mp-app">SafirCharge · اکنون</div>
                  <div class="mp-title" id="pvTitle">شارژرهای جدید در شهر شما!</div>
                  <div class="mp-body" id="pvBody">۴ ایستگاه شارژ سریع جدید در تهران افتتاح شد.</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="card card-pad">
          <div class="flex between" style="font-size:13px"><span class="muted">مخاطبان</span><b class="tnum" id="sumAud">۴۲٬۸۶۰ نفر</b></div>
          <div class="flex between mt-12" style="font-size:13px"><span class="muted">هزینهٔ تقریبی پیامک</span><b class="tnum" id="sumCost">—</b></div>
        </div>
        <button class="btn btn-primary" style="height:48px"><span class="bic" data-ic="bell"></span> تأیید و ارسال</button>
        <button class="btn">ذخیره پیش‌نویس</button>
      </div>
    </div>
</form>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.aud-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.aud{display:flex;flex-direction:column;gap:3px;align-items:flex-start;padding:14px;border:1.5px solid var(--border);border-radius:13px;background:var(--surface);font-family:inherit;text-align:start}
.aud.active{border-color:var(--accent);background:var(--accent-soft)}
.chan-chip{padding:9px 16px;border:1px solid var(--border);border-radius:10px;background:var(--surface);font-family:inherit;font-size:13px;font-weight:700;color:var(--text-2);transition:all .12s}
.chan-chip.on{border-color:var(--accent);background:var(--accent-soft);color:var(--accent-strong)}
.radio-row{display:flex;flex-direction:column;gap:10px}
.rdo{display:flex;align-items:flex-start;gap:11px;padding:13px 15px;border:1.5px solid var(--border);border-radius:12px;cursor:pointer;transition:all .12s}
.rdo.active{border-color:var(--accent);background:var(--accent-soft)}
.rdo-dot{width:20px;height:20px;border-radius:50%;border:2px solid var(--border);flex:none;margin-top:1px;display:grid;place-items:center}
.rdo.active .rdo-dot{border-color:var(--accent)}.rdo.active .rdo-dot::after{content:"";width:10px;height:10px;border-radius:50%;background:var(--accent)}
.mini-phone{width:300px;border-radius:28px;background:linear-gradient(160deg,#1a2c3d,#0d1a26);padding:16px 14px 30px;box-shadow:var(--shadow-lg)}
.mp-status{display:flex;justify-content:space-between;color:rgba(255,255,255,.8);font-size:12px;font-weight:700;padding:0 8px 18px}
.mp-notif{display:flex;gap:11px;background:rgba(255,255,255,.96);border-radius:16px;padding:12px 13px;box-shadow:0 8px 20px rgba(0,0,0,.25)}
.mp-ic{width:38px;height:38px;border-radius:10px;background:var(--accent);color:#fff;display:grid;place-items:center;flex:none}.mp-ic svg{width:20px;height:20px}
.mp-app{font-size:10.5px;color:#64748b;font-weight:600}
.mp-title{font-size:13.5px;font-weight:800;color:#0f1e2e;margin-top:2px}
.mp-body{font-size:12px;color:#43566b;margin-top:3px;line-height:1.6}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  function fa(n){return n.toLocaleString('fa-IR');}
  var title=document.getElementById('nTitle'),body=document.getElementById('nBody'),type=document.getElementById('nType');
  function sync(){
    document.getElementById('pvTitle').textContent=title.value||'عنوان اعلان';
    document.getElementById('pvBody').textContent=body.value||'متن پیام…';
    document.getElementById('pvIc').innerHTML=window.icon(type.value);
    document.getElementById('cnt').textContent=fa(body.value.length)+' / ۵۰۰ کاراکتر';
  }
  [title,body].forEach(function(el){el.addEventListener('input',sync);});
  type.addEventListener('change',sync);sync();

  var aud=+document.querySelector('#audGrid .aud.active')?.getAttribute('data-n')||0;
  document.querySelectorAll('#audGrid .aud').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#audGrid .aud').forEach(function(x){x.classList.remove('active');});b.classList.add('active');
      aud=+b.getAttribute('data-n');
      document.getElementById('audienceInput').value=b.getAttribute('data-aud')||'all';
      updateSum();
    });
  });
  document.querySelectorAll('.chan-chip').forEach(function(c){c.addEventListener('click',function(){
    document.querySelectorAll('.chan-chip').forEach(function(x){x.classList.remove('on');});
    c.classList.add('on');
    var ch=c.getAttribute('data-c');
    document.getElementById('channelInput').value=ch==='sms'?'sms':(ch==='email'?'email':(ch==='wa'?'sms':'push'));
    updateSum();
  });});
  function updateSum(){
    document.getElementById('sumAud').textContent=aud?fa(aud)+' نفر':'سفارشی';
    var sms=document.querySelector('.chan-chip[data-c=sms]').classList.contains('on');
    document.getElementById('sumCost').textContent=sms&&aud?fa(aud*120)+' تومان':'—';
  }
  document.querySelectorAll('[data-radio] .rdo').forEach(function(r){r.addEventListener('click',function(){r.parentElement.querySelectorAll('.rdo').forEach(function(x){x.classList.remove('active');});r.classList.add('active');});});
  updateSum();
})();
</script>
@endpush
