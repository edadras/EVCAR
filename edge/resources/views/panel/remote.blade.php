@extends('panel.layouts.app', ['stationName' => $remoteJs['stationName'] ?? null])

@section('title', 'کنترل دستگاه‌ها')
@section('heading', 'کنترل دستگاه‌ها و OCPP')

@section('content')
<div class="banner">
    <strong>اجرای محلی:</strong> دستورها مستقیماً از Edge به OCPP Gateway همین ایستگاه ارسال می‌شوند — بدون صف ابری.
    @if($remoteJs['cloudOnline'] ?? false)
        <span class="pill pill-green" style="margin-inline-start:8px">مرکز ابری در دسترس</span>
    @else
        <span class="pill pill-amber" style="margin-inline-start:8px">مرکز آفلاین — کنترل محلی فعال · سینک خودکار در صف Retry</span>
    @endif
</div>

<div class="grid col-1-2">
    <div class="card" style="align-self:start">
        <div class="card-head">
            <div><h3>کانکتورها</h3><div class="ch-sub">{{ $remoteJs['stationName'] ?? '—' }} · {{ $remoteJs['stationCode'] ?? '—' }}</div></div>
            <span class="pill {{ ($remoteJs['online'] ?? false) ? 'pill-green' : 'pill-amber' }}" id="stationOnlinePill"><span class="pdot"></span>{{ ($remoteJs['online'] ?? false) ? 'فعال' : 'بدون داده' }}</span>
        </div>
        <div id="devList"></div>
    </div>

    <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
            <div class="card-head">
                <div><h3>کنترل کانکتور <span id="curName" style="color:var(--accent)">—</span></h3><div class="ch-sub" id="curMeta">—</div></div>
                <span class="pill" id="curStatus">—</span>
            </div>
            <div class="card-pad">
                <div class="grid" style="grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-bottom:16px">
                    <div class="stat-box"><div class="l">توان</div><div class="v tnum fs13" id="mPower">—</div></div>
                    <div class="stat-box"><div class="l">ولتاژ</div><div class="v tnum fs13" id="mVolt">—</div></div>
                    <div class="stat-box"><div class="l">جریان</div><div class="v tnum fs13" id="mAmp">—</div></div>
                    <div class="stat-box"><div class="l">انرژی</div><div class="v tnum fs13" id="mEnergy">—</div></div>
                    <div class="stat-box"><div class="l">مدت</div><div class="v tnum fs13" id="mDuration">—</div></div>
                    <div class="stat-box"><div class="l">کاربر</div><div class="v fs13" id="mUser">—</div></div>
                </div>
                <div class="fw7 fs13 mt8" style="margin-bottom:10px">دستورهای کنترلی</div>
                <div class="grid" style="grid-template-columns:1fr 1fr;gap:10px">
                    <button class="btn ctl-btn" data-cmd="restart" type="button"><span data-ic="refresh"></span> راه‌اندازی مجدد</button>
                    <button class="btn ctl-btn" data-cmd="unlock" type="button"><span data-ic="plug"></span> باز کردن قفل</button>
                    <button class="btn ctl-btn" data-cmd="stop" type="button"><span data-ic="bolt"></span> توقف شارژ</button>
                    <button class="btn ctl-btn" data-cmd="disable" type="button"><span data-ic="alert"></span> غیرفعال‌سازی</button>
                    <button class="btn ctl-btn" data-cmd="firmware" type="button"><span data-ic="download"></span> میان‌افزار</button>
                    <button class="btn ctl-btn" data-cmd="diag" type="button"><span data-ic="activity"></span> تشخیص</button>
                </div>
                <div class="fw7 fs13 mt16" style="margin-bottom:8px">محدودسازی توان</div>
                <div class="flex items-center gap-12">
                    <input type="range" min="1" max="180" value="180" id="pwRange" style="flex:1;accent-color:var(--accent)">
                    <span class="pill pill-accent tnum" id="pwVal">—</span>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3>کنسول رویدادها</h3><span class="pill fs12" id="syncMiniStat">—</span></div>
            <div class="card-pad"><div class="console" id="console"></div></div>
        </div>
        <div class="card">
            <div class="card-head">
                <h3>مرکز ابری · سینک</h3>
                <a href="{{ route('panel.sync') }}" class="btn btn-sm">صفحه سینک</a>
            </div>
            <div class="card-pad fs13" id="syncFeed" style="padding-top:0">
                <div class="muted">در حال بارگذاری وضعیت سینک…</div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.rc-dev{display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid var(--border-2);cursor:pointer}
.rc-dev:last-child{border-bottom:none}
.rc-dev:hover{background:var(--surface-2)}
.rc-dev.active{background:var(--accent-soft);box-shadow:inset 3px 0 0 var(--accent)}
.rc-dev .rd-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;background:var(--surface-2)}
.rc-dev .rd-ic svg{width:19px;height:19px}
.console{background:#0C2236;border-radius:12px;padding:14px;font-family:ui-monospace,monospace;font-size:12px;color:#cfe0f0;line-height:1.9;max-height:220px;overflow-y:auto}
.cline .ct{color:#6e8499;margin-inline-end:8px}
.cline.ok{color:#4ade80}.cline.warn{color:#fbbf24}.cline.cmd{color:#67c5ff}
.ic-green{color:var(--green)}.ic-red{color:var(--red)}.ic-amber{color:var(--amber)}.ic-blue{color:var(--accent)}
</style>
@endpush

@push('scripts')
<script>
window.__REMOTE_STATION=@json($remoteJs ?? ['devs'=>[]]);
(function(){
  var devs=window.__REMOTE_STATION.devs||[];
  var activeIdx=0;
  function fmtNum(n,unit){if(n===null||n===undefined||n===0&&!unit)return '—';return (+n).toLocaleString('fa-IR')+(unit?' '+unit:'');}
  function applyDev(d){
    if(!d)return;
    document.getElementById('curName').textContent=d.id;
    document.getElementById('curMeta').textContent=d.t;
    var cs=document.getElementById('curStatus');cs.className='pill '+d.st[1];cs.textContent=d.st[0];
    document.getElementById('mPower').textContent=d.power>0?fmtNum(d.power,'kW'):'—';
    document.getElementById('mVolt').textContent=d.voltage>0?fmtNum(d.voltage,'V'):'—';
    document.getElementById('mAmp').textContent=d.current>0?fmtNum(d.current,'A'):'—';
    document.getElementById('mEnergy').textContent=d.energy>0?fmtNum(d.energy,'kWh'):'—';
    document.getElementById('mDuration').textContent=d.duration_min>0?fmtNum(d.duration_min,'دق'):'—';
    document.getElementById('mUser').textContent=d.user||'—';
    var pr=document.getElementById('pwRange'),maxKw=d.maxKw||180;
    pr.min=Math.max(1,Math.round(maxKw*0.1));pr.max=maxKw;pr.value=maxKw;
    document.getElementById('pwVal').textContent=maxKw.toLocaleString('fa-IR')+' kW';
  }
  function renderList(){
    document.getElementById('devList').innerHTML=devs.length?devs.map(function(d,i){
      return '<div class="rc-dev'+(i===activeIdx?' active':'')+'" data-i="'+i+'"><div class="rd-ic">'+window.icon('plug')+'</div>'+
        '<div style="flex:1"><div class="fw7 fs13">'+d.id+'</div><div class="muted fs12">'+d.t+'</div></div>'+
        '<span class="pill '+d.st[1]+'">'+d.st[0]+'</span></div>';
    }).join(''):'<div class="card-pad muted">کانکتوری ثبت نشده — ابتدا سینک از مرکز را اجرا کنید.</div>';
    document.querySelectorAll('.rc-dev').forEach(function(el){el.addEventListener('click',function(){
      document.querySelectorAll('.rc-dev').forEach(function(x){x.classList.remove('active');});el.classList.add('active');
      activeIdx=+el.getAttribute('data-i');applyDev(devs[activeIdx]);
    });});
  }
  function mergeLive(connectors){
    if(!connectors||!connectors.length)return;
    connectors.forEach(function(nc){
      var i=devs.findIndex(function(d){return d.connectorId===nc.connectorId;});
      if(i>=0)devs[i]=nc;
    });
    renderList();applyDev(devs[activeIdx]||devs[0]);
  }
  renderList();
  function selectedDev(){var el=document.querySelector('.rc-dev.active');return el?devs[+el.getAttribute('data-i')]:devs[0];}
  function csrf(){var m=document.querySelector('meta[name=csrf-token]');return m?m.getAttribute('content'):'';}
  function log(type,msg){
    var c=document.getElementById('console'),t=new Date(),ts=('0'+t.getHours()).slice(-2)+':'+('0'+t.getMinutes()).slice(-2)+':'+('0'+t.getSeconds()).slice(-2);
    var line=document.createElement('div');line.className='cline '+type;
    line.innerHTML='<span class="ct">'+ts.replace(/[0-9]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d];})+'</span>'+msg;
    c.insertBefore(line,c.firstChild);
  }
  if(devs.length){applyDev(devs[0]);log('ok','کنترل محلی Edge فعال است');}
  var CMD_MAP={
    restart:{type:'reset',label:'Reset',payload:{type:'Soft'}},
    unlock:{type:'unlock',label:'UnlockConnector'},
    stop:{type:'remote_stop',label:'RemoteStop'},
    disable:{type:'change_availability',label:'ChangeAvailability',payload:{type:'Inoperative'}},
    firmware:{type:'update_firmware',label:'UpdateFirmware'},
    diag:{type:'get_diagnostics',label:'GetDiagnostics'}
  };
  function runCommand(spec,extra){
    var d=selectedDev();
    if(!d||!d.connectorId){log('warn','کانکتور انتخاب نشده');return;}
    log('cmd','» '+spec.label+' · '+d.id);
    fetch(window.__REMOTE_STATION.commandUrl,{
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf()},
      body:JSON.stringify({command_type:spec.type,connector_id:d.connectorId,payload:Object.assign({},spec.payload||{},extra||{})})
    }).then(function(r){return r.json().then(function(j){return {ok:r.ok,body:j};});})
      .then(function(res){
        if(res.ok&&res.body.ok)log('ok','✓ '+(res.body.message||'انجام شد'));
        else log('warn','✗ '+(res.body.error||res.body.message||'خطا'));
      }).catch(function(){log('warn','✗ خطای شبکه');});
  }
  document.querySelectorAll('.ctl-btn').forEach(function(b){
    b.addEventListener('click',function(){var s=CMD_MAP[b.getAttribute('data-cmd')];if(s)runCommand(s);});
  });
  document.getElementById('pwRange').addEventListener('change',function(e){
    runCommand({type:'set_charging_profile',label:'SetChargingProfile'},{max_kw:+e.target.value});
  });
  if(window.__REMOTE_STATION.liveUrl){
    setInterval(function(){
      fetch(window.__REMOTE_STATION.liveUrl,{headers:{'Accept':'application/json'}})
        .then(function(r){return r.json();})
        .then(function(data){mergeLive(data.connectors||[]);}).catch(function(){});
    },8000);
  }
  if(window.__REMOTE_STATION.commandsUrl){
    var seen={};
    setInterval(function(){
      fetch(window.__REMOTE_STATION.commandsUrl,{headers:{'Accept':'application/json'}})
        .then(function(r){return r.json();})
        .then(function(data){
          (data.commands||[]).forEach(function(c){
            if(!c.uuid||seen[c.uuid])return;seen[c.uuid]=1;
            var src=c.source_label||'پنل';
            log(c.status==='completed'?'ok':'warn',src+' · '+c.type+' · '+c.status+(c.error?' · '+c.error:''));
          });
        }).catch(function(){});
    },12000);
  }
  if(window.__REMOTE_STATION.syncFeedUrl){
    function renderSyncFeed(data){
      var s=data.status||{};
      var pill=document.getElementById('syncMiniStat');
      if(pill)pill.textContent='صف: '+(s.outbox&&s.outbox.pending?s.outbox.pending:0)+' · مرکز: '+(s.cloud&&s.cloud.reachable?'متصل':'آفلاین');
      var el=document.getElementById('syncFeed');
      if(!el)return;
      var html='<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:12px">';
      html+='<div class="stat-box"><div class="l">صف ارسال</div><div class="v tnum fs13">'+(s.outbox?s.outbox.pending:0)+'</div></div>';
      html+='<div class="stat-box"><div class="l">خطا/تلاش</div><div class="v tnum fs13">'+(s.outbox?(s.outbox.retry||s.outbox.failed):0)+'</div></div>';
      html+='<div class="stat-box"><div class="l">کاربران کش</div><div class="v tnum fs13">'+(s.cached?s.cached.users:0)+'</div></div></div>';
      html+='<div class="fw7 fs12 mb8">آخرین رویدادهای صف</div>';
      var out=data.outbox||[];
      if(!out.length)html+='<div class="muted fs12">رویدادی در صف نیست.</div>';
      else html+=out.slice(0,5).map(function(o){
        return '<div class="fs12" style="padding:6px 0;border-bottom:1px solid var(--border-2)"><code>'+o.event_type+'</code> · '+o.status+(o.last_error?' · <span style="color:var(--red)">'+o.last_error+'</span>':'')+'</div>';
      }).join('');
      html+='<div class="fw7 fs12 mt12 mb8">دستورات مرکز</div>';
      var cc=data.commands_cloud||[];
      if(!cc.length)html+='<div class="muted fs12">دستوری از مرکز ثبت نشده.</div>';
      else html+=cc.slice(0,4).map(function(c){
        return '<div class="fs12" style="padding:6px 0;border-bottom:1px solid var(--border-2)">'+c.type+' · '+c.status+'</div>';
      }).join('');
      el.innerHTML=html;
    }
    function loadSyncFeed(){
      fetch(window.__REMOTE_STATION.syncFeedUrl,{headers:{'Accept':'application/json'}})
        .then(function(r){return r.json();}).then(renderSyncFeed).catch(function(){});
    }
    loadSyncFeed();
    setInterval(loadSyncFeed,15000);
  }
})();
</script>
@endpush
