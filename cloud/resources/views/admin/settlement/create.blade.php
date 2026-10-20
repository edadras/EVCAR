@extends('admin.layouts.app', ['page' => 'finance', 'title' => 'ایجاد تسویهٔ جدید', 'crumb' => 'مدیریت مالی'])

@section('content')
<form method="POST" action="{{ route('admin.settlement.store') }}">@csrf
@php use App\Support\Persian; @endphp

<div class="flex items-center gap-12 mt-8" style="margin-bottom:18px">
      <a href="/admin/finance" class="btn btn-sm"><span class="bic" data-ic="dots"></span> بازگشت به مالی</a>
    </div>

    <div class="grid col-2-1">
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
          <div class="card-head"><div><h3>اطلاعات تسویه</h3><div class="ch-sub">انتخاب اپراتور/مالک و دوره</div></div></div>
          <div class="card-pad">
            <div class="form-grid">
              <div class="ffield col-span"><label>اپراتور / مالک <span class="req">*</span></label>
                <select class="fselect" name="partner_id" id="opSel" required>
                  @foreach($partners as $partner)
                    @php $pv = $preview[$partner->id] ?? ['gross' => 0, 'share' => 0]; @endphp
                    <option value="{{ $partner->id }}" data-share="{{ (int) $partner->revenue_share_percent }}" data-rev="{{ (int) $pv['gross'] }}">
                      {{ $partner->name }} — اشتراک {{ Persian::num((int) $partner->revenue_share_percent) }}٪
                    </option>
                  @endforeach
                </select>
              </div>
              <div class="ffield"><label>شروع دوره</label><input class="finput" type="date" name="period_start" value="{{ now()->startOfMonth()->format('Y-m-d') }}" required></div>
              <div class="ffield"><label>پایان دوره</label><input class="finput" type="date" name="period_end" value="{{ now()->endOfMonth()->format('Y-m-d') }}" required></div>
              <div class="ffield"><label>روش پرداخت</label><select class="fselect"><option>واریز به شبا</option><option>اعتبار سامانه</option><option>چک</option></select></div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-head"><div><h3>تنظیم سهم و کسورات</h3></div></div>
          <div class="card-pad">
            <div class="ffield" style="margin-bottom:14px">
              <label>درصد سهم اپراتور</label>
              <div class="flex items-center gap-12">
                <input type="range" min="50" max="90" value="75" id="shareRange" style="flex:1;accent-color:var(--accent)">
                <span class="pill pill-accent tnum" id="shareVal" style="min-width:54px;justify-content:center">۷۵٪</span>
              </div>
            </div>
            <div class="form-grid">
              <div class="ffield"><label>هزینهٔ نگهداری (تومان)</label><input class="finput tnum" name="maintenance_fee" dir="ltr" style="text-align:right" id="maint" value="0"></div>
              <div class="ffield"><label>مالیات ارزش افزوده</label><input class="finput tnum" dir="ltr" style="text-align:right" value="۹٪"></div>
            </div>
            <label class="toggle-row mt-12"><div><div class="fw7 fs13">کسر مالیات از سهم اپراتور</div><div class="fhint">در صورت غیرفعال بودن، سامانه پرداخت می‌کند</div></div><span class="switch on" data-sw><i></i></span></label>
          </div>
        </div>
      </div>

      <!-- پیش‌نمایش محاسبه -->
      <div style="display:flex;flex-direction:column;gap:16px;align-self:start;position:sticky;top:84px">
        <div class="card">
          <div class="card-head"><h3>پیش‌نمایش محاسبه</h3></div>
          <div class="card-pad">
            <div class="calc-row"><span class="muted">درآمد ناخالص</span><b class="tnum" id="cGross">۱٬۲۸۰٬۰۰۰٬۰۰۰</b></div>
            <div class="calc-row"><span class="muted">سهم اپراتور (<span id="cPct">۷۵</span>٪)</span><b class="tnum" style="color:var(--green)" id="cShare">۹۶۰٬۰۰۰٬۰۰۰</b></div>
            <div class="calc-row"><span class="muted">کارمزد سامانه</span><b class="tnum" id="cFee">−۳۲۰٬۰۰۰٬۰۰۰</b></div>
            <div class="calc-row"><span class="muted">هزینهٔ نگهداری</span><b class="tnum">−۴۸٬۰۰۰٬۰۰۰</b></div>
            <div class="calc-row total"><span>مبلغ خالص</span><b class="tnum" id="cNet">۲۲۴٬۰۰۰٬۰۰۰</b></div>
          </div>
        </div>
        <button class="btn btn-primary" style="height:48px"><span class="bic" data-ic="check"></span> ایجاد و صدور فاکتور</button>
        <button class="btn">ذخیره پیش‌نویس</button>
      </div>
    </div>
</form>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.calc-row{display:flex;justify-content:space-between;align-items:center;font-size:13.5px;padding:9px 0;border-bottom:1px solid var(--border-2)}
.calc-row:last-child{border-bottom:none}
.calc-row.total{font-size:16px;font-weight:800;background:var(--accent-soft);margin:6px -8px 0;padding:13px 12px;border-radius:10px;color:var(--accent-strong)}
.toggle-row{display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:12px;padding:13px 15px;cursor:pointer}
.switch{width:44px;height:25px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:19px;height:19px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .16s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-19px)}
input[type=range]{height:6px;border-radius:6px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  function fa(n){return Math.round(n).toLocaleString('fa-IR');}
  var opSel=document.getElementById('opSel');
  var gross=opSel.options.length?Math.round(+opSel.options[0].getAttribute('data-rev')/10):0;
  var share=opSel.options.length?+opSel.options[0].getAttribute('data-share'):70;
  var maint=0;
  function recalc(){
    var shareAmt=gross*share/100;
    var fee=gross-shareAmt;
    var net=shareAmt-fee*0+ -0; // net = share - maintenance (fee already taken from gross share split)
    net=shareAmt - maint;
    document.getElementById('cGross').textContent=fa(gross);
    document.getElementById('cPct').textContent=fa(share);
    document.getElementById('cShare').textContent=fa(shareAmt);
    document.getElementById('cFee').textContent='−'+fa(fee);
    document.getElementById('cNet').textContent=fa(net);
  }
  opSel.addEventListener('change',function(){
    var o=opSel.options[opSel.selectedIndex];
    gross=Math.round(+o.getAttribute('data-rev')/10);share=+o.getAttribute('data-share');
    document.getElementById('shareRange').value=share;
    document.getElementById('shareVal').textContent=fa(share)+'٪';
    recalc();
  });
  var sr=document.getElementById('shareRange');
  sr.addEventListener('input',function(){share=+sr.value;document.getElementById('shareVal').textContent=fa(share)+'٪';recalc();});
  document.getElementById('maint').addEventListener('input',function(){var v=this.value.replace(/[^۰-۹0-9]/g,'').replace(/[۰-۹]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);});maint=+v||0;recalc();});
  document.querySelectorAll('[data-sw]').forEach(function(s){s.closest('.toggle-row').addEventListener('click',function(){s.classList.toggle('on');});});
  recalc();
})();
</script>
@endpush
