@extends('admin.layouts.app', ['page' => 'monitoring', 'title' => 'مانیتورینگ زنده شبکه', 'crumb' => 'مدیریت شبکه · NOC'])

@section('content')
@php use App\Support\Persian; @endphp

<div class="flex between items-center mt-8" style="margin-bottom:16px">
      <div class="flex items-center gap-12">
        <span class="pill pill-green"><span class="pdot"></span>پایش لحظه‌ای فعال</span>
        <span class="muted fs12">آخرین به‌روزرسانی: <b id="clock" class="tnum">—</b></span>
      </div>
      <div class="flex gap-8">
        <select class="fselect" id="stationFilter" style="width:auto;min-width:160px">
          <option value="">همهٔ ایستگاه‌ها</option>
          @foreach($stations ?? [] as $st)
            <option value="{{ $st->id }}">{{ $st->name }}</option>
          @endforeach
        </select>
        <button class="btn btn-sm"><span class="bic" data-ic="refresh"></span> همگام‌سازی</button>
      </div>
    </div>

    <!-- KPI شبکه -->
    <div class="kpi-grid">
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="plug"></div><div><div class="ms-v tnum">{{ Persian::digits($online_connectors) }}</div><div class="ms-l">نازل آنلاین</div></div><span class="ms-spark" style="color:var(--green)">▲</span></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="bolt"></div><div><div class="ms-v tnum">{{ Persian::digits($charging_connectors) }}</div><div class="ms-l">در حال شارژ</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="activity"></div><div><div class="ms-v tnum">{{ Persian::digits($load_mw) }} <small>MW</small></div><div class="ms-l">بار لحظه‌ای شبکه</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="refresh"></div><div><div class="ms-v tnum">{{ Persian::digits($sync_health) }}٪</div><div class="ms-l">سلامت همگام‌سازی</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-red" data-ic="alert"></div><div><div class="ms-v tnum">{{ Persian::digits($active_faults) }}</div><div class="ms-l">خطای فعال</div></div></div>
    </div>

    <div class="grid col-2-1 mt-16">
      <!-- بار شبکه -->
      <div class="card">
        <div class="card-head"><div><h3>بار توان شبکه</h3><div class="ch-sub">۶۰ دقیقهٔ گذشته · مگاوات</div></div>
          <div class="head-actions"><span class="pill pill-green"><span class="pdot"></span>زنده</span></div>
        </div>
        <div class="card-pad">
          <svg id="loadChart" width="100%" height="170" viewBox="0 0 720 170" preserveAspectRatio="none"></svg>
        </div>
      </div>
      <!-- سلامت زیرساخت -->
      <div class="card">
        <div class="card-head"><h3>سلامت زیرساخت</h3></div>
        <div class="card-pad" style="padding-top:8px">
          @php
            $pendingQueue = \App\Models\SyncInbox::where('status', 'pending')->count();
            $edgeTotal = \App\Models\EdgeStation::count();
            $edgeOffline = \App\Models\EdgeStation::where('sync_status', '!=', 'online')->count();
            $notifToday = \App\Models\UserNotification::whereDate('sent_at', today())->count();
            $notifRead = \App\Models\UserNotification::whereDate('sent_at', today())->whereNotNull('read_at')->count();
            $deliveryPct = $notifToday > 0 ? (int) round($notifRead / $notifToday * 100) : 100;
          @endphp
          <div class="hrow"><span class="h-ic ic-green" data-ic="refresh"></span><div class="l-main"><div class="l-title">سرور مرکزی (CSMS)</div><div class="l-sub">سلامت {{ Persian::digits($sync_health) }}٪</div></div><span class="pill pill-green">سالم</span></div>
          <div class="hrow"><span class="h-ic {{ $pendingQueue > 500 ? 'ic-amber' : 'ic-green' }}" data-ic="activity"></span><div class="l-main"><div class="l-title">صف رویداد (Queue)</div><div class="l-sub">{{ Persian::digits($pendingQueue) }} رویداد در انتظار</div></div><span class="pill {{ $pendingQueue > 500 ? 'pill-amber' : 'pill-green' }}">{{ $pendingQueue > 500 ? 'هشدار' : 'سالم' }}</span></div>
          <div class="hrow"><span class="h-ic {{ $edgeOffline > 0 ? 'ic-amber' : 'ic-green' }}" data-ic="station"></span><div class="l-main"><div class="l-title">کنترلرهای لبه</div><div class="l-sub">{{ Persian::digits($edgeOffline) }} آفلاین از {{ Persian::digits($edgeTotal) }}</div></div><span class="pill {{ $edgeOffline > 0 ? 'pill-amber' : 'pill-green' }}">{{ $edgeOffline > 0 ? 'هشدار' : 'سالم' }}</span></div>
          <div class="hrow"><span class="h-ic ic-green" data-ic="bolt"></span><div class="l-main"><div class="l-title">بار لحظه‌ای</div><div class="l-sub">{{ Persian::digits($load_mw) }} MW · {{ Persian::digits($charging_connectors) }} کانکتور فعال</div></div><span class="pill pill-green">سالم</span></div>
          <div class="hrow" style="border-bottom:none"><span class="h-ic ic-green" data-ic="bell"></span><div class="l-main"><div class="l-title">سامانهٔ اعلان</div><div class="l-sub">{{ Persian::digits($notifToday) }} ارسال امروز · تحویل {{ Persian::digits($deliveryPct) }}٪</div></div><span class="pill pill-green">سالم</span></div>
        </div>
      </div>
    </div>

    <!-- مانیتورینگ ایستگاه‌ها (کارتی) -->
    <div class="flex between items-center mt-16" style="margin-bottom:14px">
      <div><h3 style="font-size:17px">مانیتورینگ ایستگاه‌ها</h3><div class="muted fs12">هر باکس یک ایستگاه · وضعیت کانکتورها، بهره‌برداری و Sync</div></div>
      <div class="head-actions">
        <div class="seg" id="filterSeg"><button class="active">همه</button><button>سالم</button><button>پرتراکم</button><button>خطا/آفلاین</button></div>
        <div class="search" style="max-width:200px"><input id="search" placeholder="جستجوی ایستگاه…"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
      </div>
    </div>
    <div class="station-grid" id="stationGrid"></div>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:12px;box-shadow:var(--shadow-sm);position:relative}
.ms-ic{width:42px;height:42px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:21px;height:21px}
.ms-v{font-size:21px;font-weight:800}.ms-v small{font-size:11px;font-weight:600;color:var(--text-2)}.ms-l{font-size:11.5px;color:var(--muted)}
.ms-spark{margin-inline-start:auto;font-size:13px}
.ic-violet{background:color-mix(in srgb,var(--violet) 12%,white);color:var(--violet)}
html[data-theme="dark"] .ic-violet{background:color-mix(in srgb,var(--violet) 26%,#0e1a26);color:#b794f4}
.ic-red{background:var(--red-soft);color:var(--red)}
.ic-amber{background:var(--amber-soft);color:var(--amber)}
.hrow{display:flex;align-items:center;gap:12px;padding:11px 0;border-bottom:1px solid var(--border-2)}
.hrow .h-ic{width:38px;height:38px;border-radius:11px;display:grid;place-items:center;flex:none}.hrow .h-ic svg{width:19px;height:19px}
.hrow .l-main{flex:1;min-width:0}
.kpi-grid{grid-template-columns:repeat(5,1fr)}
@media(max-width:1100px){.kpi-grid{grid-template-columns:repeat(2,1fr)}}
.soc-mini{display:flex;align-items:center;gap:7px}
.soc-bar{width:42px;height:6px;border-radius:6px;background:var(--surface-3);overflow:hidden;flex:none}
.soc-bar i{display:block;height:100%;border-radius:6px}
.sync-dot{display:inline-flex;align-items:center;gap:5px;font-size:11.5px;font-weight:700}
.sync-dot::before{content:"";width:8px;height:8px;border-radius:50%}
.sync-ok{color:var(--green)}.sync-ok::before{background:var(--green)}
.sync-lag{color:var(--amber)}.sync-lag::before{background:var(--amber)}
.sync-off{color:var(--red)}.sync-off::before{background:var(--red)}
.temp-warn{color:var(--red);font-weight:700}

/* کارت ایستگاه */
.station-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
@media(max-width:1200px){.station-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:760px){.station-grid{grid-template-columns:1fr}}
.scard{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);box-shadow:var(--shadow-sm);overflow:hidden;border-top:3px solid var(--green)}
.scard.warn{border-top-color:var(--amber)}
.scard.bad{border-top-color:var(--red)}
.scard-head{display:flex;align-items:flex-start;gap:11px;padding:15px 16px 12px}
.scard-ic{width:40px;height:40px;border-radius:11px;display:grid;place-items:center;flex:none;background:var(--accent-soft);color:var(--accent-strong)}.scard-ic svg{width:20px;height:20px}
.scard-name{font-weight:800;font-size:15px}
.scard-loc{font-size:11.5px;color:var(--muted);margin-top:1px}
.scard-st{margin-inline-start:auto;flex:none}
.scard-metrics{display:flex;padding:0 16px 12px;gap:8px}
.smetric{flex:1;background:var(--surface-2);border:1px solid var(--border-2);border-radius:11px;padding:9px 8px;text-align:center}
.smetric .sm-v{font-size:15px;font-weight:800;line-height:1.1}.smetric .sm-v small{font-size:9.5px;font-weight:600;color:var(--text-2)}
.smetric .sm-l{font-size:10px;color:var(--muted);margin-top:3px}
.scard-util{padding:0 16px 12px}
.util-top{display:flex;justify-content:space-between;font-size:11.5px;margin-bottom:6px}
.util-bar{height:7px;border-radius:7px;background:var(--surface-3);overflow:hidden}
.util-bar i{display:block;height:100%;border-radius:7px;background:linear-gradient(90deg,var(--green),#4ade80)}
.util-bar.hi i{background:linear-gradient(90deg,var(--orange),#fbbf24)}
.util-bar.full i{background:linear-gradient(90deg,var(--red),#f87171)}
.scard-conns{padding:11px 16px;border-top:1px solid var(--border-2);background:var(--surface-2)}
.conn-legend{display:flex;gap:5px}
.nozzle{flex:1;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:10.5px;font-weight:800;position:relative;cursor:default}
.nozzle.charging{background:var(--green);color:#fff}
.nozzle.free{background:var(--green-soft);color:var(--green)}
.nozzle.fault{background:var(--red);color:#fff}
.nozzle.offline{background:var(--surface-3);color:var(--muted)}
.nozzle.reserved{background:var(--accent-soft);color:var(--accent-strong)}
.nozzle .nz-pct{position:absolute;bottom:-1px;left:0;right:0;height:3px;background:rgba(255,255,255,.4)}
.nozzle .nz-pct i{display:block;height:100%;background:#fff}
.scard-foot{display:flex;align-items:center;justify-content:space-between;padding:11px 16px;border-top:1px solid var(--border-2);font-size:12px}
.scard-users{font-size:11px;color:var(--muted);padding:0 16px 12px;line-height:1.7}
.scard-users b{color:var(--text-2)}
</style>
@endpush

@push('scripts')
<script>
window.__MONITORING_STATIONS = {!! json_encode(app(App\Services\Admin\AdminViewDataService::class)->monitoringStationsJs(), JSON_UNESCAPED_UNICODE) !!};

(function(){
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});
  function toFa(n){return String(n).replace(/[0-9]/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d];});}

  // ساعت زنده
  function tick(){var d=new Date();document.getElementById('clock').textContent=toFa(('۰'+d.getHours()).slice(-2)+':'+('۰'+d.getMinutes()).slice(-2)+':'+('۰'+d.getSeconds()).slice(-2));}
  tick();setInterval(tick,1000);

  // نمودار بار شبکه (از DB)
  (function(){
    var vals=(@json($loadChart ?? [])).map(function(r){return Math.max(0,Math.round((r.energy||0)/10));});
    if(!vals.length){vals=[0];}
    var W=720,H=170,pad=12,max=Math.max(1,Math.max.apply(null,vals));
    function p(area){var step=(W-pad*2)/(vals.length-1);var d=vals.map(function(v,i){var x=pad+i*step;var y=pad+(H-pad*2)*(1-v/max);return (i?'L':'M')+x.toFixed(1)+' '+y.toFixed(1);}).join(' ');if(area)d+=' L'+(W-pad)+' '+(H-pad)+' L'+pad+' '+(H-pad)+' Z';return d;}
    var grid='';for(var g=0;g<=3;g++){var y=pad+(H-pad*2)*g/3;grid+='<line x1="'+pad+'" y1="'+y+'" x2="'+(W-pad)+'" y2="'+y+'" stroke="var(--border)" stroke-width="1" stroke-dasharray="3 5"/>';}
    document.getElementById('loadChart').innerHTML='<defs><linearGradient id="gl" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="var(--accent)" stop-opacity=".26"/><stop offset="1" stop-color="var(--accent)" stop-opacity="0"/></linearGradient></defs>'+grid+'<path d="'+p(true)+'" fill="url(#gl)"/><path d="'+p()+'" fill="none" stroke="var(--accent)" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>';
  })();

  // داده ایستگاه‌ها از DB (بدون بازنویسی کانکتورها)
  var STATIONS=(window.__MONITORING_STATIONS||[]).map(function(st){
    st.conns=st.conns||[];
    st.n=st.n||st.conns.length;
    st.charging=st.charging!=null?st.charging:st.conns.filter(function(c){return c.state==='charging';}).length;
    st.totalkw=st.totalkw!=null?st.totalkw:st.conns.reduce(function(a,c){return a+(c.pw||0);},0);
    return st;
  });

  var stPill={charging:'charging',free:'free',fault:'fault',offline:'offline',reserved:'reserved'};
  var syncMap={ok:['sync-ok','همگام'],lag:['sync-lag','تأخیر'],off:['sync-off','قطع']};
  var healthPill={ok:['سالم','pill-green'],warn:['پرتراکم','pill-amber'],bad:['خطا','pill-red']};
  var curFilter='all',curQ='',curStationId='';

  function render(filter,q){
    var html=STATIONS.filter(function(st){
      var fOk=filter==='all'||(filter==='ok'&&st.health==='ok')||(filter==='warn'&&st.health==='warn')||(filter==='bad'&&(st.health==='bad'));
      var nq=window.AdminUI?window.AdminUI.normalize(q):q;
      var nName=window.AdminUI?window.AdminUI.normalize(st.name+' '+st.loc):st.name.toLowerCase();
      var qOk=!nq||nName.indexOf(nq)>-1;
      var stOk=!curStationId||String(st.id)===String(curStationId);
      return fOk&&qOk&&stOk;
    }).map(function(st){
      var hp=healthPill[st.health],sy=syncMap[st.sync];
      var loadCls=st.load>=90?'full':(st.load>=70?'hi':'');
      var nozzles=st.conns.map(function(c){
        var pct=c.state==='charging'?'<span class="nz-pct"><i style="width:'+c.soc+'%"></i></span>':'';
        var lbl=c.state==='charging'?toFa(c.pw)+'kW':(c.state==='free'?c.id:(c.state==='fault'?'!':(c.state==='offline'?'⏻':c.id)));
        var title=c.id+' · '+c.type+(c.state==='charging'?(' · '+(c.user||'—')+' · '+toFa(c.soc||0)+'٪'):'');
        return '<span class="nozzle '+c.state+'" title="'+title+'">'+lbl+pct+'</span>';
      }).join('');
      var activeUsers=st.conns.filter(function(c){return c.state==='charging'&&c.user;}).slice(0,2).map(function(c){return c.user+' ('+toFa(c.soc||0)+'٪)';}).join(' · ');
      return '<div class="scard '+(st.health==='ok'?'':st.health)+'">'+
        '<div class="scard-head"><div class="scard-ic">'+window.icon('station')+'</div>'+
        '<div><div class="scard-name">'+st.name+'</div><div class="scard-loc">'+st.loc+'</div></div>'+
        '<span class="pill '+hp[1]+' scard-st">'+(st.health==='ok'?'<span class="pdot"></span>':'')+hp[0]+'</span></div>'+
        '<div class="scard-metrics">'+
          '<div class="smetric"><div class="sm-v">'+toFa(st.charging)+'<small>/'+toFa(st.n)+'</small></div><div class="sm-l">در حال شارژ</div></div>'+
          '<div class="smetric"><div class="sm-v">'+toFa(st.totalkw)+'<small> kW</small></div><div class="sm-l">توان لحظه‌ای</div></div>'+
          '<div class="smetric"><div class="sm-v"><span class="sync-dot '+sy[0]+'" style="justify-content:center">'+sy[1]+'</span></div><div class="sm-l">همگام‌سازی</div></div>'+
        '</div>'+
        '<div class="scard-util"><div class="util-top"><span class="muted">بهره‌برداری</span><b class="tnum">'+toFa(st.load)+'٪</b></div><div class="util-bar '+loadCls+'"><i style="width:'+st.load+'%"></i></div></div>'+
        '<div class="scard-conns"><div class="conn-legend">'+nozzles+'</div></div>'+
        (activeUsers?'<div class="scard-users">در حال شارژ: <b>'+activeUsers+'</b></div>':'')+
        '<div class="scard-foot"><a href="'+(st.remoteUrl||'/admin/remote')+'" class="btn btn-sm">کنترل از راه دور</a><span class="muted fs12">'+toFa(st.conns.filter(function(c){return c.state==='free';}).length)+' آزاد</span></div>'+
      '</div>';
    }).join('');
    document.getElementById('stationGrid').innerHTML=html||'<div style="grid-column:1/-1;text-align:center;padding:30px;color:var(--muted)">ایستگاهی یافت نشد</div>';
  }
  render('all','');

  var keys=['all','ok','warn','bad'];
  document.querySelectorAll('#filterSeg button').forEach(function(b,i){b.addEventListener('click',function(){document.querySelectorAll('#filterSeg button').forEach(function(x){x.classList.remove('active');});b.classList.add('active');curFilter=keys[i];render(curFilter,curQ);});});
  document.getElementById('search').addEventListener('input',function(){curQ=this.value.trim();render(curFilter,curQ);});
  var stationFilter=document.getElementById('stationFilter');
  if(stationFilter){stationFilter.addEventListener('change',function(){curStationId=stationFilter.value;render(curFilter,curQ);});}

  // رفرش دوره‌ای از سرور (هر ۶۰ ثانیه)
  setInterval(function(){location.reload();},60000);
})();
</script>
@endpush
