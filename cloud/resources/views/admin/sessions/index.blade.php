@extends('admin.layouts.app', ['page' => 'sessions', 'title' => 'جلسات شارژ', 'crumb' => 'مدیریت شبکه'])

@section('content')

<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="tabs" id="tabs">
        <button class="active" data-tab="live">جلسات زنده <span class="tcount" id="tabLive">—</span></button>
        <button data-tab="history">سوابق شارژ</button>
      </div>
      <div class="flex gap-8 items-center">
        <span class="muted fs12">به‌روزرسانی: <b class="tnum" data-live-stamp>—</b></span>
        <div class="search" style="max-width:220px"><input id="sessionSearch" placeholder="جستجوی جلسه یا کاربر…" autocomplete="off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
        <button type="button" class="btn btn-sm" id="exportBtn"><span class="bic" data-ic="download"></span> خروجی CSV</button>
      </div>
    </div>

    <div class="kpi-grid" style="margin-bottom:16px">
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="bolt"></div><div><div class="ms-v tnum" id="sActive">—</div><div class="ms-l">جلسهٔ فعال</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="leaf"></div><div><div class="ms-v tnum" id="sToday">—</div><div class="ms-l">جلسهٔ امروز · <span id="sTodayEnergy">—</span> kWh</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="clock"></div><div><div class="ms-v tnum"><span id="sAvgDur">—</span> <small>دقیقه</small></div><div class="ms-l">میانگین مدت شارژ موفق (۳۰ روز)</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="activity"></div><div><div class="ms-v tnum"><span id="sAvgEnergy">—</span> <small>kWh</small></div><div class="ms-l">میانگین انرژی هر جلسهٔ موفق</div></div></div>
    </div>

    <!-- زنده -->
    <div class="card" id="liveCard">
      <div class="card-head">
        <div><h3>جلسات در حال انجام</h3><div class="ch-sub">به‌روزرسانی خودکار هر ۸ ثانیه از داده‌های OCPP</div></div>
        <div class="head-actions"><span class="pill pill-green"><span class="pdot"></span>زنده</span></div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>شناسه</th><th>کاربر</th><th>ایستگاه</th><th>کانکتور</th><th>روش</th><th>باتری (SoC)</th><th>انرژی</th><th>توان</th><th>مدت</th><th>هزینهٔ جاری (تومان)</th><th></th></tr></thead>
          <tbody id="liveBody"></tbody>
        </table>
      </div>
    </div>

    <!-- سوابق -->
    <div class="card" id="histCard" style="display:none">
      <div class="card-head">
        <div><h3>سوابق جلسات شارژ</h3><div class="ch-sub"><span id="histTotal">—</span> جلسهٔ پایان‌یافته</div></div>
        <div class="head-actions">
          <div class="seg" id="histSeg"><button class="active">همه</button><button>موفق</button><button>ناموفق / بدون انرژی</button></div>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>شناسه</th><th>کاربر</th><th>ایستگاه</th><th>تاریخ و زمان</th><th>مدت</th><th>انرژی</th><th>روش</th><th>مبلغ (تومان)</th><th>وضعیت</th></tr></thead>
          <tbody id="histBody"></tbody>
        </table>
      </div>
      <div class="flex between items-center" style="padding:14px 18px;border-top:1px solid var(--border-2)">
        <span class="muted fs12" id="histCount">—</span>
        <div class="flex gap-8" id="pager"></div>
      </div>
    </div>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm)}
.ms-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:22px;height:22px}
.ms-v{font-size:22px;font-weight:800;letter-spacing:-.02em}.ms-v small{font-size:13px;font-weight:600;color:var(--text-2)}.ms-l{font-size:12px;color:var(--muted)}
.tcount{background:var(--green);color:#fff;font-size:10.5px;font-weight:700;padding:1px 7px;border-radius:20px;margin-inline-start:5px}
.stop-btn{width:30px;height:30px;border-radius:9px;border:1px solid var(--border);background:var(--surface);color:var(--red);display:grid;place-items:center}
.stop-btn:hover{background:var(--red-soft)}
.live-err{color:var(--red)}
</style>
@endpush

@push('scripts')
<script>
window.__SESSIONS = @json($live);

(function(){
  var UI=window.AdminUI, fa=UI.fa, esc=UI.esc;
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  var cols=['#0E6BA8','#16A34A','#F97316','#7C3AED','#0891B2','#DC2626','#D97706'];
  function set(id,v){var el=document.getElementById(id);if(el)el.textContent=v;}
  var PAGE=25, page=1, LIVE=[], HIST=[], stopping={};
  var stateLabel={starting:['در انتظار شروع','pill-accent'],charging:['در حال شارژ','pill-green'],idle:['توقف از سمت خودرو','pill-amber'],stop_requested:['در حال توقف','pill-amber']};

  function rowLive(r,i){
    var st=stateLabel[r.status]||stateLabel.charging;
    var soc=r.prog==null?'<span class="muted fs12">گزارش نشده</span>':'<div class="bar"><i style="width:'+r.prog+'%"></i></div><div class="cell-muted tnum" style="margin-top:4px">'+fa(r.prog,0)+'٪</div>';
    var stopBtn=r.can_stop?'<button type="button" class="stop-btn" data-session-id="'+r.session_id+'" title="توقف جلسه"'+(stopping[r.session_id]?' disabled':'')+'>■</button>':'<span class="muted fs12">—</span>';
    return '<tr data-search="'+esc(r.id+' '+r.user+' '+(r.phone||'')+' '+r.station+' '+r.charger)+'"><td class="mono">'+esc(r.id)+'</td>'+
      '<td><div class="cell-user"><span class="uavatar" style="background:'+cols[i%cols.length]+'">'+esc(r.initials)+'</span><span class="cell-strong">'+esc(r.user)+'</span></div></td>'+
      '<td><div class="cell-strong">'+esc(r.station)+'</div><div class="cell-muted mono">'+esc(r.charger)+'</div></td>'+
      '<td><span class="tag">'+esc(r.connector)+'</span></td><td><span class="tag">'+esc(r.auth)+'</span></td>'+
      '<td style="min-width:110px">'+soc+'<div style="margin-top:4px"><span class="pill '+st[1]+'">'+st[0]+'</span></div></td>'+
      '<td class="tnum">'+fa(r.energy,2)+' kWh</td>'+
      '<td class="tnum">'+(r.power==null?'—':fa(r.power)+' kW')+(r.voltage?'<div class="cell-muted">'+fa(r.voltage,0)+'V · '+fa(r.current)+'A</div>':'')+'</td>'+
      '<td class="tnum">'+fa(r.duration,0)+' دق</td><td class="tnum cell-strong">'+fa(r.cost,0)+'</td><td>'+stopBtn+'</td></tr>';
  }
  function rowHist(r,j){
    var st=r.status==='completed'?['موفق','pill-green']:['ناموفق / بدون انرژی','pill-amber'];
    return '<tr><td class="mono">'+esc(r.id)+'</td><td><div class="cell-user"><span class="uavatar" style="background:'+cols[j%cols.length]+'">'+esc(r.initials)+'</span><span class="cell-strong">'+esc(r.user)+'</span></div></td>'+
      '<td>'+esc(r.station)+'<div class="cell-muted mono">'+esc(r.charger)+' · '+esc(r.connector)+'</div></td><td class="cell-muted tnum">'+esc(r.started)+'</td>'+
      '<td class="tnum">'+fa(r.duration,0)+' دق</td><td class="tnum cell-strong">'+fa(r.energy,2)+' kWh</td><td><span class="tag">'+esc(r.auth)+'</span></td>'+
      '<td class="tnum cell-strong">'+fa(r.cost,0)+'</td><td><span class="pill '+st[1]+'" title="'+esc(r.stop_reason||'')+'">'+st[0]+'</span></td></tr>';
  }

  function q(){return UI.normalize(document.getElementById('sessionSearch').value);}
  function segIdx(){return [].indexOf.call(document.querySelectorAll('#histSeg button'),document.querySelector('#histSeg button.active'));}
  function filteredHist(){
    var s=q(), idx=segIdx();
    return HIST.filter(function(r){
      var segOk=idx<=0||(idx===1&&r.status==='completed')||(idx===2&&r.status!=='completed');
      return segOk&&(!s||UI.normalize(r.id+' '+r.user+' '+(r.phone||'')+' '+r.station+' '+r.charger).indexOf(s)!==-1);
    });
  }
  function renderLive(){
    var s=q();
    var rows=LIVE.filter(function(r){return !s||UI.normalize(r.id+' '+r.user+' '+(r.phone||'')+' '+r.station+' '+r.charger).indexOf(s)!==-1;});
    document.getElementById('liveBody').innerHTML=rows.length?rows.map(rowLive).join(''):'<tr><td colspan="11" class="muted" style="text-align:center;padding:24px">جلسهٔ فعالی نیست</td></tr>';
  }
  function renderHist(){
    var rows=filteredHist(), pages=Math.max(1,Math.ceil(rows.length/PAGE));
    if(page>pages)page=pages;
    var slice=rows.slice((page-1)*PAGE,page*PAGE);
    document.getElementById('histBody').innerHTML=slice.length?slice.map(rowHist).join(''):'<tr><td colspan="9" class="muted" style="text-align:center;padding:24px">موردی یافت نشد</td></tr>';
    set('histCount','نمایش '+fa(slice.length,0)+' از '+fa(rows.length,0));
    var html='<button class="btn btn-sm" data-p="'+(page-1)+'"'+(page<=1?' disabled':'')+'>قبلی</button>';
    for(var p=Math.max(1,page-2);p<=Math.min(pages,page+2);p++){html+='<button class="btn btn-sm'+(p===page?' btn-primary':'')+'" data-p="'+p+'">'+fa(p,0)+'</button>';}
    html+='<button class="btn btn-sm" data-p="'+(page+1)+'"'+(page>=pages?' disabled':'')+'>بعدی</button>';
    document.getElementById('pager').innerHTML=html;
  }
  document.getElementById('pager').addEventListener('click',function(e){var b=e.target.closest('[data-p]');if(!b||b.disabled)return;page=+b.getAttribute('data-p');renderHist();});

  function apply(d){
    var s=d.stats||{};
    set('sActive',fa(s.active,0)); set('tabLive',fa(s.active,0)); set('sToday',fa(s.today,0)); set('sTodayEnergy',fa(s.today_energy,2));
    set('sAvgDur',fa(s.avg_duration_min,0)); set('sAvgEnergy',fa(s.avg_energy_kwh,2)); set('histTotal',fa(s.history_total,0));
    LIVE=d.live||[]; HIST=d.history||[];
    renderLive(); renderHist();
  }

  document.querySelectorAll('#tabs button').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#tabs button').forEach(function(x){x.classList.remove('active');});
      b.classList.add('active');
      var live=b.getAttribute('data-tab')==='live';
      document.getElementById('liveCard').style.display=live?'':'none';
      document.getElementById('histCard').style.display=live?'none':'';
    });
  });
  document.getElementById('sessionSearch').addEventListener('input',UI.debounce(function(){page=1;renderLive();renderHist();},200));
  document.querySelectorAll('#histSeg button').forEach(function(b){
    b.addEventListener('click',function(){document.querySelectorAll('#histSeg button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');page=1;renderHist();});
  });
  document.getElementById('exportBtn').addEventListener('click',function(){
    var csv='\ufeffid,user,station,charger,connector,started,duration_min,energy_kwh,auth,cost_toman,status\n';
    filteredHist().forEach(function(r){csv+=[r.id,r.user,r.station,r.charger,r.connector,r.started,r.duration,r.energy,r.auth,r.cost,r.status].map(function(c){return '"'+String(c==null?'':c).replace(/"/g,'""')+'"';}).join(',')+'\n';});
    var a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));a.download='sessions.csv';a.click();
  });

  apply(window.__SESSIONS||{});
  var poller=UI.live('sessions',8000,apply);

  document.getElementById('liveBody').addEventListener('click',function(e){
    var btn=e.target.closest('.stop-btn');
    if(!btn)return;
    var sid=btn.getAttribute('data-session-id');
    if(!sid||!confirm('این جلسه متوقف شود؟'))return;
    btn.disabled=true; stopping[sid]=true;
    UI.postJson(UI.apiBase()+'/api/sessions/'+sid+'/stop',{})
      .then(function(res){alert(res.message||'دستور توقف ارسال شد');poller.refresh();})
      .catch(function(err){alert((err&&err.message)||'خطا در توقف جلسه');delete stopping[sid];btn.disabled=false;});
  });
})();
</script>
@endpush
