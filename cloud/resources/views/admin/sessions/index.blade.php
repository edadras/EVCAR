@extends('admin.layouts.app', ['page' => 'sessions', 'title' => 'جلسات شارژ', 'crumb' => 'مدیریت شبکه'])

@section('content')
@php use App\Support\Persian; @endphp

<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <div class="tabs" id="tabs">
        <button class="active" data-tab="live">جلسات زنده <span class="tcount">{{ Persian::digits($stats['active']) }}</span></button>
        <button data-tab="history">سوابق شارژ</button>
      </div>
      <div class="flex gap-8">
        <div class="search" style="max-width:220px"><input id="sessionSearch" placeholder="جستجوی جلسه یا کاربر…" autocomplete="off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
        <button type="button" class="btn btn-sm" id="exportBtn"><span class="bic" data-ic="download"></span> خروجی CSV</button>
      </div>
    </div>

    <div class="kpi-grid" style="margin-bottom:16px">
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="bolt"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['active']) }}</div><div class="ms-l">جلسهٔ فعال</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="leaf"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['today']) }}</div><div class="ms-l">جلسهٔ امروز</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="clock"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['avg_duration_min'] ?? 0) }} <small>دقیقه</small></div><div class="ms-l">میانگین مدت شارژ</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="activity"></div><div><div class="ms-v tnum">{{ Persian::digits($stats['avg_energy_kwh'] ?? 0) }} <small>kWh</small></div><div class="ms-l">میانگین انرژی هر جلسه</div></div></div>
    </div>

    <!-- زنده -->
    <div class="card" id="liveCard">
      <div class="card-head">
        <div><h3>جلسات در حال انجام</h3><div class="ch-sub">به‌روزرسانی لحظه‌ای از طریق OCPP</div></div>
        <div class="head-actions"><span class="pill pill-green"><span class="pdot"></span>زنده</span></div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>شناسه جلسه</th><th>کاربر</th><th>ایستگاه</th><th>کانکتور</th><th>روش</th><th>پیشرفت باتری</th><th>انرژی</th><th>توان</th><th>مدت</th><th>هزینهٔ جاری</th><th></th></tr></thead>
          <tbody id="liveBody"></tbody>
        </table>
      </div>
    </div>

    <!-- سوابق -->
    <div class="card" id="histCard" style="display:none">
      <div class="card-head">
        <div><h3>سوابق جلسات شارژ</h3><div class="ch-sub">{{ Persian::digits(number_format($stats['history_total'] ?? 0)) }} جلسه · ۳۰ روز اخیر</div></div>
        <div class="head-actions">
          <div class="seg" id="histSeg"><button class="active">همه</button><button>تکمیل‌شده</button><button>ناموفق</button></div>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>شناسه</th><th>کاربر</th><th>ایستگاه</th><th>تاریخ و زمان</th><th>مدت</th><th>انرژی</th><th>روش پرداخت</th><th>مبلغ</th><th>وضعیت</th></tr></thead>
          <tbody id="histBody"></tbody>
        </table>
      </div>
      <div class="flex between items-center" style="padding:14px 18px;border-top:1px solid var(--border-2)">
        <span class="muted fs12" id="histCount">—</span>
        <div class="flex gap-8">
          <button class="btn btn-sm">قبلی</button>
          <button class="btn btn-sm btn-primary">۱</button>
          <button class="btn btn-sm">۲</button>
          <button class="btn btn-sm">۳</button>
          <button class="btn btn-sm">بعدی</button>
        </div>
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
</style>
@endpush

@push('scripts')
<script>
window.__SESSIONS = {!! json_encode(app(App\Services\Admin\AdminViewDataService::class)->sessionsJs(), JSON_UNESCAPED_UNICODE) !!};

(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  var DB=window.__SESSIONS||{};
  var cols=['#0E6BA8','#16A34A','#F97316','#7C3AED','#0891B2','#DC2626','#D97706','#0E6BA8','#16A34A','#7C3AED'];
  function fa(n){return n.toLocaleString('fa-IR');}

  function rowLive(r,i){
    var prog=r.prog,bar=prog>85?'bar-orange':(prog<20?'bar-accent':'');
    var stPill=prog>85?'<span class="pill pill-amber">رو به اتمام</span>':(prog<15?'<span class="pill pill-accent">شروع شد</span>':'<span class="pill pill-green"><span class="pdot"></span>در حال شارژ</span>');
    var stopBtn=r.can_stop?'<button type="button" class="stop-btn" data-session-id="'+r.session_id+'" title="توقف از طریق Edge">■</button>':'<span class="muted fs12">—</span>';
    return '<tr data-search="'+r.id+' '+r.user+' '+r.station+' '+r.charger+'"><td class="mono">'+r.id+'</td><td><div class="cell-user"><span class="uavatar" style="background:'+cols[i%cols.length]+'">'+r.initials+'</span><span class="cell-strong">'+r.user+'</span></div></td><td><div class="cell-strong">'+r.station+'</div><div class="cell-muted mono">'+r.charger+'</div></td><td><span class="tag">'+r.connector+'</span></td><td><span class="tag">'+r.auth+'</span></td><td style="min-width:120px"><div class="bar '+bar+'"><i style="width:'+prog+'%"></i></div><div class="cell-muted tnum" style="margin-top:4px">'+fa(prog)+'٪ · '+stPill+'</div></td><td class="tnum">'+r.energy+' kWh</td><td class="tnum">'+r.power+' kW</td><td class="tnum">'+fa(r.duration)+' دق</td><td class="tnum cell-strong">'+fa(r.cost)+'</td><td>'+stopBtn+'</td></tr>';
  }
  function rowHist(r,j){
      var st=r.status==='completed'?['تکمیل‌شده','pill-green','completed']:(r.status==='failed'?['ناموفق','pill-red','failed']:['متوقف‌شده','pill-amber','stopped']);
      return '<tr data-status="'+st[2]+'" data-search="'+r.id+' '+r.user+' '+r.station+'"><td class="mono">'+r.id+'</td><td><div class="cell-user"><span class="uavatar" style="background:'+cols[j%cols.length]+'">'+r.initials+'</span><span class="cell-strong">'+r.user+'</span></div></td><td>'+r.station+'</td><td class="cell-muted tnum">'+r.started+'</td><td class="tnum">'+fa(r.duration)+' دق</td><td class="tnum cell-strong">'+r.energy+' kWh</td><td><span class="tag">'+r.auth+'</span></td><td class="tnum cell-strong">'+fa(r.cost)+'</td><td><span class="pill '+st[1]+'">'+st[0]+'</span></td></tr>';
  }
  var liveRows=(DB.live&&DB.live.length)?DB.live:[];
  var histRows=(DB.history&&DB.history.length)?DB.history:[];
  document.getElementById('liveBody').innerHTML=liveRows.map(rowLive).join('');
  document.getElementById('histBody').innerHTML=histRows.map(rowHist).join('');
  function updateHistCount(){
    var vis=document.querySelectorAll('#histBody tr:not([style*="display: none"])').length;
    var el=document.getElementById('histCount');
    if(el) el.textContent='نمایش '+fa(vis)+' از '+fa(histRows.length);
  }
  updateHistCount();

  // تب‌ها
  document.querySelectorAll('#tabs button').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#tabs button').forEach(function(x){x.classList.remove('active');});
      b.classList.add('active');
      var live=b.getAttribute('data-tab')==='live';
      document.getElementById('liveCard').style.display=live?'':'none';
      document.getElementById('histCard').style.display=live?'none':'';
    });
  });
  if(window.AdminUI){
    function applySessionSearch(){
      var q=window.AdminUI.normalize(document.getElementById('sessionSearch').value);
      document.querySelectorAll('#liveBody tr').forEach(function(tr){
        var text=window.AdminUI.normalize(tr.getAttribute('data-search')||tr.textContent);
        tr.style.display=!q||text.indexOf(q)!==-1?'':'none';
      });
      var idx=[].indexOf.call(document.querySelectorAll('#histSeg button'),document.querySelector('#histSeg button.active'));
      document.querySelectorAll('#histBody tr').forEach(function(tr){
        var st=tr.getAttribute('data-status');
        var segOk=idx===0||(idx===1&&st==='completed')||(idx===2&&st==='failed');
        var text=window.AdminUI.normalize(tr.getAttribute('data-search')||tr.textContent);
        tr.style.display=segOk&&(!q||text.indexOf(q)!==-1)?'':'none';
      });
      updateHistCount();
    }
    document.getElementById('sessionSearch').addEventListener('input',window.AdminUI.debounce(applySessionSearch,200));
    document.querySelectorAll('#histSeg button').forEach(function(b){
      b.addEventListener('click',function(){
        document.querySelectorAll('#histSeg button').forEach(function(x){x.classList.remove('active');});
        b.classList.add('active');
        applySessionSearch();
      });
    });
    document.querySelectorAll('#tabs button').forEach(function(b){
      b.addEventListener('click',function(){setTimeout(applySessionSearch,0);});
    });
    document.getElementById('exportBtn').addEventListener('click',function(){
      var rows=document.querySelectorAll('#histBody tr');
      var csv='id,user,station,started,duration,energy,auth,cost,status\n';
      rows.forEach(function(tr){if(tr.style.display==='none')return;csv+=Array.from(tr.cells).map(function(c){return '"'+c.textContent.replace(/"/g,'""')+'"';}).join(',')+'\n';});
      var a=document.createElement('a');a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv'}));a.download='sessions.csv';a.click();
    });
  }
  document.getElementById('liveBody').addEventListener('click',function(e){
    var btn=e.target.closest('.stop-btn');
    if(!btn||!window.AdminUI)return;
    var sid=btn.getAttribute('data-session-id');
    if(!sid||!confirm('توقف این جلسه از طریق Edge ایستگاه؟'))return;
    btn.disabled=true;
    window.AdminUI.postJson(window.AdminUI.apiBase()+'/api/sessions/'+sid+'/stop',{})
      .then(function(res){alert(res.message||'دستور توقف صف شد');location.reload();})
      .catch(function(err){alert((err&&err.message)||'خطا در توقف جلسه');btn.disabled=false;});
  });
})();
</script>
@endpush
