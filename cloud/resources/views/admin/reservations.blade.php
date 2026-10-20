@extends('admin.layouts.app', ['page' => 'reservations', 'title' => 'رزرو شارژ', 'crumb' => 'مدیریت شبکه'])

@section('content')
@php use App\Support\Persian; @endphp

@php
  $resFilters = $filters ?? [];
  $periodLabel = $resFilters['period_label'] ?? 'امروز';
  $hasDateRange = !empty($resFilters['date_from']);
  $activeDay = $hasDateRange ? -1 : (int) ($resFilters['day'] ?? 0);
  $dayOptions = $resFilters['day_options'] ?? [];
@endphp
@if(($reservationsJs['stationStatus'] ?? $station?->status ?? 'active') !== 'active')
<div class="alert" style="margin-top:16px;padding:12px 16px;background:var(--orange-soft);border-radius:var(--r);color:var(--orange)">
  ایستگاه انتخاب‌شده {{ ($reservationsJs['stationStatus'] ?? $station?->status) === 'maintenance' ? 'در حالت تعمیر' : 'غیرفعال' }} است — ثبت رزرو جدید مجاز نیست.
</div>
@endif
<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="flex items-center gap-12">
        <select class="fselect" id="stationFilter" style="width:auto;min-width:180px">@foreach($stations as $st)<option value="{{ $st->id }}" @selected($station?->id === $st->id)>{{ $st->name }}@if($st->status !== 'active') ({{ $st->status === 'maintenance' ? 'تعمیر' : 'غیرفعال' }})@endif</option>@endforeach</select>
        <span class="pill pill-green"><span class="pdot"></span>پایش زنده</span>
      </div>
      <div class="seg" id="daySeg">
        @foreach($dayOptions as $opt)
        <button type="button" data-day="{{ $opt['offset'] }}" @class(['active' => !$hasDateRange && $activeDay === $opt['offset']])>{{ $opt['label'] }}</button>
        @endforeach
      </div>
    </div>

    <!-- KPI -->
    <div class="kpi-grid">
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="plug"></div><div><div class="ms-v tnum">{{ Persian::digits($kpis['free_connectors']) }}<small> / {{ Persian::digits($kpis['total_connectors']) }}</small></div><div class="ms-l">کانکتور آزاد</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="bolt"></div><div><div class="ms-v tnum">{{ Persian::digits($kpis['charging_connectors']) }}</div><div class="ms-l">در حال شارژ</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="calendar"></div><div><div class="ms-v tnum">{{ Persian::digits($kpis['reserved_connectors'] ?? 0) }}</div><div class="ms-l">رزرو فعال الان</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="chart"></div><div><div class="ms-v tnum">{{ Persian::digits($kpis['period_reservations']) }}</div><div class="ms-l">{{ $hasDateRange ? 'رزرو در بازه' : 'رزرو در روز' }}</div></div></div>
    </div>

    <div class="grid col-2-1 mt-16">
      <!-- جدول زمانی رزرو -->
      <div class="card">
        <div class="card-head"><div><h3>جدول زمانی کانکتورها</h3><div class="ch-sub" id="tlSub">{{ $reservationsJs['stationName'] ?? ($station?->name ?? '—') }} · {{ Persian::digits($kpis['total_connectors']) }} کانکتور · {{ $periodLabel }} · سبز=آزاد، نارنجی=شارژ، آبی=رزرو، خاکستری=غیرقابل رزرو</div></div></div>
        <div class="card-pad">
          <div class="timeline-grid" id="tlGrid"></div>
          <div class="flex gap-16 mt-16">
            <span class="legend-inline"><i style="background:var(--green)"></i>آزاد</span>
            <span class="legend-inline"><i style="background:var(--orange)"></i>در حال شارژ</span>
            <span class="legend-inline"><i style="background:var(--accent)"></i>رزرو شده</span>
            <span class="legend-inline"><i style="background:var(--surface-3)"></i>غیرقابل رزرو</span>
          </div>
        </div>
      </div>

      <!-- وضعیت زندهٔ کانکتورها -->
      <div class="card">
        <div class="card-head"><h3>وضعیت زنده و زمان آزاد شدن</h3></div>
        <div class="card-pad" style="padding-top:8px" id="liveList"></div>
      </div>
    </div>

    <!-- لیست رزروها -->
    <div class="card mt-16">
      <div class="card-head">
        <div><h3>رزروهای ثبت‌شده</h3><div class="ch-sub" id="listSub">{{ $reservationsJs['stationName'] ?? ($station?->name ?? '—') }} · {{ $periodLabel }}</div></div>
        <div class="head-actions" style="flex-wrap:wrap">
          <div class="flex items-center gap-8">
            <label class="fs12 muted">از</label>
            <input type="date" class="finput" id="dateFrom" style="width:140px;height:36px" value="{{ $resFilters['date_from'] ?? '' }}">
            <label class="fs12 muted">تا</label>
            <input type="date" class="finput" id="dateTo" style="width:140px;height:36px" value="{{ $resFilters['date_to'] ?? '' }}">
            <button type="button" class="btn btn-sm" id="applyDates">اعمال</button>
            @if($hasDateRange)<button type="button" class="btn btn-sm" id="clearDates">پاک کردن</button>@endif
          </div>
          <div class="search" style="max-width:220px"><input id="resSearch" placeholder="جستجوی کاربر یا کد…" autocomplete="off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
          <button class="btn btn-sm btn-primary" id="manualBtn"><span class="bic" data-ic="plus"></span> رزرو دستی</button>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>کد رزرو</th><th>کاربر</th><th>کانکتور</th><th>زمان</th><th>خودرو</th><th>مدت تخمینی</th><th>وضعیت</th><th></th></tr></thead>
          <tbody id="resBody"></tbody>
        </table>
      </div>
    </div>
@endsection

@push('modals')
<!-- مودال رزرو دستی -->
<div class="modal-overlay" id="resModal">
  <div class="modal" style="max-width:640px">
    <div class="modal-head">
      <h3>ثبت رزرو دستی</h3>
      <button class="icon-btn" id="closeRes" style="width:34px;height:34px"><span data-ic="x"></span></button>
    </div>
    <div class="modal-body">
      <div class="form-grid">
        <div class="ffield"><label>کاربر <span class="req">*</span></label><input type="hidden" id="rUserId"><input class="finput" id="rUser" placeholder="جستجوی نام یا موبایل…" autocomplete="off"></div>
        <div class="ffield"><label>خودرو</label><select class="fselect" id="rCar"><option>ایویکو اسکایول ET5</option><option>کیا EV6</option><option>تسلا مدل ۳</option><option>هیوندای آیونیک ۵</option></select></div>
        <div class="ffield"><label>ایستگاه</label><select class="fselect" id="rStation">@foreach(($reservableStations ?? $stations) as $st)<option value="{{ $st->id }}" @selected($station?->id === $st->id && $st->status === 'active')>{{ $st->name }}</option>@endforeach</select></div>
        <div class="ffield"><label>کانکتور <span class="req">*</span></label><select class="fselect" id="rConn" required><option value="">— انتخاب کانکتور —</option></select><div class="fhint" id="rConnHint"></div></div>
      </div>

      <div class="sec-title" style="font-size:14px;margin:18px 0 10px">تاریخ و ساعت</div>
      <div class="day-pick" id="rDays">
        <button type="button" class="rday active" data-d="0"><span class="rd-w">امروز</span><span class="rd-n tnum">۲۳ آذر</span></button>
        <button type="button" class="rday" data-d="1"><span class="rd-w">فردا</span><span class="rd-n tnum">۲۴ آذر</span></button>
        <button type="button" class="rday" data-d="2"><span class="rd-w">شنبه</span><span class="rd-n tnum">۲۵ آذر</span></button>
      </div>
      <div class="rslot-grid" id="rSlots"></div>

      <div class="ffield" style="margin-top:18px"><label>مدت تخمینی شارژ</label>
        <div class="seg" id="rDur" style="width:100%"><button type="button" class="active" data-min="20" style="flex:1">۲۰ دقیقه</button><button type="button" data-min="30" style="flex:1">۳۰ دقیقه</button><button type="button" data-min="45" style="flex:1">۴۵ دقیقه</button><button type="button" data-min="60" style="flex:1">۶۰ دقیقه</button></div>
      </div>
      <label class="toggle-row"><div><div class="fw7 fs13">ارسال پیامک تأیید به کاربر</div><div class="fhint">اطلاع‌رسانی زمان و کد رزرو</div></div><span class="switch on" data-sw><i></i></span></label>
    </div>
    <div class="modal-foot">
      <span class="muted fs13" style="margin-inline-end:auto" id="rSummary">زمانی انتخاب نشده</span>
      <button class="btn" id="cancelRes">انصراف</button>
      <button class="btn btn-primary" id="saveRes"><span class="bic" data-ic="check"></span> ثبت رزرو</button>
    </div>
  </div>
</div>

<!-- مودال جزئیات رزرو -->
<div class="modal-overlay" id="resDetailModal">
  <div class="modal" style="max-width:560px">
    <div class="modal-head"><h3 id="resDtTitle">جزئیات رزرو</h3><button class="icon-btn" id="closeResDetail" style="width:34px;height:34px"><span data-ic="x"></span></button></div>
    <div class="modal-body" id="resDtBody"></div>
    <div class="modal-foot">
      <button type="button" class="btn" id="resDtCancel" style="color:var(--red);margin-inline-end:auto;display:none">لغو رزرو</button>
      <button class="btn" id="resDtClose2">بستن</button>
    </div>
  </div>
</div>
@endpush

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.day-pick{display:flex;gap:9px}
.rday{flex:1;display:flex;flex-direction:column;gap:3px;padding:11px;border:1.5px solid var(--border);border-radius:12px;background:var(--surface);font-family:inherit}
.rday.active{border-color:var(--accent);background:var(--accent-soft)}
.rd-w{font-weight:700;font-size:13px;color:var(--text)}.rd-n{font-size:11px;color:var(--muted)}
.rslot-grid{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-top:12px}
.rslot{padding:9px 4px;border:1.5px solid var(--border);border-radius:10px;background:var(--surface);font-family:inherit;font-weight:700;font-size:12.5px;color:var(--text);font-variant-numeric:tabular-nums}
.rslot.taken{opacity:.4;text-decoration:line-through;pointer-events:none}
.rslot.sel{border-color:var(--accent);background:var(--accent);color:#fff}
.toggle-row{display:flex;align-items:center;justify-content:space-between;gap:16px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:12px;padding:13px 15px;cursor:pointer;margin-top:16px}
.switch{width:44px;height:25px;border-radius:20px;background:var(--surface-3);position:relative;flex:none;transition:background .15s}
.switch i{position:absolute;top:3px;inset-inline-start:3px;width:19px;height:19px;border-radius:50%;background:#fff;box-shadow:var(--shadow-sm);transition:transform .16s}
.switch.on{background:var(--green)}
html[dir=rtl] .switch.on i{transform:translateX(-19px)}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm)}
.ms-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:22px;height:22px}
.ms-v{font-size:22px;font-weight:800}.ms-l{font-size:12px;color:var(--muted)}
.ic-violet{background:color-mix(in srgb,var(--violet) 12%,white);color:var(--violet)}
html[data-theme="dark"] .ic-violet{background:color-mix(in srgb,var(--violet) 26%,#0e1a26);color:#b794f4}
.legend-inline{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--text-2);font-weight:600}.legend-inline i{width:10px;height:10px;border-radius:3px}

.timeline-grid{display:flex;flex-direction:column;gap:8px}
.tl-row{display:flex;align-items:center;gap:10px}
.tl-label{width:54px;flex:none;font-size:12px;font-weight:700;color:var(--text-2)}
.tl-track{flex:1;display:flex;gap:3px}
.tl-cell{flex:1;height:30px;border-radius:5px;position:relative}
.tl-cell.free{background:var(--green-soft)}
.tl-cell.busy{background:var(--orange)}
.tl-cell.res{background:var(--accent)}
.tl-cell.blocked{background:var(--surface-3);opacity:.75}
.tl-hours{display:flex;gap:3px;margin-inline-start:64px;margin-top:6px}
.tl-hours span{flex:1;text-align:center;font-size:10px;color:var(--muted)}

.lv{display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid var(--border-2)}
.lv:last-child{border-bottom:none}
.lv .lv-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:none}.lv-ic svg{width:19px;height:19px}
.lv .lv-main{flex:1;min-width:0}
.lv .lv-t{font-weight:700;font-size:13px}
.lv .lv-prog{height:5px;border-radius:5px;background:var(--surface-3);overflow:hidden;margin-top:6px}
.lv .lv-prog i{display:block;height:100%;background:linear-gradient(90deg,var(--orange),#fbbf24)}
.lv .lv-eta{text-align:center;flex:none}
.lv .lv-eta b{font-size:14px}.lv .lv-eta span{display:block;font-size:10px;color:var(--muted)}
.dt-row{display:flex;justify-content:space-between;align-items:center;font-size:13px;padding:10px 0;border-bottom:1px solid var(--border-2)}
.dt-row:last-child{border-bottom:none}
.dt-row .dt-l{color:var(--muted)}
</style>
@endpush

@push('scripts')
<script>
window.__RESERVATIONS = @json($reservationsJs);
(function(){
  var DB=window.__RESERVATIONS||{};
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  function toFa(n){return String(n).replace(/[0-9]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d];});}

  // جدول زمانی از DB
  var cls=['free','busy','res','blocked'];
  var timeline=(DB.timeline&&DB.timeline.length)?DB.timeline:[];
  document.getElementById('tlGrid').innerHTML=timeline.map(function(row){
    var title=row.blocked&&row.reason?' title="'+row.reason+'"':'';
    return '<div class="tl-row"><span class="tl-label">'+row.id+'</span><div class="tl-track">'+
      row.cells.map(function(v){return '<span class="tl-cell '+cls[v]+'"'+title+'></span>';}).join('')+'</div></div>';
  }).join('')+'<div class="tl-hours">'+['۱۲','۱۳','۱۴','۱۵','۱۶','۱۷'].map(function(h){return '<span>'+h+'</span>';}).join('')+'</div>';

  function fillConnectorOptions(stationId){
    var sel=document.getElementById('rConn');
    var hint=document.getElementById('rConnHint');
    var opts=(DB.connectorOptions&&DB.connectorOptions[stationId])||[];
    sel.innerHTML='<option value="">— انتخاب کانکتور —</option>'+opts.map(function(o){
      var dis=!o.reservable?' disabled':'';
      var suffix=o.reservable?' · آزاد':(' · '+o.reason);
      return '<option value="'+o.id+'"'+dis+'>'+o.label+suffix+'</option>';
    }).join('');
    var first=opts.find(function(o){return o.reservable;});
    if(first)sel.value=String(first.id);
    hint.textContent=opts.length&&!opts.some(function(o){return o.reservable;})?'هیچ کانکتور قابل رزروی در این ایستگاه نیست.':'';
  }
  document.getElementById('rStation').addEventListener('change',function(){fillConnectorOptions(+this.value);});
  fillConnectorOptions(+document.getElementById('rStation').value);

  // وضعیت زنده
  var live=(DB.live&&DB.live.length)?DB.live:[];
  document.getElementById('liveList').innerHTML=live.length?live.map(function(c){
    return '<div class="lv"><div class="lv-ic ic-orange">'+window.icon('plug')+'</div>'+
      '<div class="lv-main"><div class="lv-t">کانکتور '+c.id+' · '+c.u+'</div>'+
      '<div class="fs12 muted" style="margin-top:1px">خودرو '+toFa(c.soc)+'٪ شارژ شده</div>'+
      '<div class="lv-prog"><i style="width:'+c.soc+'%"></i></div></div>'+
      '<div class="lv-eta"><b>'+c.eta+'</b><span>تا آزاد شدن</span></div></div>';
  }).join(''):'<div class="muted fs13" style="padding:16px 0;text-align:center">در حال حاضر جلسهٔ شارژ فعالی در این ایستگاه نیست.</div>';

  // رزروها
  var res=(DB.reservations&&DB.reservations.length)?DB.reservations:[];
  document.getElementById('resBody').innerHTML=res.map(function(r){
    return '<tr data-search="'+(r.search||r.code+' '+r.user+' '+r.car)+'"><td class="mono">'+r.code+'</td><td class="cell-strong">'+r.user+'</td>'+
      '<td><span class="tag">کانکتور '+r.conn+'</span></td><td class="tnum cell-strong">'+r.time+'</td>'+
      '<td class="cell-muted">'+r.car+'</td><td class="tnum">'+r.dur+'</td>'+
      '<td><span class="pill '+r.st[1]+'">'+(r.st[1]==='pill-green'?'<span class="pdot"></span>':'')+r.st[0]+'</span></td>'+
      '<td class="flex gap-8" style="flex-wrap:nowrap">'+
        '<button type="button" class="btn btn-sm res-detail" data-code="'+r.code+'">جزئیات</button>'+
        (r.cancelled?'':'<button type="button" class="btn btn-sm res-cancel" data-id="'+r.id+'" style="color:var(--red)">لغو</button>')+
      '</td></tr>';
  }).join('');

  function resUrl(params){
    var p=new URLSearchParams(window.location.search);
    p.set('station_id',document.getElementById('stationFilter').value);
    Object.keys(params||{}).forEach(function(k){
      if(params[k]==null||params[k]==='')p.delete(k);
      else p.set(k,String(params[k]));
    });
    return window.AdminUI.apiBase()+'/reservations?'+p.toString();
  }
  document.getElementById('stationFilter').addEventListener('change',function(){
    window.location.href=resUrl({});
  });
  document.querySelectorAll('#daySeg button').forEach(function(b){
    b.addEventListener('click',function(){
      window.location.href=resUrl({day:b.getAttribute('data-day'),date_from:null,date_to:null});
    });
  });
  document.getElementById('applyDates').addEventListener('click',function(){
    var from=document.getElementById('dateFrom').value;
    var to=document.getElementById('dateTo').value||from;
    if(!from){alert('تاریخ شروع را انتخاب کنید.');return;}
    window.location.href=resUrl({date_from:from,date_to:to,day:null});
  });
  var clearBtn=document.getElementById('clearDates');
  if(clearBtn)clearBtn.addEventListener('click',function(){
    document.getElementById('dateFrom').value='';
    document.getElementById('dateTo').value='';
    window.location.href=resUrl({date_from:null,date_to:null,day:0});
  });
  if(window.AdminUI) window.AdminUI.bindTableSearch('#resSearch','#resBody');

  var resDm=document.getElementById('resDetailModal');
  function closeResDetail(){resDm.classList.remove('open');}
  document.getElementById('closeResDetail').addEventListener('click',closeResDetail);
  document.getElementById('resDtClose2').addEventListener('click',closeResDetail);
  resDm.addEventListener('click',function(e){if(e.target===resDm)closeResDetail();});
  var resDtCancel=document.getElementById('resDtCancel');
  function cancelReservation(id,onOk){
    if(!window.AdminUI||!id)return;
    if(!confirm('آیا از لغو این رزرو مطمئن هستید؟'))return;
    window.AdminUI.postJson(window.AdminUI.apiBase()+'/api/reservations/'+id+'/cancel',{})
      .then(function(res){alert(res.message||'لغو شد');if(onOk)onOk();else location.reload();})
      .catch(function(e){alert((e&&e.message)||'خطا در لغو رزرو');});
  }
  document.addEventListener('click',function(e){
    var cb=e.target.closest('.res-cancel');
    if(cb){cancelReservation(+cb.getAttribute('data-id'));return;}
    var b=e.target.closest('.res-detail');if(!b)return;
    var r=res.filter(function(x){return x.code===b.getAttribute('data-code');})[0];if(!r)return;
    document.getElementById('resDtTitle').textContent='جزئیات رزرو '+r.code;
    if(r.cancelled){resDtCancel.style.display='none';resDtCancel.removeAttribute('data-id');}
    else{resDtCancel.style.display='';resDtCancel.setAttribute('data-id',r.id);}
    document.getElementById('resDtBody').innerHTML=
      '<div class="dt-row"><span class="dt-l">کاربر</span><b>'+r.user+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">موبایل</span><b class="tnum" dir="ltr" style="text-align:left">'+r.phone+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">ایستگاه</span><b>'+r.station+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">کانکتور</span><span class="tag">'+r.conn+' · '+r.connType+'</span></div>'+
      '<div class="dt-row"><span class="dt-l">خودرو</span><b>'+r.car+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">تاریخ</span><b class="tnum">'+r.date+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">بازهٔ زمانی</span><b class="tnum">'+r.time+' تا '+r.until+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">مدت تخمینی</span><b class="tnum">'+r.dur+'</b></div>'+
      '<div class="dt-row"><span class="dt-l">وضعیت</span><span class="pill '+r.st[1]+'">'+(r.st[1]==='pill-green'?'<span class="pdot"></span>':'')+r.st[0]+'</span></div>';
    resDm.classList.add('open');
  });
  resDtCancel.addEventListener('click',function(){
    var id=+resDtCancel.getAttribute('data-id');
    cancelReservation(id,function(){resDm.classList.remove('open');location.reload();});
  });

  // مودال رزرو دستی
  var modal=document.getElementById('resModal');
  var stationInactive={{ ($reservationsJs['stationStatus'] ?? $station?->status ?? 'active') !== 'active' ? 'true' : 'false' }};
  document.getElementById('manualBtn').addEventListener('click',function(){
    if(stationInactive){alert('ایستگاه فعال نیست و رزرو جدید مجاز نیست.');return;}
    modal.classList.add('open');
  });
  document.getElementById('closeRes').addEventListener('click',function(){modal.classList.remove('open');});
  document.getElementById('cancelRes').addEventListener('click',function(){modal.classList.remove('open');});
  modal.addEventListener('click',function(e){if(e.target===modal)modal.classList.remove('open');});
  document.querySelectorAll('#resModal [data-sw]').forEach(function(s){s.closest('.toggle-row').addEventListener('click',function(){s.classList.toggle('on');});});

  var RSLOTS=['۱۲:۰۰','۱۲:۳۰','۱۳:۰۰','۱۳:۳۰','۱۴:۰۰','۱۴:۳۰','۱۵:۰۰','۱۵:۳۰','۱۶:۰۰','۱۶:۳۰','۱۷:۰۰','۱۷:۳۰'];
  var SLOT_HOURS=[12,12.5,13,13.5,14,14.5,15,15.5,16,16.5,17,17.5];
  var rDay=0,rSlot=null;
  function renderRSlots(){
    document.getElementById('rSlots').innerHTML=RSLOTS.map(function(s,i){
      return '<button type="button" class="rslot'+(rSlot===i?' sel':'')+'" data-i="'+i+'">'+s+'</button>';
    }).join('');
    document.querySelectorAll('#rSlots .rslot').forEach(function(b){b.addEventListener('click',function(){if(b.classList.contains('taken'))return;rSlot=+b.getAttribute('data-i');renderRSlots();updateSummary();});});
  }
  function updateSummary(){
    var s=document.getElementById('rSummary');
    if(rSlot!=null){var d=document.querySelector('#rDays .rday.active .rd-w').textContent;s.textContent=d+' · ساعت '+RSLOTS[rSlot];s.style.color='var(--text)';}
    else{s.textContent='زمانی انتخاب نشده';s.style.color='';}
  }
  renderRSlots();
  document.querySelectorAll('#rDays .rday').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('#rDays .rday').forEach(function(x){x.classList.remove('active');});b.classList.add('active');rDay=+b.getAttribute('data-d');rSlot=null;renderRSlots();updateSummary();});});
  document.querySelectorAll('#rDur button').forEach(function(b){b.addEventListener('click',function(){document.querySelectorAll('#rDur button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');});});
  if(window.AdminUI) window.AdminUI.bindUserAutocomplete(document.getElementById('rUser'),{hiddenInput:'#rUserId'});
  document.getElementById('saveRes').addEventListener('click',function(){
    if(!window.AdminUI)return;
    var uid=document.getElementById('rUserId').value;
    var stationId=document.getElementById('rStation').value;
    if(!uid){alert('کاربر را انتخاب کنید.');return;}
    if(rSlot==null){alert('زمان رزرو را انتخاب کنید.');return;}
    var connEl=document.getElementById('rConn');
    if(!connEl||!connEl.value){alert('کانکتور را انتخاب کنید.');return;}
    var durBtn=document.querySelector('#rDur button.active');
    var duration=+(durBtn&&durBtn.getAttribute('data-min'))||30;
    var dt=new Date();dt.setDate(dt.getDate()+rDay);
    var h=SLOT_HOURS[rSlot];dt.setHours(Math.floor(h),(h%1)?30:0,0,0);
    var payload={
      user_id:+uid,station_id:+stationId,
      reserved_from:window.AdminUI.localDatetime(dt),
      duration_minutes:duration
    };
    if(connEl&&connEl.value)payload.connector_id=+connEl.value;
    window.AdminUI.postJson(window.AdminUI.apiBase()+'/api/reservations',payload).then(function(res){
      alert(res.message||'ثبت شد');
      modal.classList.remove('open');
      window.location.href=resUrl({station_id:stationId});
    }).catch(function(e){alert((e&&e.message)||'خطا در ثبت رزرو');});
  });
})();
</script>
@endpush
