@extends('admin.layouts.app', ['page' => 'anpr', 'title' => 'شارژ خودکار با تشخیص پلاک', 'crumb' => 'مدیریت شبکه'])

@section('content')
@php use App\Support\Persian; @endphp

<div class="flex between items-center mt-8" style="margin-bottom:18px">
      <span class="pill pill-green"><span class="pdot"></span><span id="kpiCameras">{{ Persian::digits($kpis['cameras']) }}</span> دوربین ANPR فعال · شناسایی خودکار خودرو</span>
      <div class="flex gap-8 items-center">
        <select id="stationFilter" class="inp" style="min-width:200px">
          <option value="">همه ایستگاه‌ها</option>
          @foreach($stations as $st)
          <option value="{{ $st['id'] }}" @selected(($filters['station_id'] ?? null) == $st['id'])>{{ $st['name'] }}</option>
          @endforeach
        </select>
        <button class="btn btn-sm"><span class="bic" data-ic="sliders"></span> تنظیمات دوربین</button>
        <button class="btn btn-sm btn-primary"><span class="bic" data-ic="plus"></span> ثبت پلاک جدید</button>
      </div>
    </div>

    <div class="kpi-grid">
      <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="camera"></div><div><div class="ms-v tnum" id="kpiCameras2">{{ Persian::digits($kpis['cameras']) }}</div><div class="ms-l">دوربین فعال</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-green" data-ic="car"></div><div><div class="ms-v tnum" id="kpiPlates">{{ Persian::digits(number_format($kpis['registered_plates'])) }}</div><div class="ms-l">پلاک ثبت‌شده</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="bolt"></div><div><div class="ms-v tnum" id="kpiAuto">{{ Persian::digits($kpis['auto_today']) }}</div><div class="ms-l">شارژ خودکار امروز</div></div></div>
      <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="check"></div><div><div class="ms-v tnum" id="kpiAcc">{{ Persian::digits($kpis['accuracy_pct']) }}٪</div><div class="ms-l">دقت تشخیص</div></div></div>
    </div>

    <div class="grid col-2-1 mt-16">
      <!-- فید دوربین زنده -->
      <div class="card">
        <div class="card-head"><div><h3>فید زندهٔ دوربین — <span id="demoStation">{{ $anprJs['demo']['station'] ?? ($anprJs['station'] ?? 'در انتظار رویداد') }}</span></h3><div class="ch-sub">دوربین ورودی ایستگاه · داده از Edge</div></div>
          <div class="head-actions"><span class="pill pill-red"><span class="pdot"></span>زنده</span></div>
        </div>
        <div class="card-pad">
          <div class="cam">
            <div class="cam-scene">
              <div class="cam-grid"></div>
              <div class="cam-car" id="camCar">
                <svg viewBox="0 0 200 90" width="180"><rect x="14" y="30" width="172" height="40" rx="12" fill="#1f3146"/><path d="M40 30 L60 12 H150 L168 30 Z" fill="#27425c"/><rect x="62" y="16" width="84" height="16" rx="4" fill="#0d1b28"/><circle cx="52" cy="72" r="13" fill="#0d1b28" stroke="#3a536c" stroke-width="3"/><circle cx="156" cy="72" r="13" fill="#0d1b28" stroke="#3a536c" stroke-width="3"/></svg>
              </div>
              <div class="cam-box" id="camBox" style="opacity:0">
                <span class="cb-corner tl"></span><span class="cb-corner tr"></span><span class="cb-corner bl"></span><span class="cb-corner br"></span>
              </div>
              <div class="cam-tag" id="camTag">در حال پایش…</div>
              <div class="cam-meta"><span id="camSource">CAM‑۰۱ · {{ $anprJs['demo']['station'] ?? '—' }}</span><span class="tnum" id="camTime">{{ $anprJs['demo']['time'] ?? '—' }}</span></div>
            </div>
          </div>
          <div class="detect-result" id="result">
            <div class="dr-plate" id="drPlate"></div>
            <div style="flex:1">
              <div class="fw7" id="drName">در انتظار شناسایی…</div>
              <div class="fs12 muted" id="drStatus">دوربین در حال پایش ورودی</div>
            </div>
            <span class="pill" id="drPill">—</span>
          </div>
        </div>
      </div>

      <!-- نحوهٔ کار -->
      <div class="card">
        <div class="card-head"><h3>فرایند شارژ خودکار</h3></div>
        <div class="card-pad">
          <div class="step"><div class="step-n">۱</div><div><div class="fw7 fs13">تشخیص پلاک</div><div class="fs12 muted mt-4">دوربین ANPR پلاک خودروی واردشده را می‌خواند.</div></div></div>
          <div class="step"><div class="step-n">۲</div><div><div class="fw7 fs13">تطبیق با کاربر</div><div class="fs12 muted mt-4">پلاک با حساب کاربری و کیف پول مطابقت داده می‌شود.</div></div></div>
          <div class="step"><div class="step-n">۳</div><div><div class="fw7 fs13">اتصال و احراز</div><div class="fs12 muted mt-4">با اتصال کانکتور، جلسهٔ شارژ به‌صورت خودکار آغاز می‌شود.</div></div></div>
          <div class="step"><div class="step-n">۴</div><div><div class="fw7 fs13">کسر هزینه</div><div class="fs12 muted mt-4">در پایان، هزینه از کیف پول کسر و رسید پیامک می‌شود.</div></div></div>
          <div class="anpr-note mt-12"><span data-ic="shield"></span><span>خودروهای ثبت‌نشده به مسیر پرداخت دستی (QR/RFID) هدایت می‌شوند.</span></div>
        </div>
      </div>
    </div>

    <!-- رویدادهای تشخیص -->
    <div class="card mt-16">
      <div class="card-head">
        <div><h3>رویدادهای تشخیص پلاک</h3><div class="ch-sub">آخرین خودروهای شناسایی‌شده از ایستگاه‌های Edge</div></div>
        <div class="head-actions">
          <div class="search" style="max-width:200px"><input id="evSearch" placeholder="جستجو…" autocomplete="off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div>
          <div class="seg" id="evSeg"><button class="active">همه</button><button>شارژ خودکار</button><button>ناموفق</button></div>
        </div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>زمان</th><th>پلاک</th><th>کاربر</th><th>ایستگاه / دوربین</th><th>اقدام</th><th>دقت</th><th>وضعیت</th></tr></thead>
          <tbody id="evBody"></tbody>
        </table>
      </div>
    </div>

    <!-- پلاک‌های ثبت‌شده -->
    <div class="card mt-16">
      <div class="card-head"><div><h3>پلاک‌های ثبت‌شده برای شارژ خودکار</h3></div>
        <div class="head-actions"><div class="search" style="max-width:240px"><input id="plateSearch" placeholder="جستجوی پلاک یا کاربر…" autocomplete="off"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg></div></div>
      </div>
      <div class="table-wrap">
        <table class="tbl">
          <thead><tr><th>پلاک</th><th>کاربر</th><th>خودرو</th><th>کیف پول</th><th>شارژ خودکار</th><th>آخرین تشخیص</th><th></th></tr></thead>
          <tbody id="plateBody"></tbody>
        </table>
      </div>
    </div>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:16px;display:flex;align-items:center;gap:13px;box-shadow:var(--shadow-sm)}
.ms-ic{width:44px;height:44px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:22px;height:22px}
.ms-v{font-size:22px;font-weight:800}.ms-l{font-size:12px;color:var(--muted)}

.cam{border-radius:14px;overflow:hidden;border:1px solid var(--border)}
.cam-scene{position:relative;height:240px;background:linear-gradient(180deg,#16293c,#0d1b28);overflow:hidden}
.cam-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);background-size:28px 28px;transform:perspective(300px) rotateX(40deg);transform-origin:bottom;opacity:.5}
.cam-car{position:absolute;bottom:42px;left:50%;transform:translateX(-50%);transition:opacity .4s}
.cam-box{position:absolute;bottom:54px;left:50%;transform:translateX(-50%);width:96px;height:32px;transition:opacity .3s}
.cb-corner{position:absolute;width:12px;height:12px;border:2px solid #4ade80}
.cb-corner.tl{top:0;left:0;border-right:none;border-bottom:none}.cb-corner.tr{top:0;right:0;border-left:none;border-bottom:none}
.cb-corner.bl{bottom:0;left:0;border-right:none;border-top:none}.cb-corner.br{bottom:0;right:0;border-left:none;border-top:none}
.cam-tag{position:absolute;bottom:92px;left:50%;transform:translateX(-50%);background:#4ade80;color:#06250f;font-size:12px;font-weight:800;padding:3px 12px;border-radius:20px;white-space:nowrap}
.cam-meta{position:absolute;top:10px;left:12px;right:12px;display:flex;justify-content:space-between;color:#9fb4c8;font-size:11px;font-weight:600}
.cam-meta span:last-child{font-variant-numeric:tabular-nums}

.detect-result{display:flex;align-items:center;gap:14px;margin-top:14px;padding:14px;border-radius:14px;background:var(--surface-2);border:1px solid var(--border-2)}

.step{display:flex;gap:13px;padding:11px 0;border-bottom:1px solid var(--border-2)}.step:last-of-type{border-bottom:none}
.step-n{width:30px;height:30px;border-radius:50%;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;font-weight:800;font-size:13px;flex:none}
.anpr-note{display:flex;align-items:flex-start;gap:9px;background:var(--accent-softer);border-radius:11px;padding:12px;font-size:12px;color:var(--text-2);line-height:1.6}
.anpr-note svg{width:18px;height:18px;color:var(--accent);flex:none;margin-top:1px}
</style>
@endpush

@push('scripts')
<script>
window.__ANPR = {!! json_encode($anprJs, JSON_UNESCAPED_UNICODE) !!};
(function(){
  var DB=window.__ANPR||{};
  var lastEventId=DB.last_event_id||0;
  document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});

  function renderDemo(demo){
    var box=document.getElementById('camBox');
    var tag=document.getElementById('camTag');
    if(!demo||!demo.d2){
      box.style.opacity='0';
      tag.textContent='در حال پایش…';
      document.getElementById('drPlate').innerHTML='';
      document.getElementById('drName').textContent='در انتظار شناسایی…';
      document.getElementById('drStatus').textContent='دوربین در حال پایش ورودی';
      document.getElementById('drPill').className='pill';
      document.getElementById('drPill').textContent='—';
      document.getElementById('camTime').textContent='—';
      return;
    }
    box.style.opacity='1';
    document.getElementById('demoStation').textContent=demo.station||'—';
    document.getElementById('camSource').textContent=(demo.camera||'CAM-01')+' · '+(demo.station||'—');
    document.getElementById('camTime').textContent=demo.time||'—';
    document.getElementById('drPlate').innerHTML=window.plate({d2:demo.d2,'let':demo['let'],d3:demo.d3,prov:demo.prov});
    tag.textContent=demo.d2+' '+demo['let']+' '+demo.d3+' — '+demo.prov;
    document.getElementById('drName').textContent=(demo.name||'—')+' · شناسایی شد';
    document.getElementById('drStatus').textContent=demo.status||'—';
    var pill=demo.pill||['—',''];
    document.getElementById('drPill').className='pill '+(pill[1]||'');
    document.getElementById('drPill').innerHTML=(pill[1]==='pill-green'?'<span class="pdot"></span>':'')+pill[0];
  }

  function renderEvents(ev){
    document.getElementById('evBody').innerHTML=(ev||[]).map(function(e){
      return '<tr data-cat="'+e.cat+'" data-search="'+e.u+' '+e.st+' '+e.act+'"><td class="cell-muted tnum">'+e.t+'</td>'+
        '<td>'+window.plate(Object.assign({size:'sm'},e.p))+'</td>'+
        '<td class="cell-strong">'+e.u+'</td><td class="cell-muted">'+e.st+'</td>'+
        '<td>'+e.act+'</td><td class="tnum cell-muted">'+e.acc+'</td>'+
        '<td><span class="pill '+e.s[1]+'">'+(e.s[1]==='pill-green'?'<span class="pdot"></span>':'')+e.s[0]+'</span></td></tr>';
    }).join('');
  }

  function renderPlates(plates){
    document.getElementById('plateBody').innerHTML=(plates||[]).map(function(r){
      return '<tr data-search="'+r.u+' '+r.car+'"><td>'+window.plate(Object.assign({size:'sm'},r.p))+'</td>'+
        '<td class="cell-strong">'+r.u+'</td><td class="cell-muted">'+r.car+'</td>'+
        '<td class="tnum '+(r.w.length<8?'':'cell-strong')+'">'+r.w+'</td>'+
        '<td>'+(r.auto?'<span class="pill pill-green"><span class="pdot"></span>فعال</span>':'<span class="pill pill-amber">غیرفعال</span>')+'</td>'+
        '<td class="cell-muted">'+r.last+'</td><td><button class="btn btn-sm">مدیریت</button></td></tr>';
    }).join('');
  }

  function renderKpis(k){
    if(!k)return;
    document.getElementById('kpiCameras').textContent=k.cameras;
    document.getElementById('kpiCameras2').textContent=k.cameras;
    document.getElementById('kpiPlates').textContent=Number(k.registered_plates).toLocaleString('fa-IR');
    document.getElementById('kpiAuto').textContent=k.auto_today;
    document.getElementById('kpiAcc').textContent=k.accuracy_pct+'٪';
  }

  function applyBundle(data){
    if(!data)return;
    renderDemo(data.demo);
    renderEvents(data.events);
    renderPlates(data.plates);
    renderKpis(data.kpis);
    if(data.last_event_id)lastEventId=data.last_event_id;
  }

  applyBundle(DB);

  if(window.AdminUI){
    window.AdminUI.bindSegRows('evSeg','#evBody',function(tr,idx){
      var c=tr.getAttribute('data-cat');
      return idx===0||(idx===1&&c==='auto')||(idx===2&&c==='fail');
    });
    window.AdminUI.bindTableSearch('#evSearch','#evBody',function(tr){
      var idx=[].indexOf.call(document.querySelectorAll('#evSeg button'),document.querySelector('#evSeg button.active'));
      var c=tr.getAttribute('data-cat');
      return idx===0||(idx===1&&c==='auto')||(idx===2&&c==='fail');
    });
    window.AdminUI.bindTableSearch('#plateSearch','#plateBody');
  }

  var stationFilter=document.getElementById('stationFilter');
  if(stationFilter){
    stationFilter.addEventListener('change',function(){
      var q=this.value?'?station_id='+encodeURIComponent(this.value):'';
      window.location.href=window.location.pathname+q;
    });
  }

  function pollLive(){
    var sid=stationFilter&&stationFilter.value?stationFilter.value:'';
    var url='/admin/api/anpr/live'+(sid?'?station_id='+encodeURIComponent(sid):'');
    fetch(url,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}})
      .then(function(r){return r.json();})
      .then(function(data){
        if(data.last_event_id&&data.last_event_id!==lastEventId){
          applyBundle(data);
        }else if(data.kpis){
          renderKpis(data.kpis);
        }
      })
      .catch(function(){});
  }
  setInterval(pollLive,12000);
})();
</script>
@endpush
