@extends('admin.layouts.app', ['page' => 'inventory', 'title' => 'انبار و دستگاه‌ها', 'crumb' => 'انبار و دارایی'])

@section('content')
@php use App\Support\Persian; @endphp
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="tabs" id="tabs">
        <button class="active" data-tab="stock">موجودی انبار</button>
        <button data-tab="assign">واگذاری به شرکا</button>
        <button data-tab="history">تاریخچه دستگاه</button>
      </div>
      <div class="flex gap-8">
        <button class="btn btn-sm"><span class="bic" data-ic="download"></span> خروجی</button>
        <button class="btn btn-sm btn-primary" id="addBtn"><span class="bic" data-ic="plus"></span> ثبت ورود کالا</button>
      </div>
    </div>

    <!-- KPI -->
    <div class="kpi-grid">
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="box"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['total_qty']) }}</div><div class="ms-l">کل موجودی (واحد)</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="wrench"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['skus']) }}</div><div class="ms-l">قلم کالا (SKU)</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="truck"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['low_stock']) }}</div><div class="ms-l">کم‌موجودی</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="coins"></div><div><div class="ms-v tnum">{{ Persian::digits($items->count()) }}</div><div class="ms-l">ردیف انبار</div></div></div>
    </div>

    <!-- موجودی -->
    <section data-pane="stock">
      <div class="card mt-16">
        <div class="card-head">
          <div><h3>موجودی انبار</h3><div class="ch-sub">دستگاه‌ها و قطعات با قیمت خرید</div></div>
          <div class="head-actions">
            <div class="seg" id="stockSeg"><button class="active">همه</button><button>دستگاه شارژر</button><button>قطعات</button><button>کم‌موجودی</button></div>
            <div class="search" style="max-width:200px"><input id="stockSearch" placeholder="جستجوی کالا…"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
          </div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>کد کالا</th><th>نام</th><th>دسته</th><th>تأمین‌کننده</th><th>موجودی</th><th>قیمت خرید واحد</th><th>ارزش کل</th><th>تاریخ ورود</th><th>وضعیت</th></tr></thead>
            <tbody id="stockBody"></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- واگذاری -->
    <section data-pane="assign" style="display:none">
      <div class="card mt-16">
        <div class="card-head">
          <div><h3>واگذاری دستگاه به شرکا و مالکین</h3><div class="ch-sub">دستگاه، قیمت واگذاری، تاریخ و شریک دریافت‌کننده</div></div>
          <div class="head-actions"><button class="btn btn-sm btn-primary" id="assignBtn"><span class="bic" data-ic="plus"></span> واگذاری جدید</button></div>
        </div>
        <div class="table-wrap">
          <table class="tbl">
            <thead><tr><th>کد واگذاری</th><th>دستگاه</th><th>شماره سریال</th><th>شریک / مالک</th><th>قیمت خرید</th><th>قیمت واگذاری</th><th>تاریخ واگذاری</th><th>وضعیت</th><th></th></tr></thead>
            <tbody id="assignBody"></tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- تاریخچه -->
    <section data-pane="history" style="display:none">
      <div class="grid col-1-2 mt-16">
        <!-- انتخاب دستگاه -->
        <div class="card" style="align-self:start">
          <div class="card-head"><h3>انتخاب دستگاه</h3></div>
          <div class="scroll-y" style="max-height:520px" id="devPicker"></div>
        </div>
        <!-- تایم‌لاین -->
        <div class="card">
          <div class="card-head">
            <div><h3 id="histName">شارژر CP‑۹۱۰۱</h3><div class="ch-sub" id="histMeta">DC CCS2 · ۱۸۰kW · سریال SN‑۸۸۲۱۰۴</div></div>
            <div class="head-actions"><span class="pill pill-green" id="histStatus"><span class="pdot"></span>واگذارشده</span></div>
          </div>
          <div class="card-pad">
            <div class="grid g-3" style="margin-bottom:18px">
              <div class="det-stat"><div class="ds-l">قیمت خرید</div><div class="ds-v tnum">۱۸۵ <small>م</small></div></div>
              <div class="det-stat"><div class="ds-l">قیمت واگذاری</div><div class="ds-v tnum">۲۲۰ <small>م</small></div></div>
              <div class="det-stat"><div class="ds-l">هزینهٔ تعمیرات</div><div class="ds-v tnum">۱۲٫۴ <small>م</small></div></div>
            </div>
            <div class="section-title" style="margin-bottom:12px">رویدادهای دستگاه</div>
            <div class="timeline" id="histTimeline"></div>
          </div>
        </div>
      </div>
    </section>
@endsection

@push('modals')
<!-- مودال ثبت ورود کالا -->
<div class="modal-overlay" id="addModal">
  <div class="modal" style="max-width:600px">
    <div class="modal-head"><h3>ثبت ورود کالا به انبار</h3><button class="icon-btn" id="closeAdd" style="width:34px;height:34px"><span data-ic="x"></span></button></div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="ffield"><label>نوع کالا</label><select class="fselect" id="inCategory"><option value="charger">دستگاه شارژر</option><option value="part">قطعهٔ یدکی</option><option value="cable">کابل و کانکتور</option><option value="accessory">تجهیزات جانبی</option></select></div>
        <div class="ffield"><label>نام کالا <span class="req">*</span></label><input class="finput" id="inName" placeholder="مثلاً شارژر DC ۱۸۰kW"></div>
        <div class="ffield"><label>تأمین‌کننده</label><input class="finput" id="inSupplier" placeholder="نام شرکت تأمین‌کننده"></div>
        <div class="ffield"><label>تعداد <span class="req">*</span></label><input class="finput tnum" dir="ltr" style="text-align:right" id="inQty" value="۱"></div>
        <div class="ffield"><label>قیمت خرید واحد (تومان) <span class="req">*</span></label><input class="finput tnum" dir="ltr" style="text-align:right" id="inPrice" placeholder="۱۸۵٬۰۰۰٬۰۰۰"></div>
        <div class="ffield col-span"><label>شماره فاکتور خرید</label><input class="finput" id="inInvoice" placeholder="اختیاری"></div>
      </div>
    </div>
    <div class="modal-foot"><button class="btn" id="cancelAdd">انصراف</button><button type="button" class="btn btn-primary" id="saveAdd"><span class="bic" data-ic="check"></span> ثبت در انبار</button></div>
  </div>
</div>

<!-- مودال واگذاری -->
<div class="modal-overlay" id="assignModal">
  <div class="modal" style="max-width:600px">
    <div class="modal-head"><h3>واگذاری دستگاه به شریک</h3><button class="icon-btn" id="closeAssign" style="width:34px;height:34px"><span data-ic="x"></span></button></div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="ffield col-span"><label>دستگاه از انبار <span class="req">*</span></label><select class="fselect" id="asCharger"></select></div>
        <div class="ffield"><label>شریک / مالک <span class="req">*</span></label><select class="fselect" id="asPartner"></select></div>
        <div class="ffield"><label>قیمت واگذاری (تومان) <span class="req">*</span></label><input class="finput tnum" dir="ltr" style="text-align:right" id="asPrice" placeholder="۲۲۰٬۰۰۰٬۰۰۰"></div>
        <div class="ffield col-span"><label>توضیحات</label><input class="finput" id="asNotes" placeholder="اختیاری"></div>
      </div>
    </div>
    <div class="modal-foot"><button class="btn" id="cancelAssign">انصراف</button><button type="button" class="btn btn-primary" id="saveAssign"><span class="bic" data-ic="check"></span> ثبت واگذاری</button></div>
  </div>
</div>
@endpush

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm)}
.ms-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:22px;height:22px}
.ms-v{font-size:22px;font-weight:800}.ms-v small{font-size:12px;font-weight:600;color:var(--text-2)}.ms-l{font-size:12px;color:var(--muted)}
.ic-violet{background:color-mix(in srgb,var(--violet) 12%,white);color:var(--violet)}
html[data-theme="dark"] .ic-violet{background:color-mix(in srgb,var(--violet) 26%,#0e1a26);color:#b794f4}
.det-stat{background:var(--surface-2);border:1px solid var(--border-2);border-radius:var(--r);padding:13px 14px}
.ds-l{font-size:11.5px;color:var(--muted)}.ds-v{font-size:19px;font-weight:800;margin-top:3px}.ds-v small{font-size:11px;font-weight:600;color:var(--text-2)}
.dev-pick-row{display:flex;align-items:center;gap:11px;padding:13px 16px;border-bottom:1px solid var(--border-2);cursor:pointer;transition:background .12s}
.dev-pick-row:last-child{border-bottom:none}
.dev-pick-row:hover{background:var(--surface-2)}
.dev-pick-row.active{background:var(--accent-softer);box-shadow:inset 3px 0 0 var(--accent)}
.dev-pick-row .dp-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:none;background:var(--accent-soft);color:var(--accent-strong)}.dp-ic svg{width:19px;height:19px}
.dev-pick-row .dp-n{font-weight:700;font-size:13px}.dev-pick-row .dp-s{font-size:11.5px;color:var(--muted)}
.timeline{position:relative;padding-inline-start:6px}
.tl-item{display:flex;gap:13px;padding-bottom:18px;position:relative}
.tl-item:last-child{padding-bottom:0}
.tl-item::before{content:"";position:absolute;inset-inline-start:5px;top:20px;bottom:-2px;width:2px;background:var(--border)}
.tl-item:last-child::before{display:none}
.tl-dot{width:30px;height:30px;border-radius:9px;display:grid;place-items:center;flex:none;z-index:1}.tl-dot svg{width:15px;height:15px}
.tl-body{flex:1;padding-bottom:2px}
.tl-title{font-weight:700;font-size:13.5px}
.tl-desc{font-size:12px;color:var(--text-2);margin-top:3px;line-height:1.7}
.tl-meta{display:flex;gap:12px;margin-top:6px;font-size:11.5px;color:var(--muted)}
.tl-cost{font-weight:700;color:var(--text-2)}
</style>
@endpush

@push('scripts')
<script>
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  var ID=@json($inventoryJs);
  var stock=ID.stock||[];
  var catMap={device:['دستگاه','tag'],part:['قطعه','tag']};
  function normQ(v){return window.AdminUI?window.AdminUI.normalize(v):String(v||'').toLowerCase();}
  function renderStock(filter,q){
    q=normQ(q);
    document.getElementById('stockBody').innerHTML=stock.filter(function(r){
      var fOk=filter==='all'||(filter==='device'&&r.kind==='device')||(filter==='part'&&r.kind==='part')||(filter==='low'&&(r.status[0]==='کم‌موجودی'||r.status[0]==='ناموجود'));
      var qOk=!q||normQ(r.search).indexOf(q)!==-1;
      return fOk&&qOk;
    }).map(function(r){
      return '<tr data-search="'+r.search+'"><td class="mono">'+r.sku+'</td><td class="cell-strong">'+r.name+'</td>'+
        '<td><span class="tag">'+catMap[r.kind][0]+'</span></td><td class="cell-muted">'+r.supplier+'</td>'+
        '<td class="tnum '+(r.qty==='۰'?'':'cell-strong')+'">'+r.qty+'</td><td class="tnum">'+r.unit+'</td><td class="tnum cell-strong">'+r.total+'</td>'+
        '<td class="cell-muted">'+r.date+'</td><td><span class="pill '+r.status[1]+'">'+(r.status[1]==='pill-green'?'<span class="pdot"></span>':'')+r.status[0]+'</span></td></tr>';
    }).join('');
  }
  renderStock('all','');
  var sk=['all','device','part','low'];
  document.querySelectorAll('#stockSeg button').forEach(function(b,i){b.addEventListener('click',function(){document.querySelectorAll('#stockSeg button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');renderStock(sk[i],document.getElementById('stockSearch').value);});});
  document.getElementById('stockSearch').addEventListener('input',function(){var a=document.querySelector('#stockSeg button.active');renderStock(sk[[].indexOf.call(a.parentNode.children,a)],this.value);});

  var assigns=ID.assigns||[];
  document.getElementById('assignBody').innerHTML=(assigns.length?assigns:[{code:'—',name:'—',serial:'—',partner:'—',purchase:'—',sale:'—',date:'—',status:['—','pill']}]).map(function(r){
    return '<tr><td class="mono">'+r.code+'</td><td class="cell-strong">'+r.name+'</td><td class="mono cell-muted">'+r.serial+'</td>'+
      '<td>'+r.partner+'</td><td class="tnum cell-muted">'+r.purchase+'</td><td class="tnum cell-strong" style="color:var(--green)">'+r.sale+'</td>'+
      '<td class="cell-muted">'+r.date+'</td><td><span class="pill '+r.status[1]+'">'+(r.status[1]==='pill-green'?'<span class="pdot"></span>':'')+r.status[0]+'</span></td>'+
      '<td><button class="btn btn-sm" type="button">جزئیات</button></td></tr>';
  }).join('');

  var devices=ID.devices||[];
  var histData=ID.histories||{};
  document.getElementById('devPicker').innerHTML=devices.map(function(d,i){
    return '<div class="dev-pick-row'+(i===0?' active':'')+'" data-i="'+i+'"><div class="dp-ic">'+window.icon('station')+'</div>'+
      '<div style="flex:1"><div class="dp-n">'+d.n+'</div><div class="dp-s">'+d.s+'</div></div>'+
      '<span class="pill '+d.st[1]+'" style="font-size:10.5px">'+d.st[0]+'</span></div>';
  }).join('');

  function renderHist(i){
    var d=devices[i]||{n:'—',s:'—',st:['—','pill'],id:0};
    document.getElementById('histName').textContent=d.n;
    document.getElementById('histMeta').textContent=d.s;
    var hs=document.getElementById('histStatus');hs.className='pill '+d.st[1];hs.innerHTML=(d.st[1]==='pill-green'?'<span class="pdot"></span>':'')+d.st[0];
    var events=histData[d.id]||[];
    document.getElementById('histTimeline').innerHTML=events.map(function(e){
      return '<div class="tl-item"><div class="tl-dot '+e.c+'">'+window.icon(e.ic)+'</div>'+
        '<div class="tl-body"><div class="tl-title">'+e.t+'</div><div class="tl-desc">'+e.d+'</div>'+
        '<div class="tl-meta"><span>'+e.date+'</span>'+(e.cost?'<span class="tl-cost">'+e.cost+' تومان</span>':'')+'</div></div></div>';
    }).join('');
  }
  renderHist(0);
  document.querySelectorAll('.dev-pick-row').forEach(function(el){el.addEventListener('click',function(){document.querySelectorAll('.dev-pick-row').forEach(function(x){x.classList.remove('active');});el.classList.add('active');renderHist(+el.getAttribute('data-i'));});});

  // تب‌ها
  document.querySelectorAll('#tabs button').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#tabs button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');
      var t=b.getAttribute('data-tab');
      document.querySelectorAll('[data-pane]').forEach(function(p){p.style.display=p.getAttribute('data-pane')===t?'':'none';});
    });
  });

  // مودال‌ها
  function bindModal(openId,modalId,closeId,cancelId){
    var m=document.getElementById(modalId);
    document.getElementById(openId).addEventListener('click',function(){m.classList.add('open');});
    document.getElementById(closeId).addEventListener('click',function(){m.classList.remove('open');});
    document.getElementById(cancelId).addEventListener('click',function(){m.classList.remove('open');});
    m.addEventListener('click',function(e){if(e.target===m)m.classList.remove('open');});
  }
  bindModal('addBtn','addModal','closeAdd','cancelAdd');
  bindModal('assignBtn','assignModal','closeAssign','cancelAssign');

  var chargerOpts=ID.chargerOptions||[];
  var partnerOpts=ID.partners||[];
  document.getElementById('asCharger').innerHTML=chargerOpts.length?chargerOpts.map(function(c){return '<option value="'+c.id+'">'+c.label+'</option>';}).join(''):'<option value="">دستگاهی در انبار نیست</option>';
  document.getElementById('asPartner').innerHTML=partnerOpts.map(function(p){return '<option value="'+p.id+'">'+p.name+'</option>';}).join('');

  document.getElementById('saveAdd').addEventListener('click',function(){
    if(!window.AdminUI)return;
    var name=document.getElementById('inName').value.trim();
    var qty=parseInt(window.AdminUI.parseDigits(document.getElementById('inQty').value),10)||0;
    var price=parseFloat(window.AdminUI.parseDigits(document.getElementById('inPrice').value))||0;
    if(!name||qty<1||price<=0){alert('نام، تعداد و قیمت الزامی است.');return;}
    window.AdminUI.postJson(window.AdminUI.apiBase()+'/api/inventory/in',{
      name:name,category:document.getElementById('inCategory').value,quantity:qty,unit_price:price,
      supplier:document.getElementById('inSupplier').value.trim()||null,
      invoice:document.getElementById('inInvoice').value.trim()||null
    }).then(function(res){alert(res.message||'ثبت شد');document.getElementById('addModal').classList.remove('open');location.reload();})
      .catch(function(e){alert((e&&e.message)||'خطا در ثبت کالا');});
  });

  document.getElementById('saveAssign').addEventListener('click',function(){
    if(!window.AdminUI)return;
    var cid=document.getElementById('asCharger').value;
    var pid=document.getElementById('asPartner').value;
    var price=parseFloat(window.AdminUI.parseDigits(document.getElementById('asPrice').value))||0;
    if(!cid||!pid||price<=0){alert('دستگاه، شریک و قیمت واگذاری الزامی است.');return;}
    window.AdminUI.postJson(window.AdminUI.apiBase()+'/api/inventory/assign',{
      charger_id:+cid,partner_id:+pid,sale_price:price,
      notes:document.getElementById('asNotes').value.trim()||null
    }).then(function(res){alert(res.message||'ثبت شد');document.getElementById('assignModal').classList.remove('open');location.reload();})
      .catch(function(e){alert((e&&e.message)||'خطا در واگذاری');});
  });
})();
</script>
@endpush
