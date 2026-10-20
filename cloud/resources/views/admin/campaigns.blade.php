@extends('admin.layouts.app', ['page' => 'campaigns', 'title' => 'طرح‌ها و کمپین‌ها', 'crumb' => 'سامانه و ارتباطات'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="seg" id="segF"><button class="active">همه</button><button>فعال</button><button>زمان‌بندی‌شده</button><button>پایان‌یافته</button></div>
      <button class="btn btn-sm btn-primary" id="newBtn"><span class="bic" data-ic="plus"></span> طرح جدید</button>
    </div>

    <div class="kpi-grid">
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="gift"></div><div><div class="ms-v tnum">{{ Persian::num($campaigns->where('status', 'active')->count()) }}</div><div class="ms-l">طرح فعال</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="users"></div><div><div class="ms-v tnum">{{ Persian::num($campaigns->count()) }}</div><div class="ms-l">کل طرح‌ها</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="bolt"></div><div><div class="ms-v tnum">{{ Persian::num((int) $campaigns->avg('discount_percent')) }}٪</div><div class="ms-l">میانگین تخفیف</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="coins"></div><div><div class="ms-v tnum">{{ Persian::num((int) round($campaigns->sum('bonus_amount') / 10 / 1_000_000)) }} <small>م</small></div><div class="ms-l">اعتبار توزیع‌شده</div></div></div>
    </div>

    <div class="grid g-3 mt-16" id="campGrid">
      @foreach($campaigns as $camp)
        @php
          $stk = $camp->status !== 'active' ? 'ended' : ($camp->starts_at && $camp->starts_at->isFuture() ? 'scheduled' : ($camp->ends_at && $camp->ends_at->isPast() ? 'ended' : 'active'));
          $badge = $camp->discount_percent ? Persian::num((int) $camp->discount_percent).'٪' : ($camp->bonus_amount ? Persian::num((int) round($camp->bonus_amount / 10 / 1000)).'K' : $camp->type);
          $gradients = ['linear-gradient(140deg,#0E6BA8,#7C3AED)','linear-gradient(140deg,#16A34A,#0891B2)','linear-gradient(140deg,#F97316,#DC2626)'];
          $stLabel = $stk === 'active' ? ['فعال','pill-green'] : ($stk === 'scheduled' ? ['زمان‌بندی','pill-amber'] : ['پایان‌یافته','pill']);
        @endphp
        <div class="camp" data-stk="{{ $stk }}">
          <div class="camp-top" style="background:{{ $gradients[$loop->index % 3] }}"><span class="ct-badge">{{ $badge }}</span><span class="ct-tag">{{ $stLabel[0] }}</span></div>
          <div class="camp-b">
            <h4>{{ $camp->name }}</h4>
            <div class="cb-d">{{ $camp->type === 'discount' ? 'تخفیف '.$badge : 'اعتبار هدیه' }} · {{ $camp->starts_at?->format('Y/m/d') }} تا {{ $camp->ends_at?->format('Y/m/d') }}</div>
            <div class="camp-meta"><span>{{ $camp->status }}</span><span class="pill {{ $stLabel[1] }}">@if($stLabel[1]==='pill-green')<span class="pdot"></span>@endif{{ $stLabel[0] }}</span></div>
          </div>
        </div>
      @endforeach
    </div>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm)}
.ms-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:22px;height:22px}
.ms-v{font-size:22px;font-weight:800}.ms-v small{font-size:12px;font-weight:600;color:var(--text-2)}.ms-l{font-size:12px;color:var(--muted)}
.ic-violet{background:color-mix(in srgb,var(--violet) 12%,white);color:var(--violet)}
html[data-theme="dark"] .ic-violet{background:color-mix(in srgb,var(--violet) 26%,#0e1a26);color:#b794f4}

.camp{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);overflow:hidden;box-shadow:var(--shadow-sm)}
.camp-top{height:96px;display:flex;align-items:center;justify-content:center;position:relative;color:#fff}
.camp-top .ct-badge{font-size:30px;font-weight:800}
.camp-top .ct-tag{position:absolute;top:10px;inset-inline-end:10px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;background:rgba(255,255,255,.25)}
.camp-b{padding:15px}
.camp-b h4{font-size:14.5px}
.camp-b .cb-d{font-size:12px;color:var(--muted);margin-top:5px;line-height:1.6;min-height:38px}
.camp-meta{display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:12px;border-top:1px solid var(--border-2);font-size:11.5px;color:var(--muted)}

.toggle-row{display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:12px;padding:13px 15px;cursor:pointer}
.switch{width:44px;height:25px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:19px;height:19px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .16s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-19px)}

.offer-pv{border-radius:18px;padding:18px;color:#fff;background:linear-gradient(140deg,var(--accent),color-mix(in srgb,var(--accent) 45%,var(--violet)));position:relative;overflow:hidden;box-shadow:var(--shadow-md)}
.offer-pv::after{content:"";position:absolute;inset:0;background:radial-gradient(circle at 85% 0%,rgba(255,255,255,.22),transparent 45%)}
.op-ic{width:44px;height:44px;border-radius:13px;background:rgba(255,255,255,.2);display:grid;place-items:center;position:relative;z-index:1}.op-ic svg{width:23px;height:23px}
.op-badge{position:absolute;top:18px;inset-inline-end:18px;font-size:24px;font-weight:800;z-index:1}
.op-title{font-size:16px;font-weight:800;margin-top:14px;position:relative;z-index:1}
.op-body{font-size:12.5px;opacity:.92;margin-top:6px;line-height:1.7;position:relative;z-index:1}
.op-cta{margin-top:14px;background:rgba(255,255,255,.95);color:var(--accent-strong);font-weight:700;font-size:13px;text-align:center;padding:10px;border-radius:11px;position:relative;z-index:1}
</style>
@endpush

@push('scripts')
<!-- مودال طرح جدید -->
<div class="modal-overlay" id="modal">
  <div class="modal" style="max-width:880px">
    <div class="modal-head"><h3>ساخت طرح / کمپین جدید</h3><button class="icon-btn" id="closeM" style="width:34px;height:34px"><span data-ic="x"></span></button></div>
    <div class="modal-body">
      <div class="grid col-2-1">
        <div>
          <div class="form-grid">
            <div class="ffield col-span"><label>عنوان طرح <span class="req">*</span></label><input class="finput" id="cTitle" value="تخفیف شارژ شبانه"></div>
            <div class="ffield col-span"><label>توضیح <span class="req">*</span></label><textarea class="ftext" id="cBody">۲۰٪ تخفیف روی همهٔ شارژها بین ساعت ۰۰ تا ۶ بامداد.</textarea></div>
            <div class="ffield"><label>نوع طرح</label><select class="fselect" id="cType"><option value="percent">تخفیف درصدی</option><option value="credit">اعتبار هدیه</option><option value="event">رویداد / خبر</option></select></div>
            <div class="ffield"><label>مقدار</label><input class="finput tnum" dir="ltr" style="text-align:right" id="cVal" value="۲۰٪"></div>
            <div class="ffield"><label>از تاریخ</label><input class="finput tnum" dir="ltr" style="text-align:right" value="۱۴۰۳/۱۰/۰۱"></div>
            <div class="ffield"><label>تا تاریخ</label><input class="finput tnum" dir="ltr" style="text-align:right" value="۱۴۰۳/۱۰/۳۰"></div>
            <div class="ffield col-span"><label>مخاطبان</label><select class="fselect"><option>همهٔ کاربران</option><option>کاربران فعال</option><option>کاربران تهران</option><option>کاربران سازمانی</option></select></div>
          </div>
          <label class="toggle-row mt-12"><div><div class="fw7 fs13">ارسال اعلان به اپ مشتری</div><div class="fhint">نمایش طرح به‌صورت پیام در اپلیکیشن</div></div><span class="switch on" data-sw><i></i></span></label>
        </div>
        <div>
          <div class="muted fs12 fw7" style="margin-bottom:10px">پیش‌نمایش در اپ مشتری</div>
          <div class="offer-pv" id="pv">
            <div class="op-ic" id="pvIc"></div>
            <div class="op-badge" id="pvVal">۲۰٪</div>
            <div class="op-title" id="pvTitle">تخفیف شارژ شبانه</div>
            <div class="op-body" id="pvBody">۲۰٪ تخفیف روی همهٔ شارژها بین ساعت ۰۰ تا ۶ بامداد.</div>
            <div class="op-cta">مشاهده و فعال‌سازی</div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal-foot"><button class="btn" id="cancelM">انصراف</button><button type="button" class="btn btn-primary" id="saveCamp"><span class="bic" data-ic="check"></span> انتشار طرح</button></div>
  </div>
</div>



<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  function render(f){
    document.querySelectorAll('#campGrid .camp').forEach(function(c){
      c.style.display=(f==='all'||c.getAttribute('data-stk')===f)?'':'none';
    });
  }
  var keys=['all','active','scheduled','ended'];
  document.querySelectorAll('#segF button').forEach(function(b,i){b.addEventListener('click',function(){document.querySelectorAll('#segF button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');render(keys[i]);});});

  // مودال
  var modal=document.getElementById('modal');
  document.getElementById('newBtn').addEventListener('click',function(){modal.classList.add('open');});
  document.getElementById('closeM').addEventListener('click',function(){modal.classList.remove('open');});
  document.getElementById('cancelM').addEventListener('click',function(){modal.classList.remove('open');});
  modal.addEventListener('click',function(e){if(e.target===modal)modal.classList.remove('open');});
  document.querySelectorAll('[data-sw]').forEach(function(s){s.closest('.toggle-row').addEventListener('click',function(){s.classList.toggle('on');});});

  var ct=document.getElementById('cTitle'),cb=document.getElementById('cBody'),cv=document.getElementById('cVal'),cty=document.getElementById('cType');
  function pv(){
    document.getElementById('pvTitle').textContent=ct.value||'عنوان طرح';
    document.getElementById('pvBody').textContent=cb.value||'توضیح طرح…';
    document.getElementById('pvVal').textContent=cv.value||'';
    document.getElementById('pvIc').innerHTML=window.icon(cty.value==='credit'?'gift':(cty.value==='event'?'leaf':'bolt'));
  }
  [ct,cb,cv].forEach(function(el){el.addEventListener('input',pv);});cty.addEventListener('change',pv);pv();

  document.getElementById('saveCamp').addEventListener('click',function(){
    if(!window.AdminUI)return;
    var title=ct.value.trim();
    if(!title){alert('عنوان طرح الزامی است.');return;}
    window.AdminUI.postJson(window.AdminUI.apiBase()+'/api/campaigns',{
      title:title,body:cb.value.trim(),type:cty.value,value:cv.value.trim(),audience:'all'
    }).then(function(res){alert(res.message||'ثبت شد');modal.classList.remove('open');location.reload();})
      .catch(function(e){alert((e&&e.message)||'خطا در انتشار طرح');});
  });
})();
</script>
@endpush
