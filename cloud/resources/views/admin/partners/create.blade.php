@extends('admin.layouts.app', ['page' => 'partners', 'title' => 'افزودن مالک / شریک شارژر', 'crumb' => 'شرکا و مالکان شارژر'])

@section('content')
<form method="POST" action="{{ route('admin.partners.store') }}">@csrf
@php use App\Support\Persian; @endphp

<div class="flex items-center gap-12 mt-8" style="margin-bottom:18px">
      <a href="/admin/partners" class="btn btn-sm"><span class="bic" data-ic="dots"></span> بازگشت به شرکا</a>
    </div>

    <div class="grid col-2-1">
      <div style="display:flex;flex-direction:column;gap:16px">
        <!-- هویت -->
        <div class="card">
          <div class="card-head"><div><h3>اطلاعات مالک</h3><div class="ch-sub">شخص یا کسب‌وکاری که پایانهٔ شارژر را تأمین کرده است</div></div></div>
          <div class="card-pad">
            <div class="form-grid">
              <div class="ffield col-span"><label>نام مالک / کسب‌وکار <span class="req">*</span></label><input class="finput" name="name" id="pName" placeholder="مثلاً پارکینگ مرکزی پاسداران" required></div>
              <div class="ffield"><label>نوع</label><select class="fselect"><option>پارکینگ</option><option>هتل</option><option>مرکز خرید</option><option>جایگاه سوخت</option><option>ساختمان اداری</option><option>ناوگان حمل‌ونقل</option><option>شخص حقیقی</option></select></div>
              <div class="ffield"><label>شمارهٔ تماس</label><input class="finput tnum" name="contact_phone" dir="ltr" style="text-align:right" placeholder="۰۹۱۲۰۰۰۰۰۰۰"></div>
              <div class="ffield"><label>شناسهٔ ملی / کد اقتصادی</label><input class="finput tnum" name="national_id" dir="ltr" style="text-align:right" placeholder="۱۴۰۰۰۰۰۰۰۰۰"></div>
              <div class="ffield"><label>شمارهٔ شبا (برای تسویه)</label><input class="finput tnum" name="bank_iban" dir="ltr" style="text-align:right" placeholder="IR0000…"></div>
            </div>
          </div>
        </div>

        <!-- مدل همکاری -->
        <div class="card">
          <div class="card-head"><h3>مدل همکاری و سهم درآمد</h3></div>
          <div class="card-pad">
            <div class="model-types" id="modelTypes">
              <button type="button" class="mt-card active" data-share="70"><span class="mc-ic ic-blue" data-ic="coins"></span><span class="fw7">اشتراک درآمد</span><span class="fs12 muted">سهم مالک ۷۰٪</span></button>
              <button type="button" class="mt-card" data-share="85"><span class="mc-ic ic-green" data-ic="building"></span><span class="fw7">اجارهٔ پلتفرم</span><span class="fs12 muted">سهم مالک ۸۵٪</span></button>
              <button type="button" class="mt-card" data-share="50"><span class="mc-ic ic-violet" data-ic="users"></span><span class="fw7">سرمایه مشترک</span><span class="fs12 muted">طبق قرارداد</span></button>
            </div>
            <input type="hidden" name="revenue_share_percent" id="revShareHidden" value="70">
            <input type="hidden" name="platform_fee_percent" id="platFeeHidden" value="30">
            <div class="form-grid mt-16">
              <div class="ffield"><label>درصد سهم مالک</label><input class="finput tnum" dir="ltr" style="text-align:right" id="shareInput" value="۷۰٪" readonly></div>
              <div class="ffield"><label>دورهٔ تسویه</label>
                <select class="fselect" name="settlement_cycle" id="settlementCycle">
                  <option value="monthly">ماهانه</option>
                  <option value="weekly">هفتگی</option>
                  <option value="quarterly">فصلی</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- تجهیزات -->
        <div class="card">
          <div class="card-head"><div><h3>پایانه‌های شارژ مالک</h3><div class="ch-sub">دستگاه‌هایی که مالک تأمین و نصب کرده است</div></div>
            <div class="head-actions"><button class="btn btn-sm" id="addDev"><span class="bic" data-ic="plus"></span> افزودن دستگاه</button></div>
          </div>
          <div class="card-pad" style="padding-top:8px"><div id="devList" style="display:flex;flex-direction:column;gap:10px"></div></div>
        </div>
      </div>

      <!-- پیش‌نمایش -->
      <div style="display:flex;flex-direction:column;gap:16px;align-self:start;position:sticky;top:84px">
        <div class="card">
          <div class="card-head"><h3>خلاصهٔ همکاری</h3></div>
          <div class="card-pad">
            <div class="center-col">
              <span class="uavatar" id="pvAv" style="width:60px;height:60px;border-radius:18px;font-size:24px;background:linear-gradient(135deg,var(--accent),var(--orange))">؟</span>
              <div class="fw8 mt-12" id="pvName" style="font-size:16px">مالک جدید</div>
              <span class="pill pill-accent mt-8">CPO میزبان</span>
            </div>
            <hr class="sep">
            <div class="flex between" style="font-size:13px"><span class="muted">سهم مالک</span><b class="tnum" id="pvShare">۷۰٪</b></div>
            <div class="flex between mt-12" style="font-size:13px"><span class="muted">کارمزد سامانه</span><b class="tnum" id="pvFee">۳۰٪</b></div>
            <div class="flex between mt-12" style="font-size:13px"><span class="muted">پایانه‌ها</span><b class="tnum" id="pvDev">۲</b></div>
            <div class="flex between mt-12" style="font-size:13px"><span class="muted">تسویه</span><span class="tag">ماهانه</span></div>
          </div>
        </div>
        <button class="btn btn-primary" style="height:48px"><span class="bic" data-ic="plus"></span> ثبت مالک و فعال‌سازی</button>
        <button class="btn">ذخیره پیش‌نویس</button>
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
.dev-row{display:flex;align-items:center;gap:11px;border:1px solid var(--border);border-radius:13px;padding:11px 13px;background:var(--surface-2)}
.dev-row .dv-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:none}.dev-row .dv-ic svg{width:19px;height:19px}
.dev-row select,.dev-row input{height:38px;border:1px solid var(--border);background:var(--surface);border-radius:9px;font-family:inherit;font-size:12.5px;color:var(--text);padding:0 10px}
.dev-row .dv-del{width:34px;height:34px;border-radius:9px;border:1px solid var(--border);background:var(--surface);color:var(--red);display:grid;place-items:center;margin-inline-start:auto}.dev-row .dv-del svg{width:16px;height:16px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  function fa(n){return n.toLocaleString('fa-IR');}

  function setShare(sh){
    document.getElementById('shareInput').value=fa(sh)+'٪';
    document.getElementById('pvShare').textContent=fa(sh)+'٪';
    document.getElementById('pvFee').textContent=fa(100-sh)+'٪';
    document.getElementById('revShareHidden').value=sh;
    document.getElementById('platFeeHidden').value=100-sh;
  }
  document.querySelectorAll('#modelTypes .mt-card').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#modelTypes .mt-card').forEach(function(x){x.classList.remove('active');});b.classList.add('active');
      setShare(+b.getAttribute('data-share'));
    });
  });
  setShare(70);
  document.getElementById('pName').addEventListener('input',function(){
    var v=this.value||'مالک جدید';document.getElementById('pvName').textContent=v;
    document.getElementById('pvAv').textContent=this.value?v[0]:'؟';
  });

  var devs=[{t:'DC CCS2',p:'۱۸۰ kW',c:'ic-green'},{t:'AC Type2',p:'۲۲ kW',c:'ic-blue'}];
  function render(){
    document.getElementById('devList').innerHTML=devs.map(function(d,i){
      return '<div class="dev-row"><div class="dv-ic '+d.c+'">'+window.icon('plug')+'</div>'+
        '<select><option'+(d.t==='DC CCS2'?' selected':'')+'>DC CCS2</option><option'+(d.t==='DC CHAdeMO'?' selected':'')+'>DC CHAdeMO</option><option'+(d.t==='AC Type2'?' selected':'')+'>AC Type2</option></select>'+
        '<input class="tnum" value="'+d.p+'" style="width:90px">'+
        '<input placeholder="شماره سریال دستگاه" style="flex:1;min-width:80px">'+
        '<button class="dv-del" data-i="'+i+'">'+window.icon('x')+'</button></div>';
    }).join('');
    document.getElementById('pvDev').textContent=fa(devs.length);
    document.querySelectorAll('.dv-del').forEach(function(b){b.addEventListener('click',function(){if(devs.length>1){devs.splice(+b.getAttribute('data-i'),1);render();}});});
  }
  render();
  document.getElementById('addDev').addEventListener('click',function(){devs.push({t:'AC Type2',p:'۲۲ kW',c:'ic-blue'});render();});
})();
</script>
@endpush
