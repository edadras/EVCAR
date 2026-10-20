@extends('panel.layouts.app', ['stationName' => $syncStatus['station']['name'] ?? null])

@section('title', 'سینک داده')
@section('heading', 'سینک داده با مرکز ابری')

@section('content')
<div class="banner">
    <strong>سینک خودکار فعال است.</strong>
    هر دقیقه فقط <em>تغییرات</em> (Delta) بین ایستگاه و مرکز رد و بدل می‌شود؛ رویدادهای آفلاین در صف محلی می‌مانند و با وصل شدن ارتباط، با تلاش مجدد (Retry) خودکار ارسال می‌شوند.
    اسنپ‌شات کامل فقط در <strong>اولین اجرا</strong> یا به‌صورت دستی انجام می‌شود.
</div>

<div class="stat-grid mt16">
    <div class="stat-box"><div class="l">سینک خودکار</div><div class="v" style="font-size:14px" id="autoMode">Delta · {{ $syncStatus['auto_sync']['interval'] ?? 'هر ۱ دقیقه' }}</div></div>
    <div class="stat-box"><div class="l">مرکز ابری</div><div class="v" style="font-size:16px" id="cloudState">{{ ($syncStatus['cloud']['reachable'] ?? false) ? 'متصل' : 'آفلاین' }}</div></div>
    <div class="stat-box"><div class="l">صف ارسال</div><div class="v tnum" id="pendingCount">{{ \App\Support\Persian::num($syncStatus['outbox']['pending'] ?? 0) }}</div></div>
    <div class="stat-box"><div class="l">خطای ارسال</div><div class="v tnum" id="retryCount">{{ \App\Support\Persian::num($syncStatus['outbox']['retry'] ?? 0) }}</div></div>
    <div class="stat-box"><div class="l">ارسال‌شده</div><div class="v tnum" id="ackedCount">{{ \App\Support\Persian::num($syncStatus['outbox']['acked'] ?? 0) }}</div></div>
    <div class="stat-box"><div class="l">کاربران کش</div><div class="v tnum" id="cachedUsers">{{ \App\Support\Persian::num($syncStatus['cached']['users'] ?? 0) }}</div></div>
    <div class="stat-box"><div class="l">جلسات منتظر سینک</div><div class="v tnum" id="pendingSessions">{{ \App\Support\Persian::num($syncStatus['local_pending']['sessions'] ?? 0) }}</div></div>
</div>

<div class="card mt16">
    <div class="card-head">
        <h3>همگام‌سازی فوری (اختیاری)</h3>
        <span class="muted fs12" id="lastAutoAt">آخرین سینک خودکار: —</span>
    </div>
    <div class="card-pad flex gap-8" style="flex-wrap:wrap">
        <button type="button" class="btn btn-primary" id="btnNow"><span data-ic="refresh"></span> هم‌اکنون همگام‌سازی</button>
        <button type="button" class="btn" id="btnSnapshot"><span data-ic="box"></span> اسنپ‌شات کامل (بازیابی)</button>
    </div>
    <div class="card-pad muted fs12" style="border-top:1px solid var(--border-2)">
        نیازی به ارسال/دریافت دستی نیست؛ scheduler هر دقیقه push صف، pull تغییرات و دستورات مرکز را اجرا می‌کند.
        <span id="nextRetryHint"></span>
    </div>
</div>

<div class="tabs mt16">
    <button type="button" class="tab active" data-tab="outbox">صف ارسال (Outbox)</button>
    <button type="button" class="tab" data-tab="cached">داده از مرکز</button>
    <button type="button" class="tab" data-tab="commands">دستورات مرکز</button>
    <button type="button" class="tab" data-tab="activity">لاگ سینک</button>
</div>

<div class="tab-panel active" id="tab-outbox">
    <div class="card">
        <div class="card-head">
            <h3>رویدادهای در صف / ارسال‌شده</h3>
            <div class="flex gap-8">
                <select id="outboxFilter" class="input-sm">
                    <option value="">همه</option>
                    <option value="pending">در انتظار</option>
                    <option value="retry">خطا / تلاش مجدد</option>
                    <option value="acked">ارسال‌شده</option>
                </select>
            </div>
        </div>
        <div class="card-pad" style="padding-top:0;overflow-x:auto">
            <table class="table" id="outboxTable">
                <thead><tr><th>نوع</th><th>وضعیت</th><th>زمان</th><th>تلاش</th><th>جزئیات</th><th>خطا</th></tr></thead>
                <tbody><tr><td colspan="6" class="muted">در حال بارگذاری…</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<div class="tab-panel" id="tab-cached">
    <div class="grid col-2">
        <div class="card">
            <div class="card-head"><h3>خلاصه داده کش‌شده</h3></div>
            <div class="card-pad fs13" id="cachedSummary">—</div>
        </div>
        <div class="card">
            <div class="card-head"><h3>جلسات منتظر ارسال به مرکز</h3></div>
            <div class="card-pad" style="padding-top:0;overflow-x:auto">
                <table class="table" id="pendingSessionsTable">
                    <thead><tr><th>وضعیت</th><th>شروع</th><th>انرژی</th><th>هزینه</th></tr></thead>
                    <tbody><tr><td colspan="4" class="muted">—</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="card mt16">
        <div class="card-head"><h3>کاربران کش‌شده از مرکز</h3></div>
        <div class="card-pad" style="padding-top:0;overflow-x:auto">
            <table class="table" id="usersTable">
                <thead><tr><th>نام</th><th>موبایل</th><th>وضعیت</th><th>کیف پول</th><th>آخرین سینک</th></tr></thead>
                <tbody><tr><td colspan="5" class="muted">—</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<div class="tab-panel" id="tab-commands">
    <div class="card">
        <div class="card-head"><h3>دستورات دریافتی از مرکز ابری</h3></div>
        <div class="card-pad" style="padding-top:0;overflow-x:auto">
            <table class="table" id="cloudCommandsTable">
                <thead><tr><th>نوع</th><th>وضعیت</th><th>زمان</th><th>شناسه مرکز</th><th>خطا</th></tr></thead>
                <tbody><tr><td colspan="5" class="muted">هنوز دستوری از مرکز دریافت نشده — دکمه «دریافت دستورات ابری» را بزنید.</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<div class="tab-panel" id="tab-activity">
    <div class="card">
        <div class="card-head"><h3>تاریخچه عملیات سینک</h3></div>
        <div class="card-pad" style="padding-top:0">
            <div class="console" id="activityLog" style="max-height:420px"></div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:0}
.tab{padding:10px 16px;border:1px solid var(--border-2);border-radius:10px;background:var(--surface);cursor:pointer;font-size:13px;font-weight:600;color:var(--muted)}
.tab.active{background:var(--accent-soft);border-color:var(--accent);color:var(--accent)}
.tab-panel{display:none;margin-top:16px}.tab-panel.active{display:block}
.input-sm{padding:6px 10px;border:1px solid var(--border-2);border-radius:8px;font-size:13px;background:var(--surface)}
.console{background:#0C2236;border-radius:12px;padding:14px;font-family:ui-monospace,monospace;font-size:12px;color:#cfe0f0;line-height:1.8;overflow-y:auto}
.aline.ok{color:#4ade80}.aline.fail{color:#f87171}.aline.partial{color:#fbbf24}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var statusUrl=@json(route('panel.api.sync.status'));
  var outboxUrl=@json(route('panel.api.sync.outbox'));
  var cachedUrl=@json(route('panel.api.sync.cached'));
  var activityUrl=@json(route('panel.api.sync.activity'));
  var commandsUrl=@json(route('panel.api.commands.recent'))+'?source=cloud';
  var pullUrl=@json(route('panel.api.sync.pull'));

  function csrf(){return document.querySelector('meta[name=csrf-token]').getAttribute('content');}
  function fa(n){return (n||0).toLocaleString('fa-IR');}
  function fmtTime(iso){if(!iso)return '—';try{return new Date(iso).toLocaleString('fa-IR');}catch(e){return iso;}}
  function esc(s){var d=document.createElement('div');d.textContent=s||'';return d.innerHTML;}

  function refreshStatus(){
    fetch(statusUrl,{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(s){
      document.getElementById('cloudState').textContent=s.cloud.reachable?'متصل':'آفلاین';
      document.getElementById('pendingCount').textContent=fa(s.outbox.pending);
      document.getElementById('retryCount').textContent=fa(s.outbox.retry||s.outbox.failed||0);
      document.getElementById('ackedCount').textContent=fa(s.outbox.acked||0);
      document.getElementById('cachedUsers').textContent=fa(s.cached.users);
      document.getElementById('pendingSessions').textContent=fa(s.local_pending.sessions);
      if(s.auto_sync){
        document.getElementById('autoMode').textContent=(s.auto_sync.mode==='delta'?'Delta':'اسنپ‌شات اولیه')+' · '+(s.auto_sync.interval||'هر ۱ دقیقه');
        document.getElementById('lastAutoAt').textContent='آخرین سینک خودکار: '+(s.auto_sync.last_auto_at?fmtTime(s.auto_sync.last_auto_at):'—');
        var hint=document.getElementById('nextRetryHint');
        if(hint)hint.textContent=s.auto_sync.next_retry_at&&!s.cloud.reachable?' تلاش بعدی: '+fmtTime(s.auto_sync.next_retry_at):'';
      }
    }).catch(function(){});
  }

  function loadOutbox(){
    var f=document.getElementById('outboxFilter').value;
    fetch(outboxUrl+(f?'?filter='+encodeURIComponent(f):''),{headers:{'Accept':'application/json'}})
      .then(function(r){return r.json();})
      .then(function(data){
        var tb=document.querySelector('#outboxTable tbody');
        var items=data.items||[];
        if(!items.length){tb.innerHTML='<tr><td colspan="6" class="muted">رویدادی در صف نیست.</td></tr>';return;}
        tb.innerHTML=items.map(function(it){
          var st=it.status;
          if(st==='pending'&&it.last_error)st='retry';
          return '<tr><td><code>'+esc(it.event_type)+'</code></td><td>'+esc(st)+'</td><td class="tnum fs12">'+fmtTime(it.occurred_at)+'</td><td class="tnum">'+fa(it.attempts)+'</td><td class="fs12 muted">'+esc(it.payload_preview)+'</td><td class="fs12" style="color:var(--red)">'+esc(it.last_error||'—')+'</td></tr>';
        }).join('');
      });
  }

  function loadCached(){
    fetch(cachedUrl,{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(data){
      var s=data.summary||{};
      document.getElementById('cachedSummary').innerHTML=
        '<div>کاربران: <strong>'+fa(s.users)+'</strong> (محلی: '+fa(s.users_local_only)+' · منتظر: '+fa(s.users_pending)+')</div>'+
        '<div class="mt8">کیف‌پول: '+fa(s.wallets)+' · تعرفه: '+fa(s.tariffs)+' · شارژر: '+fa(s.chargers)+'</div>';
      var ps=data.pending_sessions||[];
      document.querySelector('#pendingSessionsTable tbody').innerHTML=ps.length?ps.map(function(s){
        return '<tr><td>'+esc(s.status)+'</td><td class="tnum fs12">'+fmtTime(s.started_at)+'</td><td class="tnum">'+fa(s.energy_kwh)+' kWh</td><td class="tnum">'+fa(Math.round(s.cost_amount))+'</td></tr>';
      }).join(''):'<tr><td colspan="4" class="muted">جلسه‌ای در انتظار ارسال نیست.</td></tr>';
      var users=data.users||[];
      document.querySelector('#usersTable tbody').innerHTML=users.length?users.map(function(u){
        return '<tr><td>'+esc(u.name||'—')+'</td><td class="tnum">'+esc(u.phone||'—')+'</td><td>'+(u.pending_sync?'منتظر':'سینک')+(u.is_local_only?' · محلی':'')+'</td><td class="tnum">'+fa(Math.round(u.wallet_balance))+'</td><td class="fs12">'+fmtTime(u.synced_at)+'</td></tr>';
      }).join(''):'<tr><td colspan="5" class="muted">کاربری کش نشده — ابتدا Delta یا اسنپ‌شات بگیرید.</td></tr>';
    });
  }

  function loadCommands(){
    fetch(commandsUrl,{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(data){
      var items=data.commands||[];
      document.querySelector('#cloudCommandsTable tbody').innerHTML=items.length?items.map(function(c){
        return '<tr><td><code>'+esc(c.type)+'</code></td><td>'+esc(c.status)+'</td><td class="fs12">'+fmtTime(c.at)+'</td><td class="fs11 muted">'+esc((c.cloud_command_uuid||'').slice(0,8))+'…</td><td class="fs12" style="color:var(--red)">'+esc(c.error||'—')+'</td></tr>';
      }).join(''):'<tr><td colspan="5" class="muted">هنوز دستوری از مرکز دریافت نشده.</td></tr>';
    });
  }

  function loadActivity(){
    fetch(activityUrl,{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(data){
      var el=document.getElementById('activityLog');
      var items=data.items||[];
      if(!items.length){el.innerHTML='<div class="muted">هنوز عملیات سینکی ثبت نشده.</div>';return;}
      el.innerHTML=items.map(function(a){
        var cls=a.status==='completed'?'ok':(a.status==='failed'?'fail':'partial');
        return '<div class="aline '+cls+'">'+fmtTime(a.at)+' · '+esc(a.action_label)+' · '+esc(a.status)+(a.message?' · '+esc(a.message):'')+(a.error?' · '+esc(a.error):'')+'</div>';
      }).join('');
    });
  }

  function post(url,body){
    return fetch(url,{method:'POST',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf(),'Content-Type':'application/json'},body:body?JSON.stringify(body):'{}'})
      .then(function(r){return r.json().then(function(j){return {ok:r.ok,j:j};});});
  }

  document.querySelectorAll('.tab').forEach(function(t){
    t.addEventListener('click',function(){
      document.querySelectorAll('.tab').forEach(function(x){x.classList.remove('active');});
      document.querySelectorAll('.tab-panel').forEach(function(x){x.classList.remove('active');});
      t.classList.add('active');
      document.getElementById('tab-'+t.getAttribute('data-tab')).classList.add('active');
      if(t.getAttribute('data-tab')==='cached')loadCached();
      if(t.getAttribute('data-tab')==='commands')loadCommands();
      if(t.getAttribute('data-tab')==='activity')loadActivity();
    });
  });

  document.getElementById('outboxFilter').addEventListener('change',loadOutbox);
  document.getElementById('btnNow').addEventListener('click',function(){
    post(pullUrl,{now:true}).then(function(){refreshStatus();loadOutbox();loadActivity();loadCached();loadCommands();});
  });
  document.getElementById('btnSnapshot').addEventListener('click',function(){
    post(pullUrl,{full:true}).then(function(){refreshStatus();loadOutbox();loadActivity();loadCached();});
  });

  loadOutbox();loadActivity();refreshStatus();
  setInterval(refreshStatus,15000);
  setInterval(function(){loadOutbox();loadActivity();},20000);
})();
</script>
@endpush
