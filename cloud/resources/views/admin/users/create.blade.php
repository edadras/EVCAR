@extends('admin.layouts.app', ['page' => 'users', 'title' => 'ایجاد کاربر جدید', 'crumb' => 'کاربران و سازمان‌ها'])

@section('content')
<form method="POST" action="{{ route('admin.users.store') }}">@csrf
@php use App\Support\Persian; @endphp

<div class="flex items-center gap-12 mt-8" style="margin-bottom:18px">
      <a href="/admin/users" class="btn btn-sm"><span class="bic" data-ic="dots"></span> بازگشت به فهرست کاربران</a>
    </div>

    <div class="grid col-2-1">
      <!-- فرم -->
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
          <div class="card-head"><h3>اطلاعات هویتی</h3></div>
          <div class="card-pad">
            <input type="hidden" name="name" id="userFullName" value="">
            <div class="form-grid">
              <div class="ffield"><label>نام <span class="req">*</span></label><input class="finput" id="firstName" placeholder="مثلاً مهدی" required></div>
              <div class="ffield"><label>نام خانوادگی <span class="req">*</span></label><input class="finput" id="lastName" placeholder="مثلاً احمدی" required></div>
              <div class="ffield"><label>شماره موبایل <span class="req">*</span></label><input class="finput tnum" name="phone" id="phoneInput" dir="ltr" style="text-align:right" placeholder="۰۹۱۲۳۴۵۶۷۸۹" required></div>
              <div class="ffield"><label>کد ملی</label><input class="finput tnum" dir="ltr" style="text-align:right" placeholder="۰۰۱۲۳۴۵۶۷۸"></div>
              <div class="ffield"><label>نوع کاربر</label><select class="fselect"><option>عادی</option><option>سازمانی</option><option>راننده ناوگان</option></select></div>
              <div class="ffield"><label>ایمیل (اختیاری)</label><input class="finput" name="email" dir="ltr" style="text-align:right" placeholder="name@email.com"></div>
            </div>
          </div>
        </div>

        <!-- خودرو و پلاک -->
        <div class="card">
          <div class="card-head"><div><h3>خودرو و پلاک</h3><div class="ch-sub">برای شارژ خودکار با تشخیص پلاک (ANPR) ضروری است</div></div></div>
          <div class="card-pad">
            <div class="form-grid">
              <div class="ffield"><label>خودرو</label><select class="fselect"><option>ایویکو اسکایول ET5</option><option>کیا EV6</option><option>هیوندای آیونیک ۵</option><option>تسلا مدل ۳</option><option>سایر</option></select></div>
              <div class="ffield"><label>نوع کانکتور</label><select class="fselect"><option>CCS2</option><option>CHAdeMO</option><option>Type 2 (AC)</option></select></div>
            </div>

            <div class="ffield col-span mt-8">
              <label>شماره پلاک خودرو <span class="req">*</span></label>
              <div class="plate-input" id="plateInput">
                <span class="pi-flag"><i></i><i></i><i></i></span>
                <span class="pi-seg"><input class="pi-d2 tnum" maxlength="2" placeholder="۱۲"></span>
                <span class="pi-seg"><select class="pi-let"><option>الف</option><option>ب</option><option>ج</option><option>د</option><option selected>س</option><option>ص</option><option>ط</option><option>ق</option><option>ل</option><option>م</option><option>ن</option><option>و</option><option>ه</option><option>ی</option></select></span>
                <span class="pi-seg"><input class="pi-d3 tnum" maxlength="3" placeholder="۳۴۵"></span>
                <span class="pi-seg"><input class="pi-prov tnum" maxlength="2" placeholder="۱۱"></span>
              </div>
              <span class="fhint">پیش‌نمایش زنده در کنار صفحه نمایش داده می‌شود</span>
            </div>

            <label class="toggle-row mt-12">
              <div><div class="fw7 fs13">فعال‌سازی شارژ خودکار با پلاک</div><div class="fhint">با ورود خودرو به ایستگاه مجهز به دوربین، شارژ به‌صورت خودکار آغاز و هزینه از کیف پول کسر می‌شود.</div></div>
              <span class="switch on" data-sw><i></i></span>
            </label>
          </div>
        </div>

        <!-- پرداخت و احراز -->
        <div class="card">
          <div class="card-head"><h3>روش‌های شارژ و کیف پول</h3></div>
          <div class="card-pad">
            <div class="form-grid">
              <div class="ffield"><label>رمز عبور <span class="req">*</span></label><input class="finput" type="password" name="password" minlength="8" required placeholder="حداقل ۸ کاراکتر"></div>
              <div class="ffield"><label>موجودی اولیهٔ کیف پول (تومان)</label><input class="finput tnum" name="initial_balance" dir="ltr" style="text-align:right" placeholder="۰" value="0"></div>
              <div class="ffield"><label>سازمان (در صورت سازمانی بودن)</label>
                <select class="fselect" name="organization_id">
                  <option value="">—</option>
                  @foreach($organizations as $org)
                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="ffield col-span mt-8">
              <label>صدور کارت RFID</label>
              <div class="seg" style="width:100%"><button class="active" type="button" style="flex:1">صدور خودکار</button><button type="button" style="flex:1">شماره دستی</button><button type="button" style="flex:1">بدون کارت</button></div>
            </div>
          </div>
        </div>
      </div>

      <!-- پیش‌نمایش -->
      <div style="display:flex;flex-direction:column;gap:16px;align-self:start;position:sticky;top:84px">
        <div class="card">
          <div class="card-head"><h3>پیش‌نمایش کارت کاربر</h3></div>
          <div class="card-pad center-col">
            <span class="uavatar" id="pvAvatar" style="width:64px;height:64px;border-radius:20px;font-size:22px;background:linear-gradient(135deg,var(--accent),var(--orange))">؟</span>
            <div class="fw8 mt-12" id="pvName" style="font-size:17px">کاربر جدید</div>
            <div class="muted fs13" id="pvPhone">۰۹۱۲ ۳۴۵ ۶۷۸۹</div>
            <div class="mt-16" id="pvPlate"></div>
            <div class="fs12 muted mt-8">پلاک ثبت‌شده برای شارژ خودکار</div>
            <hr class="sep" style="width:100%">
            <div class="flex between" style="width:100%;font-size:13px"><span class="muted">شارژ خودکار</span><span class="pill pill-green" id="pvAuto"><span class="pdot"></span>فعال</span></div>
            <div class="flex between mt-12" style="width:100%;font-size:13px"><span class="muted">کارت RFID</span><span class="tag">صدور خودکار</span></div>
          </div>
        </div>
        <button class="btn btn-primary" style="height:48px"><span class="bic" data-ic="plus"></span> ثبت کاربر</button>
        <button class="btn">ذخیره پیش‌نویس</button>
      </div>
    </div>
</form>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.toggle-row{display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:12px;padding:13px 15px;cursor:pointer}
.switch{width:44px;height:25px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:19px;height:19px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .16s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-19px)}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  var pi=document.getElementById('plateInput');
  var d2=pi.querySelector('.pi-d2'),let_=pi.querySelector('.pi-let'),d3=pi.querySelector('.pi-d3'),prov=pi.querySelector('.pi-prov');
  function renderPlate(){
    document.getElementById('pvPlate').innerHTML=window.plate({d2:d2.value||'۱۲','let':let_.value,d3:d3.value||'۳۴۵',prov:prov.value||'۱۱',size:'lg'});
  }
  [d2,d3,prov].forEach(function(el){el.addEventListener('input',renderPlate);});
  let_.addEventListener('change',renderPlate);
  renderPlate();

  // پیش‌نمایش نام
  var nameI=document.getElementById('firstName'),lastI=document.getElementById('lastName'),phoneI=document.getElementById('phoneInput');
  function up(){
    var nm=((nameI.value||'')+' '+(lastI.value||'')).trim()||'کاربر جدید';
    document.getElementById('userFullName').value=nm==='کاربر جدید'?'':nm;
    document.getElementById('pvName').textContent=nm;
    var init=nm==='کاربر جدید'?'؟':nm.split(' ').map(function(w){return w[0];}).join('.');
    document.getElementById('pvAvatar').textContent=init;
    if(phoneI.value)document.getElementById('pvPhone').textContent=phoneI.value;
  }
  [nameI,lastI,phoneI].forEach(function(el){el.addEventListener('input',up);});
  up();

  document.querySelector('form').addEventListener('submit',function(e){
    up();
    if(!document.getElementById('userFullName').value.trim()){
      e.preventDefault();
      alert('نام و نام خانوادگی الزامی است.');
      return;
    }
    if(window.AdminUI&&phoneI.value){
      phoneI.value=window.AdminUI.normalize(phoneI.value).replace(/\s/g,'');
    }
  });

  document.querySelectorAll('[data-sw]').forEach(function(s){
    s.closest('.toggle-row').addEventListener('click',function(){
      s.classList.toggle('on');
      var on=s.classList.contains('on');
      document.getElementById('pvAuto').className='pill '+(on?'pill-green':'pill');
      document.getElementById('pvAuto').innerHTML=(on?'<span class="pdot"></span>فعال':'غیرفعال');
    });
  });
  document.querySelectorAll('.seg button').forEach(function(b){
    b.addEventListener('click',function(){var p=b.parentElement;p.querySelectorAll('button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');});
  });
})();
</script>
@endpush
