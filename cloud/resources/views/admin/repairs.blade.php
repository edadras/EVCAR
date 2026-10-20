@extends('admin.layouts.app', ['page' => 'repairs', 'title' => 'تعمیرات و نگهداری', 'crumb' => 'انبار و دارایی'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="seg" id="statusSeg"><button class="active">همه</button><button>باز</button><button>در حال انجام</button><button>تکمیل‌شده</button></div>
      <div class="flex gap-8">
        <div class="search" style="max-width:220px"><input id="search" placeholder="جستجوی دستگاه یا تعمیر…"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
        <button class="btn btn-sm btn-primary" id="newBtn"><span class="bic" data-ic="plus"></span> ثبت تعمیر</button>
      </div>
    </div>

    <!-- KPI -->
    <div class="kpi-grid">
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="wrench"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['open']) }}</div><div class="ms-l">تعمیر باز</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="activity"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['in_progress']) }}</div><div class="ms-l">در حال انجام</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="check"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['closed']) }}</div><div class="ms-l">تکمیل‌شده</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="coins"></div><div><div class="ms-v tnum">{{ Persian::digits($orders->total()) }}</div><div class="ms-l">کل سوابق</div></div></div>
    </div>

    <!-- جدول تعمیرات -->
    <div class="card mt-16">
      <div class="card-head"><div><h3>سوابق تعمیرات</h3><div class="ch-sub">تعمیر هر دستگاه، قطعات مصرفی و هزینه</div></div></div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>کد تعمیر</th><th>دستگاه</th><th>ایستگاه</th><th>نوع خرابی</th><th>قطعات مصرفی</th><th>تکنسین</th><th>تاریخ</th><th>هزینه</th><th>وضعیت</th><th></th></tr></thead>
          <tbody id="repBody"></tbody>
        </table>
      </div>
    </div>

    <!-- تعمیرات پیشگیرانه -->
    <div class="card mt-16">
      <div class="card-head"><div><h3>سرویس‌های دوره‌ای پیش‌رو</h3><div class="ch-sub">نگهداری پیشگیرانه برنامه‌ریزی‌شده</div></div>
        <div class="head-actions"><button class="btn btn-sm btn-primary" id="defineBtn"><span class="bic" data-ic="plus"></span> تعریف سرویس دوره‌ای</button></div>
      </div>
      <div class="card-pad" style="padding-top:8px" id="preventive"></div>
    </div>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.dt-row{display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:10px 0;border-bottom:1px solid var(--border-2)}
.dt-row:last-child{border-bottom:none}
.dt-row .dt-l{color:var(--muted)}
.plan-head{display:flex;align-items:center;gap:12px}
.plan-head .prev-ic{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;flex:none}.plan-head .prev-ic svg{width:21px;height:21px}
html[dir=rtl] .switch.on i{transform:translateX(-19px)}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm)}
.ms-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:22px;height:22px}
.ms-v{font-size:22px;font-weight:800}.ms-v small{font-size:12px;font-weight:600;color:var(--text-2)}.ms-l{font-size:12px;color:var(--muted)}
.ic-violet{background:color-mix(in srgb,var(--violet) 12%,white);color:var(--violet)}
html[data-theme="dark"] .ic-violet{background:color-mix(in srgb,var(--violet) 26%,#0e1a26);color:#b794f4}
.parts-cell{display:flex;flex-wrap:wrap;gap:4px}
.pchip{font-size:10.5px;font-weight:600;background:var(--surface-3);color:var(--text-2);padding:2px 8px;border-radius:6px}
.prev-row{display:flex;align-items:center;gap:13px;padding:12px 0;border-bottom:1px solid var(--border-2)}
.prev-row:last-child{border-bottom:none}
.prev-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:none}.prev-ic svg{width:19px;height:19px}
.prev-main{flex:1}
.prev-due{text-align:center;flex:none}.prev-due b{font-size:13px}.prev-due span{display:block;font-size:11px;color:var(--muted)}
.part-row{display:flex;align-items:center;gap:8px;margin-bottom:8px}
.part-row select{flex:1;height:38px;border:1px solid var(--border);background:var(--surface);border-radius:9px;font-family:inherit;font-size:12.5px;color:var(--text);padding:0 10px}
.part-row input{width:64px;height:38px;border:1px solid var(--border);background:var(--surface);border-radius:9px;font-family:inherit;font-size:12.5px;color:var(--text);text-align:center}
.part-row .pr-del{width:34px;height:38px;border-radius:9px;border:1px solid var(--border);background:var(--surface);color:var(--red);display:grid;place-items:center;flex:none}.pr-del svg{width:15px;height:15px}
.cost-sum{margin-top:16px;border-top:1px solid var(--border-2);padding-top:12px}
.cs-row{display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:6px 0}
.cs-row.total{font-size:15px;font-weight:800;background:var(--accent-soft);margin:6px -8px 0;padding:11px 12px;border-radius:10px;color:var(--accent-strong)}
</style>
@endpush

@push('scripts')
<!-- مودال ثبت تعمیر -->
<div class="modal-overlay" id="repModal">
  <div class="modal" style="max-width:640px">
    <div class="modal-head"><h3>ثبت تعمیر جدید</h3><button class="icon-btn" id="closeRep" style="width:34px;height:34px"><span data-ic="x"></span></button></div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="ffield"><label>دستگاه <span class="req">*</span></label><select class="fselect" id="repCharger"></select></div>
        <div class="ffield"><label>نوع خرابی <span class="req">*</span></label><select class="fselect" id="repIssue"><option>خطای دما</option><option>قطع ارتباط</option><option>نقص کنتور توان</option><option>خطای اتصال زمین</option><option>خرابی کانکتور</option><option>سرویس دوره‌ای</option></select></div>
        <div class="ffield"><label>تکنسین</label><select class="fselect" id="repTech"><option value="">—</option></select></div>
        <div class="ffield"><label>تاریخ</label><input class="finput tnum" dir="ltr" style="text-align:right" value="۱۴۰۳/۰۹/۲۳"></div>
        <div class="ffield col-span"><label>شرح خرابی و اقدام</label><textarea class="ftext" id="repDesc" placeholder="توضیح مشکل و عملیات انجام‌شده…"></textarea></div>
      </div>

      <div class="sec-title" style="font-size:14px;margin:16px 0 10px">قطعات مصرفی از انبار</div>
      <div id="partRows"></div>
      <button class="btn btn-sm" id="addPart"><span class="bic" data-ic="plus"></span> افزودن قطعه</button>

      <div class="cost-sum">
        <div class="cs-row"><span class="muted">هزینهٔ قطعات</span><b class="tnum" id="partCost">۴٬۲۰۰٬۰۰۰</b></div>
        <div class="cs-row"><span class="muted">دستمزد و خدمات</span><input class="finput tnum" dir="ltr" style="text-align:right;width:160px;height:36px" id="laborCost" value="۳٬۰۰۰٬۰۰۰"></div>
        <div class="cs-row total"><span>هزینهٔ کل تعمیر</span><b class="tnum" id="totalCost">۷٬۲۰۰٬۰۰۰</b></div>
      </div>
    </div>
    <div class="modal-foot"><button class="btn" id="cancelRep">انصراف</button><button type="button" class="btn btn-primary" id="saveRep"><span class="bic" data-ic="check"></span> ثبت تعمیر</button></div>
  </div>
</div>

<!-- مودال تعریف سرویس دوره‌ای -->
<div class="modal-overlay" id="defineModal">
  <div class="modal" style="max-width:560px">
    <div class="modal-head"><h3>تعریف سرویس دوره‌ای جدید</h3><button class="icon-btn" id="closeDefine" style="width:34px;height:34px"><span data-ic="x"></span></button></div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="ffield col-span"><label>عنوان سرویس <span class="req">*</span></label><input class="finput" id="dfTitle" placeholder="مثلاً سرویس دورهٔ ۶ماهه"></div>
        <div class="ffield"><label>دستگاه / دامنه</label><select class="fselect" id="dfDev"><option>CP‑۹۱۰۲ · میدان آزادی</option><option>CP‑۵۱۰۸ · نمایشگاه</option><option>CP‑۲۲۰۷ · برج میلاد</option><option>CP‑۳۳۱۲ · ونک</option><option>همهٔ دستگاه‌های DC</option><option>همهٔ دستگاه‌های AC</option></select></div>
        <div class="ffield"><label>نوع سرویس</label><select class="fselect"><option>بازدید عمومی</option><option>کالیبراسیون کنتور</option><option>تعویض فیلتر</option><option>بازدید سیستم خنک‌کننده</option><option>به‌روزرسانی میان‌افزار</option></select></div>
        <div class="ffield"><label>دورهٔ تکرار</label><select class="fselect"><option>هر ۳ ماه</option><option selected>هر ۶ ماه</option><option>سالانه</option><option>هر ۱۰٬۰۰۰ kWh</option></select></div>
        <div class="ffield"><label>سررسید بعدی</label><input class="finput tnum" dir="ltr" style="text-align:right" id="dfDue" value="۵ روز دیگر"></div>
      </div>
      <label class="toggle-row" style="display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:12px;padding:13px 15px;cursor:pointer;margin-top:14px"><div><div class="fw7 fs13">یادآوری خودکار</div><div class="fhint">ایجاد خودکار وظیفه و اعلان هنگام نزدیک شدن به سررسید</div></div><span class="switch on" data-sw style="width:44px;height:25px;border-radius:20px;background:var(--green);position:relative;flex:none"><i style="position:absolute;top:3px;inset-inline-start:3px;width:19px;height:19px;border-radius:50%;background:#fff"></i></span></label>
    </div>
    <div class="modal-foot"><button class="btn" id="cancelDefine">انصراف</button><button class="btn btn-primary" id="confirmDefine"><span class="bic" data-ic="check"></span> ثبت سرویس دوره‌ای</button></div>
  </div>
</div>

<!-- مودال جزئیات تعمیر -->
<div class="modal-overlay" id="detailModal">
  <div class="modal" style="max-width:560px">
    <div class="modal-head"><h3 id="dtTitle">جزئیات تعمیر</h3><button class="icon-btn" id="closeDetail" style="width:34px;height:34px"><span data-ic="x"></span></button></div>
    <div class="modal-body" id="dtBody"></div>
    <div class="modal-foot"><button class="btn" id="dtClose2">بستن</button><button class="btn btn-primary"><span class="bic" data-ic="check"></span> ویرایش تعمیر</button></div>
  </div>
</div>

<!-- مودال برنامه‌ریزی سرویس -->
<div class="modal-overlay" id="planModal">
  <div class="modal" style="max-width:520px">
    <div class="modal-head"><h3>برنامه‌ریزی سرویس دوره‌ای</h3><button class="icon-btn" id="closePlan" style="width:34px;height:34px"><span data-ic="x"></span></button></div>
    <div class="modal-body">
      <div class="plan-head"><div class="prev-ic ic-blue" id="plIc"></div><div><div class="fw7 fs14" id="plTitle">—</div><div class="fs12 muted" id="plSub">—</div></div></div>
      <div class="form-grid" style="margin-top:16px">
        <div class="ffield"><label>تاریخ سرویس <span class="req">*</span></label><input class="finput tnum" dir="ltr" style="text-align:right" value="۱۴۰۳/۰۹/۲۸"></div>
        <div class="ffield"><label>بازهٔ زمانی</label><select class="fselect"><option>صبح (۸ تا ۱۲)</option><option>عصر (۱۳ تا ۱۷)</option></select></div>
        <div class="ffield"><label>تکنسین</label><select class="fselect"><option>محمد رجبی</option><option>سعید فلاح</option><option>کاوه مرادی</option></select></div>
        <div class="ffield"><label>اولویت</label><select class="fselect"><option>عادی</option><option>مهم</option><option>فوری</option></select></div>
        <div class="ffield col-span"><label>یادداشت</label><input class="finput" placeholder="اختیاری"></div>
      </div>
      <label class="toggle-row" style="display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:12px;padding:13px 15px;cursor:pointer;margin-top:14px"><div><div class="fw7 fs13">اطلاع به تکنسین و شریک</div><div class="fhint">ارسال پیامک یادآوری</div></div><span class="switch on" data-sw style="width:44px;height:25px;border-radius:20px;background:var(--green);position:relative;flex:none"><i style="position:absolute;top:3px;inset-inline-start:3px;width:19px;height:19px;border-radius:50%;background:#fff"></i></span></label>
    </div>
    <div class="modal-foot"><button class="btn" id="cancelPlan">انصراف</button><button class="btn btn-primary" id="confirmPlan"><span class="bic" data-ic="check"></span> ثبت برنامه</button></div>
  </div>
</div>



<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  var RD=@json($repairsJs);
  var reps=RD.orders||[];
  function normQ(v){return window.AdminUI?window.AdminUI.normalize(v):String(v||'').toLowerCase();}
  function renderReps(filter,q){
    q=normQ(q);
    document.getElementById('repBody').innerHTML=reps.filter(function(r){
      var fOk=filter==='all'||r.status[2]===filter;
      var qOk=!q||normQ(r.search).indexOf(q)!==-1;
      return fOk&&qOk;
    }).map(function(r){
      var parts=r.parts.length&&r.parts[0]!=='—'?r.parts.map(function(p){return '<span class="pchip">'+p+'</span>';}).join(''):'<span class="cell-muted">—</span>';
      return '<tr data-search="'+r.search+'"><td class="mono">'+r.code+'</td><td class="cell-strong">'+r.charger+'</td><td class="cell-muted">'+r.station+'</td>'+
        '<td><span class="tag">'+r.issue+'</span></td><td><div class="parts-cell">'+parts+'</div></td><td class="cell-muted">'+r.tech+'</td>'+
        '<td class="cell-muted">'+r.date+'</td><td class="tnum cell-strong">'+r.cost+'</td>'+
        '<td><span class="pill '+r.status[1]+'">'+(r.status[1]==='pill-green'?'<span class="pdot"></span>':'')+r.status[0]+'</span></td>'+
        '<td><button class="btn btn-sm rep-detail" data-code="'+r.code+'">جزئیات</button></td></tr>';
    }).join('');
  }
  renderReps('all','');
  var sk=['all','open','prog','done'];
  document.querySelectorAll('#statusSeg button').forEach(function(b,i){b.addEventListener('click',function(){document.querySelectorAll('#statusSeg button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');renderReps(sk[i],document.getElementById('search').value);});});
  document.getElementById('search').addEventListener('input',function(){var a=document.querySelector('#statusSeg button.active');renderReps(sk[[].indexOf.call(a.parentNode.children,a)],this.value);});

  var prev=RD.preventive||[];
  function renderPrev(){
    document.getElementById('preventive').innerHTML=prev.map(function(p){
      return '<div class="prev-row"><div class="prev-ic '+(p.c||'ic-blue')+'">'+window.icon(p.ic||'wrench')+'</div>'+
        '<div class="prev-main"><div class="fw7 fs13">'+p.t+'</div><div class="fs12 muted" style="margin-top:2px">'+p.s+'</div></div>'+
        '<div class="prev-due"><b style="color:'+p.col+'">'+p.due+'</b><span>تا سررسید</span></div>'+
        '<button class="btn btn-sm prev-plan" data-t="'+p.t+'" data-s="'+p.s+'">برنامه‌ریزی</button></div>';
    }).join('');
  }
  renderPrev();

  // مودال تعمیر
  var m=document.getElementById('repModal');
  document.getElementById('newBtn').addEventListener('click',function(){m.classList.add('open');});
  document.getElementById('closeRep').addEventListener('click',function(){m.classList.remove('open');});
  document.getElementById('cancelRep').addEventListener('click',function(){m.classList.remove('open');});
  m.addEventListener('click',function(e){if(e.target===m)m.classList.remove('open');});

  var chargers=RD.chargers||[];
  document.getElementById('repCharger').innerHTML=chargers.map(function(c){return '<option value="'+c.id+'">'+c.label+'</option>';}).join('');
  document.getElementById('repTech').innerHTML='<option value="">—</option>'+(RD.technicians||[]).map(function(t){return '<option value="'+t.id+'">'+t.name+'</option>';}).join('');
  var PARTS=(RD.partsCatalog||[]).map(function(p){return [p.name,p.price||0];});
  if(!PARTS.length){PARTS=[['—',0]];}
  var rows=[{p:0,q:1}];
  function partOpts(sel){return PARTS.map(function(p,i){return '<option value="'+i+'"'+(i===sel?' selected':'')+'>'+p[0]+' ('+p[1].toLocaleString('fa-IR')+' ت)</option>';}).join('');}
  function renderParts(){
    document.getElementById('partRows').innerHTML=rows.map(function(r,i){
      return '<div class="part-row"><select data-i="'+i+'" class="p-sel">'+partOpts(r.p)+'</select>'+
        '<input type="text" inputmode="numeric" class="p-qty" data-i="'+i+'" value="'+r.q.toLocaleString('fa-IR')+'">'+
        '<button class="pr-del" data-i="'+i+'">'+window.icon('x')+'</button></div>';
    }).join('');
    document.querySelectorAll('.p-sel').forEach(function(s){s.addEventListener('change',function(){rows[+s.getAttribute('data-i')].p=+s.value;calc();});});
    document.querySelectorAll('.p-qty').forEach(function(s){s.addEventListener('input',function(){var v=this.value.replace(/[^۰-۹0-9]/g,'').replace(/[۰-۹]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);});rows[+s.getAttribute('data-i')].q=+v||0;calc();});});
    document.querySelectorAll('.pr-del').forEach(function(b){b.addEventListener('click',function(){if(rows.length>1){rows.splice(+b.getAttribute('data-i'),1);renderParts();calc();}});});
  }
  function toFa(n){return Math.round(n).toLocaleString('fa-IR');}
  function calc(){
    var pc=rows.reduce(function(a,r){return a+PARTS[r.p][1]*r.q;},0);
    document.getElementById('partCost').textContent=toFa(pc);
    var lc=parseInt(document.getElementById('laborCost').value.replace(/[^۰-۹0-9]/g,'').replace(/[۰-۹]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d);}),10)||0;
    document.getElementById('totalCost').textContent=toFa(pc+lc);
  }
  document.getElementById('addPart').addEventListener('click',function(){rows.push({p:1,q:1});renderParts();calc();});
  document.getElementById('laborCost').addEventListener('input',calc);
  renderParts();calc();
  document.getElementById('saveRep').addEventListener('click',function(){
    if(!window.AdminUI)return;
    var cid=document.getElementById('repCharger').value;
    var issue=document.getElementById('repIssue').value;
    if(!cid||!issue){alert('دستگاه و نوع خرابی الزامی است.');return;}
    var parts=rows.map(function(r){return {name:PARTS[r.p][0],qty:r.q};}).filter(function(p){return p.name&&p.name!=='—';});
    var lc=parseInt(window.AdminUI.parseDigits(document.getElementById('laborCost').value),10)||0;
    var tech=document.getElementById('repTech').value;
    window.AdminUI.postJson(window.AdminUI.apiBase()+'/api/repairs',{
      charger_id:+cid,issue:issue,description:document.getElementById('repDesc').value,
      assigned_to:tech?+tech:null,labor_cost:lc,parts:parts
    }).then(function(res){alert(res.message||'ثبت شد');m.classList.remove('open');location.reload();})
      .catch(function(e){alert((e&&e.message)||'خطا در ثبت تعمیر');});
  });

  // مودال جزئیات تعمیر
  var dm=document.getElementById('detailModal');
  function closeDM(){dm.classList.remove('open');}
  document.getElementById('closeDetail').addEventListener('click',closeDM);
  document.getElementById('dtClose2').addEventListener('click',closeDM);
  dm.addEventListener('click',function(e){if(e.target===dm)closeDM();});
  document.addEventListener('click',function(e){
    var b=e.target.closest('.rep-detail');if(!b)return;
    var r=reps.filter(function(x){return x.code===b.getAttribute('data-code');})[0];if(!r)return;
    var parts=r.parts.length&&r.parts[0]!=='—'?r.parts.join('، '):'—';
    document.getElementById('dtTitle').textContent='جزئیات تعمیر '+r.code;
    document.getElementById('dtBody').innerHTML=
      '<div class="dt-row"><span class="dt-l">دستگاه</span><b>'+r.charger+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">ایستگاه</span><b>'+r.station+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">نوع خرابی</span><span class="tag">'+r.issue+'</span></div>'+
      '<div class="dt-row"><span class="dt-l">قطعات مصرفی</span><b>'+parts+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">تکنسین</span><b>'+r.tech+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">تاریخ</span><b class="tnum">'+r.date+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">هزینهٔ کل</span><b class="tnum" style="color:var(--accent-strong)">'+r.cost+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">وضعیت</span><span class="pill '+r.status[1]+'">'+(r.status[1]==='pill-green'?'<span class="pdot"></span>':'')+r.status[0]+'</span></div>';
    dm.classList.add('open');
  });

  // مودال برنامه‌ریزی سرویس
  var pm=document.getElementById('planModal');
  function closePM(){pm.classList.remove('open');}
  document.getElementById('closePlan').addEventListener('click',closePM);
  document.getElementById('cancelPlan').addEventListener('click',closePM);
  document.getElementById('confirmPlan').addEventListener('click',closePM);
  pm.addEventListener('click',function(e){if(e.target===pm)closePM();});
  document.getElementById('plIc').innerHTML=window.icon('wrench');
  document.addEventListener('click',function(e){
    var b=e.target.closest('.prev-plan');if(!b)return;
    document.getElementById('plTitle').textContent=b.getAttribute('data-t');
    document.getElementById('plSub').textContent=b.getAttribute('data-s');
    pm.classList.add('open');
  });

  // مودال تعریف سرویس دوره‌ای
  var defm=document.getElementById('defineModal');
  function closeDefM(){defm.classList.remove('open');}
  document.getElementById('defineBtn').addEventListener('click',function(){defm.classList.add('open');});
  document.getElementById('closeDefine').addEventListener('click',closeDefM);
  document.getElementById('cancelDefine').addEventListener('click',closeDefM);
  defm.addEventListener('click',function(e){if(e.target===defm)closeDefM();});
  document.getElementById('confirmDefine').addEventListener('click',function(){
    var t=document.getElementById('dfTitle').value.trim()||'سرویس دوره‌ای جدید';
    var dev=document.getElementById('dfDev').value;
    var due=document.getElementById('dfDue').value.trim()||'—';
    prev.unshift({ic:'wrench',c:'ic-blue',t:t,s:dev,due:due,col:'var(--green)'});
    renderPrev();
    document.getElementById('dfTitle').value='';
    closeDefM();
  });
})();
</script>
@endpush
