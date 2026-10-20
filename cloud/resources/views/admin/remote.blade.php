@extends('admin.layouts.app', ['page' => 'stations', 'title' => 'کنترل از راه دور — '.($station->name ?? 'ایستگاه'), 'crumb' => $station->name ?? 'ایستگاه'])

@section('content')
<div class="flex between items-center mt-8" style="margin-bottom:18px;flex-wrap:wrap;gap:12px">
      <a href="/admin/stations" class="btn btn-sm"><span class="bic" data-ic="dots"></span> بازگشت به ایستگاه</a>
      @if(!empty($stations) && $stations->count() > 1)
      <select id="stationPick" class="input" style="min-width:220px" onchange="if(this.value)location.href='/admin/remote?station='+this.value">
        @foreach($stations as $st)
          <option value="{{ $st->id }}" @selected(($station->id ?? null) == $st->id)>{{ $st->name }} · {{ $st->code }}</option>
        @endforeach
      </select>
      @endif
      <span class="pill {{ ($remoteJs['online'] ?? false) ? 'pill-green' : 'pill-amber' }}" id="stationOnlinePill"><span class="pdot"></span>{{ ($remoteJs['online'] ?? false) ? 'آنلاین' : 'آفلاین' }}</span>
      @if(!empty($remoteJs['edgeOnline']))
      <span class="pill pill-accent" style="font-size:11px">Edge متصل</span>
      @elseif(!empty($remoteJs['hasEdge']))
      <span class="pill pill-amber" style="font-size:11px">Edge بدون heartbeat اخیر</span>
      @endif
    </div>

    <div class="card" style="margin-bottom:16px;border-color:var(--accent);background:var(--accent-softer)">
      <div class="card-pad" style="font-size:13px;line-height:1.7">
        <strong>معماری Edge-first:</strong> کنترل شارژر فقط در ایستگاه انجام می‌شود. پنل ادمین دستور را در ابر صف می‌کند؛
        <strong>Edge</strong> آن را دریافت کرده و از طریق <strong>OCPP Gateway محلی</strong> (WebSocket همان ایستگاه) روی دستگاه اعمال می‌کند.
        مرکز ابری مستقیماً به WebSocket شارژر وصل نمی‌شود — فقط وضعیت و نتیجه از طریق سینک برمی‌گردد.
        @if(empty($remoteJs['hasEdge']))
          <span class="pill pill-amber" style="margin-inline-start:8px">این ایستگاه Edge ثبت نشده</span>
        @endif
      </div>
    </div>

    <div class="grid col-1-2">
      <!-- لیست دستگاه‌ها -->
      <div class="card" style="align-self:start">
          <div class="card-head"><div><h3>کانکتورها</h3><div class="ch-sub">{{ $remoteJs['stationName'] ?? '—' }} · {{ $remoteJs['stationCode'] ?? '—' }}</div></div></div>
        <div id="devList"></div>
      </div>

      <!-- پنل کنترل -->
      <div style="display:flex;flex-direction:column;gap:16px">
        <div class="card">
          <div class="card-head">
            <div><h3>کنترل کانکتور <span id="curName" style="color:var(--accent)">—</span></h3><div class="ch-sub" id="curMeta">—</div></div>
            <div class="head-actions"><span class="pill" id="curStatus">—</span></div>
          </div>
          <div class="card-pad">
            <!-- متریک‌های زنده -->
            <div class="grid g-3" style="margin-bottom:16px">
              <div class="live-stat"><div class="ls-l">توان لحظه‌ای</div><div class="ls-v tnum" id="mPower">—</div></div>
              <div class="live-stat"><div class="ls-l">ولتاژ</div><div class="ls-v tnum" id="mVolt">—</div></div>
              <div class="live-stat"><div class="ls-l">جریان</div><div class="ls-v tnum" id="mAmp">—</div></div>
              <div class="live-stat"><div class="ls-l">دمای کانکتور</div><div class="ls-v tnum" id="mTemp">—</div></div>
              <div class="live-stat"><div class="ls-l">انرژی جلسه</div><div class="ls-v tnum" id="mEnergy">—</div></div>
              <div class="live-stat"><div class="ls-l">مدت جلسه</div><div class="ls-v tnum" id="mDuration">—</div></div>
            </div>
            <!-- اقدامات کنترلی -->
            <div class="section-title" style="margin-bottom:10px">دستورهای کنترلی</div>
            <div class="grid g-2">
              <button class="ctl-btn" data-cmd="restart"><span class="cb-ic ic-blue" data-ic="refresh"></span><div><div class="fw7 fs13">راه‌اندازی مجدد</div><div class="fhint">Reset (Soft)</div></div></button>
              <button class="ctl-btn" data-cmd="unlock"><span class="cb-ic ic-green" data-ic="plug"></span><div><div class="fw7 fs13">باز کردن قفل کانکتور</div><div class="fhint">UnlockConnector</div></div></button>
              <button class="ctl-btn" data-cmd="stop"><span class="cb-ic ic-orange" data-ic="bolt"></span><div><div class="fw7 fs13">توقف شارژ</div><div class="fhint">RemoteStopTransaction</div></div></button>
              <button class="ctl-btn" data-cmd="disable"><span class="cb-ic ic-red" data-ic="alert"></span><div><div class="fw7 fs13">غیرفعال‌سازی</div><div class="fhint">ChangeAvailability</div></div></button>
              <button class="ctl-btn" data-cmd="firmware"><span class="cb-ic ic-violet" data-ic="download"></span><div><div class="fw7 fs13">به‌روزرسانی میان‌افزار</div><div class="fhint">UpdateFirmware</div></div></button>
              <button class="ctl-btn" data-cmd="diag"><span class="cb-ic ic-blue" data-ic="activity"></span><div><div class="fw7 fs13">دریافت گزارش تشخیصی</div><div class="fhint">GetDiagnostics</div></div></button>
            </div>
            <!-- تنظیم توان -->
            <div class="section-title" style="margin:18px 0 10px">محدودسازی توان (Smart Charging)</div>
            <div class="flex items-center gap-12">
              <input type="range" min="1" max="180" value="180" id="pwRange" style="flex:1;accent-color:var(--accent)" disabled>
              <span class="pill pill-accent tnum" id="pwVal" style="min-width:72px;justify-content:center">—</span>
            </div>
          </div>
        </div>

        <!-- کنسول لاگ -->
        <div class="card">
          <div class="card-head"><div><h3>کنسول دستورها و رویدادها</h3></div><div class="head-actions"><span class="muted fs12">زنده</span></div></div>
          <div class="card-pad"><div class="console" id="console"></div></div>
        </div>
      </div>
    </div>

    @if(!empty($anprPanel))
        @include('admin.stations.partials.anpr-panel', ['anprPanel' => $anprPanel])
    @endif
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.rc-dev{display:flex;align-items:center;gap:12px;padding:13px 18px;border-bottom:1px solid var(--border-2);cursor:pointer;transition:background .12s}
.rc-dev:last-child{border-bottom:none}
.rc-dev:hover{background:var(--surface-2)}
.rc-dev.active{background:var(--accent-softer);box-shadow:inset 3px 0 0 var(--accent)}
.rc-dev .rd-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:none}.rd-ic svg{width:19px;height:19px}
.rc-dev .rd-n{font-weight:700;font-size:13.5px}.rc-dev .rd-s{font-size:11.5px;color:var(--muted)}
.live-stat{background:var(--surface-2);border:1px solid var(--border-2);border-radius:12px;padding:12px 14px}
.live-stat .ls-l{font-size:11.5px;color:var(--muted)}
.live-stat .ls-v{font-size:19px;font-weight:800;margin-top:3px}.live-stat .ls-v small{font-size:11px;font-weight:600;color:var(--text-2)}
.ctl-btn{display:flex;align-items:center;gap:11px;padding:13px;border:1px solid var(--border);border-radius:13px;background:var(--surface);font-family:inherit;text-align:start;transition:all .12s}
.ctl-btn:hover{border-color:var(--accent);background:var(--surface-2)}
.cb-ic{width:40px;height:40px;border-radius:11px;display:grid;place-items:center;flex:none}.cb-ic svg{width:20px;height:20px}
.ic-red{background:var(--red-soft);color:var(--red)}
.console{background:#0C2236;border-radius:12px;padding:14px;font-family:ui-monospace,monospace;font-size:12px;color:#cfe0f0;line-height:1.9;max-height:220px;overflow-y:auto}
.cline .ct{color:#6e8499;margin-inline-end:8px}
.cline.ok{color:#4ade80}.cline.warn{color:#fbbf24}.cline.cmd{color:#67c5ff}
input[type=range]{height:6px;border-radius:6px}
</style>
@endpush

@push('scripts')
<script>
window.__REMOTE_STATION=@json($remoteJs ?? ['devs'=>[]]);
(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  var devs=window.__REMOTE_STATION.devs||[];
  var activeIdx=0;
  function fmtNum(n,unit){if(n===null||n===undefined||n===0&&!unit)return '—';return (+n).toLocaleString('fa-IR')+(unit?' <small>'+unit+'</small>':'');}
  function applyDev(d){
    if(!d)return;
    document.getElementById('curName').textContent=d.id;
    document.getElementById('curMeta').textContent=d.t;
    var cs=document.getElementById('curStatus');cs.className='pill '+d.st[1];cs.innerHTML=(d.st[1]==='pill-green'?'<span class="pdot"></span>':'')+d.st[0];
    document.getElementById('mPower').innerHTML=d.power>0?fmtNum(d.power,'kW'):'—';
    document.getElementById('mVolt').innerHTML=d.voltage>0?fmtNum(d.voltage,'V'):'—';
    document.getElementById('mAmp').innerHTML=d.current>0?fmtNum(d.current,'A'):'—';
    document.getElementById('mTemp').innerHTML=d.temp?fmtNum(d.temp,'°C'):'—';
    document.getElementById('mEnergy').innerHTML=d.energy>0?fmtNum(d.energy,'kWh'):'—';
    document.getElementById('mDuration').innerHTML=d.duration_min>0?fmtNum(d.duration_min,'دق'):'—';
    var pr=document.getElementById('pwRange');
    var maxKw=d.maxKw||180;
    pr.min=Math.max(1,Math.round(maxKw*0.1));
    pr.max=maxKw;
    pr.value=maxKw;
    pr.disabled=!window.__REMOTE_STATION.hasEdge;
    document.getElementById('pwVal').textContent=maxKw.toLocaleString('fa-IR')+' kW';
  }
  function renderList(){
    document.getElementById('devList').innerHTML=devs.map(function(d,i){
      return '<div class="rc-dev'+(i===activeIdx?' active':'')+'" data-i="'+i+'"><div class="rd-ic '+d.st[2]+'">'+window.icon('plug')+'</div>'+
        '<div style="flex:1"><div class="rd-n">کانکتور '+d.id+'</div><div class="rd-s">'+d.t+'</div></div>'+
        '<span class="pill '+d.st[1]+'" style="font-size:10.5px">'+(d.st[1]==='pill-green'?'<span class="pdot"></span>':'')+d.st[0]+'</span></div>';
    }).join('');
    document.querySelectorAll('.rc-dev').forEach(function(el){el.addEventListener('click',function(){
      document.querySelectorAll('.rc-dev').forEach(function(x){x.classList.remove('active');});el.classList.add('active');
      activeIdx=+el.getAttribute('data-i');
      applyDev(devs[activeIdx]);
    });});
  }
  function mergeLive(connectors){
    if(!connectors||!connectors.length)return;
    connectors.forEach(function(nc){
      var i=devs.findIndex(function(d){return d.connectorId===nc.connectorId;});
      if(i>=0)devs[i]=nc;
    });
    renderList();
    applyDev(devs[activeIdx]||devs[0]);
    var pill=document.getElementById('stationOnlinePill');
    if(pill&&window.__REMOTE_STATION){
      var on=window.__REMOTE_STATION._liveOnline;
      pill.className='pill '+(on?'pill-green':'pill-amber');
      pill.innerHTML='<span class="pdot"></span>'+(on?'آنلاین':'آفلاین');
    }
  }
  renderList();

  function selectedDev(){
    var active=document.querySelector('.rc-dev.active');
    return active?devs[+active.getAttribute('data-i')]:devs[0];
  }
  function csrf(){var m=document.querySelector('meta[name=csrf-token]');return m?m.getAttribute('content'):'';}
  function log(type,msg){
    var c=document.getElementById('console');
    var t=new Date();var ts=('۰'+t.getHours()).slice(-2)+':'+('۰'+t.getMinutes()).slice(-2)+':'+('۰'+t.getSeconds()).slice(-2);
    var line=document.createElement('div');line.className='cline '+type;
    line.innerHTML='<span class="ct">'+ts.replace(/[0-9]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d];})+'</span>'+msg;
    c.insertBefore(line,c.firstChild);
  }
  if(devs.length){
    applyDev(devs[0]);
    log('ok','کانکتور '+devs[0].id+' انتخاب شد'+(devs[0].user?' · '+devs[0].user:''));
    if(window.__REMOTE_STATION.hasEdge)log('ok','دستورات از ابر به Edge صف می‌شوند و محلی اجرا می‌گردند');
  } else {
    document.getElementById('console').innerHTML='<div class="cline warn"><span class="ct">—</span>کانکتوری برای این ایستگاه ثبت نشده است</div>';
  }

  var CMD_MAP={
    restart:{type:'reset',label:'Reset (Soft)',payload:{type:'Soft'}},
    unlock:{type:'unlock',label:'UnlockConnector'},
    stop:{type:'remote_stop',label:'RemoteStopTransaction'},
    disable:{type:'change_availability',label:'ChangeAvailability',payload:{type:'Inoperative'}},
    firmware:{type:'update_firmware',label:'UpdateFirmware'},
    diag:{type:'get_diagnostics',label:'GetDiagnostics'}
  };
  function queueCommand(spec,extra){
    if(!window.__REMOTE_STATION.hasEdge){log('warn','این ایستگاه Edge ندارد — کنترل فقط از طریق ایستگاه محلی ممکن است.');return;}
    var d=selectedDev();
    if(!d||!d.connectorId){log('warn','کانکتور انتخاب نشده است.');return;}
    log('cmd','» صف‌بندی '+spec.label+' برای '+d.id+' …');
    fetch(window.__REMOTE_STATION.commandUrl,{
      method:'POST',
      headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf()},
      body:JSON.stringify({
        command_type:spec.type,
        connector_id:d.connectorId,
        payload:Object.assign({},spec.payload||{},extra||{})
      })
    }).then(function(r){return r.json().then(function(j){return {ok:r.ok,body:j};});})
      .then(function(res){
        if(res.ok&&res.body.ok){
          log('ok','✓ '+ (res.body.message||'دستور در صف Edge قرار گرفت')+' · '+res.body.uuid);
        }else{
          log('warn','✗ '+(res.body.error||res.body.message||'خطا در صف‌بندی'));
        }
      }).catch(function(){log('warn','✗ خطای شبکه در ارسال دستور');});
  }
  document.querySelectorAll('.ctl-btn').forEach(function(b){
    b.addEventListener('click',function(){
      var spec=CMD_MAP[b.getAttribute('data-cmd')];
      if(spec)queueCommand(spec);
    });
  });

  var pr=document.getElementById('pwRange');
  pr.addEventListener('input',function(){document.getElementById('pwVal').textContent=(+pr.value).toLocaleString('fa-IR')+' kW';});
  pr.addEventListener('change',function(){
    queueCommand({type:'set_charging_profile',label:'SetChargingProfile'},{max_kw:+pr.value});
  });

  if(window.__REMOTE_STATION.liveUrl){
    setInterval(function(){
      fetch(window.__REMOTE_STATION.liveUrl,{headers:{'Accept':'application/json'}})
        .then(function(r){return r.json();})
        .then(function(data){
          window.__REMOTE_STATION._liveOnline=!!data.online;
          mergeLive(data.connectors||[]);
        }).catch(function(){});
    },10000);
  }

  if(window.__REMOTE_STATION.commandsUrl){
    var seenCmds={};
    setInterval(function(){
      fetch(window.__REMOTE_STATION.commandsUrl,{headers:{'Accept':'application/json'}})
        .then(function(r){return r.json();})
        .then(function(data){
          (data.commands||[]).forEach(function(c){
            if(!c.uuid||seenCmds[c.uuid])return;
            if(c.status==='pending'||c.status==='dispatched')return;
            seenCmds[c.uuid]=1;
            var st=c.status==='completed'?'ok':'warn';
            log(st,'نتیجه Edge: '+c.type+' · '+c.status+(c.error?' · '+c.error:''));
          });
        }).catch(function(){});
    },12000);
  }

  var anpr=window.__REMOTE_STATION.anpr;
  if(anpr&&anpr.demo&&anpr.demo.d2&&window.plate){
    document.getElementById('stDrPlate').innerHTML=window.plate({d2:anpr.demo.d2,let:anpr.demo['let'],d3:anpr.demo.d3,prov:anpr.demo.prov});
  }
  document.querySelectorAll('[data-plate]').forEach(function(el){
    try{var p=JSON.parse(el.getAttribute('data-plate'));el.innerHTML=window.plate(Object.assign({size:'sm'},p));}catch(e){}
  });

  if(anpr&&anpr.enabled&&window.__REMOTE_STATION.stationId){
    setInterval(function(){
      fetch((window.__REMOTE_STATION.anprLiveUrl||'/admin/api/anpr/live')+'?station_id='+encodeURIComponent(window.__REMOTE_STATION.stationId),{headers:{'Accept':'application/json'}})
        .then(function(r){return r.json();})
        .then(function(data){
          if(!data.demo||!data.demo.d2)return;
          var d=data.demo;
          if(window.plate)document.getElementById('stDrPlate').innerHTML=window.plate({d2:d.d2,let:d['let'],d3:d.d3,prov:d.prov});
          var tag=document.getElementById('stCamTag');if(tag)tag.textContent=d.d2+' '+d['let']+' '+d.d3;
          var nm=document.getElementById('stDrName');if(nm)nm.textContent=(d.name||'—')+' · شناسایی شد';
          var st=document.getElementById('stDrStatus');if(st)st.textContent=d.status||'—';
          var bx=document.getElementById('stCamBox');if(bx)bx.style.opacity='1';
        }).catch(function(){});
    },15000);
  }

})();
</script>
@endpush
