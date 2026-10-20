@extends('panel.layouts.app')

@section('title', 'تنظیمات ایستگاه')
@section('heading', 'تنظیمات و راه‌اندازی ایستگاه')

@section('content')
<div class="banner">
    در این بخش مسئول فنی می‌تواند <strong>ایستگاه</strong>، <strong>ارتباط مرکز</strong>، <strong>شارژر و کانکتور</strong>،
    <strong>پلاک‌خوان (دوربین مرکز)</strong> و <strong>مودم GSM برای OTP</strong> را پیکربندی کند.
    تعریف اصلی شارژرها از مرکز ابری سینک می‌شود؛ شناسه OCPP و اتصال محلی اینجا تنظیم می‌شود.
</div>

<div class="tabs mt16">
    <button type="button" class="tab active" data-tab="station">ایستگاه</button>
    <button type="button" class="tab" data-tab="devices">دستگاه‌ها</button>
    <button type="button" class="tab" data-tab="anpr">پلاک‌خوان</button>
    <button type="button" class="tab" data-tab="gsm">GSM / OTP</button>
</div>

<div class="tab-panel active" id="tab-station">
    <div class="grid col-2">
        <div class="card">
            <div class="card-head"><h3>مشخصات ایستگاه</h3></div>
            <div class="card-pad">
                <div class="field"><label>نام ایستگاه</label><input id="stName" type="text"></div>
                <div class="field"><label>کد Edge</label><input id="stCode" type="text" disabled></div>
                <div class="field"><label>هاست محلی</label><input id="stHost" type="text"></div>
                <div class="field"><label>WiFi SSID</label><input id="stWifi" type="text"></div>
                <div class="field"><label>منطقه زمانی</label><input id="stTz" type="text"></div>
                <label class="flex items-center gap-8 fs13"><input type="checkbox" id="stOffline"> حالت آفلاین فعال</label>
                <button type="button" class="btn btn-primary mt16" id="saveStation">ذخیره ایستگاه</button>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3>مرکز ابری و OCPP</h3></div>
            <div class="card-pad">
                <div class="fw7 fs13 mb8">اتصال مرکز</div>
                <div class="field"><label>آدرس API مرکز</label><input id="clUrl" type="text" placeholder="http://cloud-nginx"></div>
                <div class="field"><label>توکن سینک</label><input id="clToken" type="password" placeholder="فقط در صورت تغییر"></div>
                <div class="flex gap-8"><button type="button" class="btn" id="saveCloud">ذخیره مرکز</button><span class="pill" id="clStatus">—</span></div>
                <hr style="border:none;border-top:1px solid var(--border-2);margin:18px 0">
                <div class="fw7 fs13 mb8">OCPP Gateway محلی</div>
                <div class="field"><label>آدرس OCPP API</label><input id="ocUrl" type="text"></div>
                <div class="field"><label>ایمیل OCPP</label><input id="ocEmail" type="text"></div>
                <div class="field"><label>رمز OCPP</label><input id="ocPass" type="password" placeholder="فقط در صورت تغییر"></div>
                <div class="flex gap-8"><button type="button" class="btn" id="saveOcpp">ذخیره OCPP</button><button type="button" class="btn" id="testOcpp">تست اتصال</button></div>
                <div id="ocppMsg" class="muted fs12 mt8"></div>
            </div>
        </div>
    </div>
</div>

<div class="tab-panel" id="tab-devices">
    <div class="card">
        <div class="card-head">
            <h3>شارژرها و کانکتورها</h3>
            <button type="button" class="btn btn-sm" id="syncDevices"><span data-ic="refresh"></span> سینک از مرکز</button>
        </div>
        <div class="card-pad" id="devicesBox"><div class="muted">در حال بارگذاری…</div></div>
    </div>
</div>

<div class="tab-panel" id="tab-anpr">
<div class="banner fs13" style="margin-top:16px">
    هر دوربین باید به <strong>یک کانکتور</strong> نگاشت شود. پس از تشخیص پلاک، وقتی خودرو به همان کانکتور وصل شود (وضعیت Preparing در OCPP)، شارژ خودکار آغاز می‌شود.
</div>
    <div class="grid col-2">
        <div class="card">
            <div class="card-head"><h3>سرویس پلاک‌خوان (PLPR)</h3><span class="pill" id="anprHealth">—</span></div>
            <div class="card-pad">
                <div class="field"><label>آدرس PLPR Lite</label><input id="anUrl" type="text" placeholder="http://host.docker.internal:8502"></div>
                <div class="field"><label>حداقل اطمینان</label><input id="anConf" type="number" min="0" max="1" step="0.01"></div>
                <div class="field"><label>دوربین پیش‌فرض</label><input id="anCam" type="text"></div>
                <label class="flex items-center gap-8 fs13"><input type="checkbox" id="anPush"> ارسال خودکار به مرکز</label>
                <div class="flex gap-8 mt16">
                    <button type="button" class="btn btn-primary" id="saveAnpr">ذخیره</button>
                    <button type="button" class="btn" id="testAnpr">تست سلامت</button>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-head">
                <h3>دوربین‌های مرکز (RTSP)</h3>
                <button type="button" class="btn btn-primary btn-sm" id="saveCameras"><span data-ic="download"></span> ذخیره دوربین‌ها</button>
            </div>
            <div class="card-pad muted fs12" style="padding-bottom:0">پس از انتخاب کانکتور برای هر دوربین، حتماً <strong>ذخیره دوربین‌ها</strong> را بزنید.</div>
            <div class="card-pad" id="camerasBox"></div>
            <div class="card-pad flex gap-8" style="border-top:1px solid var(--border-2)">
                <button type="button" class="btn btn-sm" id="addCamera">+ دوربین</button>
                <button type="button" class="btn btn-primary btn-sm" id="saveCameras2">ذخیره دوربین‌ها</button>
            </div>
        </div>
    </div>
    <div class="card mt16">
        <div class="card-head"><h3>تست تشخیص پلاک</h3></div>
        <div class="card-pad flex gap-12 items-center" style="flex-wrap:wrap">
            <input type="file" id="anprImage" accept="image/*">
            <button type="button" class="btn" id="runAnprTest">تشخیص تصویر</button>
            <span id="anprTestRes" class="fs13"></span>
        </div>
        <div class="card-pad" style="border-top:1px solid var(--border-2);padding-top:0">
            <div class="fw7 fs12 mb8">آخرین تشخیص‌ها</div>
            <div id="anprRecent" class="fs13 muted">—</div>
        </div>
    </div>
</div>

<div class="tab-panel" id="tab-gsm">
    <div class="grid col-2">
        <div class="card">
            <div class="card-head"><h3>مودم GSM</h3><span class="pill" id="gsmDriver">—</span></div>
            <div class="card-pad">
                <label class="flex items-center gap-8 fs13 mb16"><input type="checkbox" id="gsmEnabled"> مودم فعال</label>
                <div class="field"><label>پورت سریال</label><input id="gsmPort" type="text" placeholder="/dev/ttyUSB0"></div>
                <div class="field"><label>Baud Rate</label><input id="gsmBaud" type="number"></div>
                <div class="field"><label>PIN</label><input id="gsmPin" type="password" placeholder="فقط در صورت تغییر"></div>
                <div class="field"><label>مرکز پیامک</label><input id="gsmCenter" type="text"></div>
                <div class="field"><label>Sender ID</label><input id="gsmSender" type="text"></div>
                <button type="button" class="btn btn-primary" id="saveGsm">ذخیره GSM</button>
            </div>
        </div>
        <div class="card">
            <div class="card-head"><h3>تست OTP</h3></div>
            <div class="card-pad">
                <div class="field"><label>موبایل تست</label><input id="gsmTestPhone" type="text" placeholder="09121234567"></div>
                <button type="button" class="btn" id="testGsmOtp">ارسال OTP تست</button>
                <div id="gsmTestRes" class="fs13 mt12 muted"></div>
                <div class="fw7 fs12 mt16 mb8">آخرین پیامک‌ها</div>
                <div id="gsmLogs" class="fs13"></div>
            </div>
        </div>
    </div>
</div>

<div id="setupToast" class="setup-toast" style="display:none"></div>
@endsection

@push('styles')
<style>
.tabs{display:flex;gap:8px;flex-wrap:wrap}
.tab{padding:10px 16px;border:1px solid var(--border-2);border-radius:10px;background:var(--surface);cursor:pointer;font-size:13px;font-weight:600;color:var(--muted)}
.tab.active{background:var(--accent-soft);border-color:var(--accent);color:var(--accent)}
.tab-panel{display:none;margin-top:16px}.tab-panel.active{display:block}
.dev-card{border:1px solid var(--border-2);border-radius:12px;padding:14px;margin-bottom:12px;background:var(--surface-2)}
.dev-card .row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px}
.cam-row{border:1px solid var(--border-2);border-radius:10px;padding:12px;margin-bottom:10px}
.setup-toast{position:fixed;bottom:24px;left:24px;background:var(--sidebar);color:#fff;padding:12px 18px;border-radius:10px;font-size:13px;z-index:99}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var base=@json(url('/panel/api/setup'));
  var state={};
  function csrf(){return document.querySelector('meta[name=csrf-token]').getAttribute('content');}
  function toast(msg){var t=document.getElementById('setupToast');t.textContent=msg;t.style.display='block';setTimeout(function(){t.style.display='none';},2800);}
  function put(url,body){return fetch(url,{method:'PUT',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrf(),'Content-Type':'application/json'},body:JSON.stringify(body||{})}).then(function(r){return r.json();});}
  function post(url,body,isForm){
    var h={'Accept':'application/json','X-CSRF-TOKEN':csrf()};
    if(!isForm)h['Content-Type']='application/json';
    return fetch(url,{method:'POST',headers:h,body:isForm?body:JSON.stringify(body||{})}).then(function(r){return r.json();});
  }
  function load(){
    fetch(base,{headers:{'Accept':'application/json'}}).then(function(r){return r.json();}).then(function(d){
      state=d;renderStation(d);renderDevices(d.devices||[]);renderAnpr(d.anpr||{});renderGsm(d.gsm||{});
    });
  }
  function renderStation(d){
    var s=d.station||{},c=d.cloud||{},o=d.ocpp||{};
    document.getElementById('stName').value=s.name||'';
    document.getElementById('stCode').value=s.edge_code||'';
    document.getElementById('stHost').value=s.local_hostname||'';
    document.getElementById('stWifi').value=s.wifi_ssid||'';
    document.getElementById('stTz').value=s.timezone||'';
    document.getElementById('stOffline').checked=!!s.offline_mode_enabled;
    document.getElementById('clUrl').value=c.api_url||'';
    document.getElementById('clStatus').className='pill '+(c.reachable?'pill-green':'pill-amber');
    document.getElementById('clStatus').textContent=c.reachable?'مرکز متصل':'مرکز آفلاین';
    document.getElementById('ocUrl').value=o.api_url||'';
    document.getElementById('ocEmail').value=o.email||'';
    document.getElementById('ocppMsg').textContent=o.configured?'OCPP پیکربندی شده':'OCPP تنظیم نشده — کنترل شبیه‌سازی می‌شود';
  }
  function renderDevices(devs){
    var box=document.getElementById('devicesBox');
    if(!devs.length){box.innerHTML='<div class="muted">شارژری ثبت نشده — از مرکز سینک کنید یا در ادمین ابری شارژر تعریف کنید.</div>';return;}
    box.innerHTML=devs.map(function(ch){
      var cons=(ch.connectors||[]).map(function(cn){
        return '<div class="fs12 muted" style="padding:6px 0;border-top:1px dashed var(--border-2)">کانکتور '+cn.connector_number+
          ' · '+cn.ocpp_status+' · '+cn.max_power_kw+' kW'+
          ' <button type="button" class="btn btn-sm" data-cn="'+cn.id+'" data-kw="'+cn.max_power_kw+'">ویرایش</button></div>';
      }).join('');
      return '<div class="dev-card"><div class="fw7">'+ch.charge_point_id+'</div><div class="fs12 muted">Cloud: '+(ch.cloud_charger_uuid||'—').slice(0,8)+'… · '+ch.max_power_kw+' kW · '+(ch.is_online?'آنلاین':'آفلاین')+'</div>'+
        '<div class="row"><input class="cp-input" data-id="'+ch.id+'" value="'+ch.charge_point_id+'" placeholder="Charge Point ID"><input class="kw-input" data-id="'+ch.id+'" type="number" value="'+ch.max_power_kw+'" placeholder="kW"></div>'+
        '<button type="button" class="btn btn-sm mt8" data-save-ch="'+ch.id+'">ذخیره شارژر</button>'+cons+'</div>';
    }).join('');
    box.querySelectorAll('[data-save-ch]').forEach(function(b){
      b.addEventListener('click',function(){
        var id=b.getAttribute('data-save-ch');
        var cp=box.querySelector('.cp-input[data-id="'+id+'"]').value;
        var kw=+box.querySelector('.kw-input[data-id="'+id+'"]').value;
        put(base+'/chargers/'+id,{charge_point_id:cp,max_power_kw:kw}).then(function(){toast('شارژر ذخیره شد');load();});
      });
    });
    box.querySelectorAll('[data-cn]').forEach(function(b){
      b.addEventListener('click',function(){
        var kw=prompt('حداکثر توان کانکتور (kW):',b.getAttribute('data-kw'));
        if(kw===null)return;
        put(base+'/connectors/'+b.getAttribute('data-cn'),{max_power_kw:+kw}).then(function(){toast('کانکتور ذخیره شد');load();});
      });
    });
  }
  var cameras=[];
  var connectorOptions=[];
  function renderAnpr(a){
    document.getElementById('anUrl').value=a.plpr_url||'';
    document.getElementById('anConf').value=a.min_confidence||0.92;
    document.getElementById('anCam').value=a.default_camera||'CAM-01';
    document.getElementById('anPush').checked=!!a.auto_push_cloud;
    document.getElementById('anprHealth').className='pill '+(a.healthy?'pill-green':'pill-amber');
    document.getElementById('anprHealth').textContent=a.healthy?'PLPR فعال':'PLPR آفلاین';
    cameras=(a.cameras||[]).slice();
    connectorOptions=a.connector_options||[];
    renderCameras();
    document.getElementById('anprRecent').innerHTML=(a.recent_detections||[]).map(function(d){
      return '<div>'+(d.plate||'—')+' · '+d.camera+' → '+d.connector+' · '+d.auto_start_status+'</div>';
    }).join('')||'—';
  }
  function connectorSelect(i,selected){
    var opts='<option value="">— کانکتور —</option>'+connectorOptions.map(function(o){
      return '<option value="'+o.id+'"'+(String(selected)===String(o.id)?' selected':'')+'>'+o.label+'</option>';
    }).join('');
    return '<div class="field"><label>کانکتور ایستگاه</label><select data-ci="'+i+'" data-f="connector_id" class="input-sm" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border-2)">'+opts+'</select></div>';
  }
  function renderCameras(){
    document.getElementById('camerasBox').innerHTML=cameras.map(function(c,i){
      return '<div class="cam-row"><div class="field"><label>شناسه</label><input data-ci="'+i+'" data-f="id" value="'+(c.id||'')+'"></div>'+
        '<div class="field"><label>نام</label><input data-ci="'+i+'" data-f="name" value="'+(c.name||'')+'"></div>'+
        connectorSelect(i,c.connector_id||'')+
        '<div class="field"><label>RTSP / منبع دوربین مرکز</label><input data-ci="'+i+'" data-f="rtsp_url" value="'+(c.rtsp_url||'')+'" placeholder="rtsp://..."></div>'+
        '<div class="flex gap-8"><label class="fs12"><input type="checkbox" data-ci="'+i+'" data-f="enabled" '+(c.enabled?'checked':'')+'> فعال</label>'+
        '<button type="button" class="btn btn-sm" data-start="'+i+'">اتصال به PLPR</button></div></div>';
    }).join('');
    document.querySelectorAll('[data-ci]').forEach(function(el){
      el.addEventListener('change',function(){
        var f=el.getAttribute('data-f'),v=el.type==='checkbox'?el.checked:(f==='connector_id'?+el.value||null:el.value);
        cameras[+el.getAttribute('data-ci')][f]=v;
      });
      el.addEventListener('input',function(){
        var f=el.getAttribute('data-f');
        cameras[+el.getAttribute('data-ci')][f]=f==='connector_id'?+el.value||null:el.value;
      });
    });
    document.querySelectorAll('[data-start]').forEach(function(b){
      b.addEventListener('click',function(){
        var cam=cameras[+b.getAttribute('data-start')];
        post(base+'/anpr/camera/start',{source:cam.rtsp_url||cam.id}).then(function(r){toast(r.message||'انجام شد');});
      });
    });
  }
  function renderGsm(g){
    var m=g.modem||{};
    document.getElementById('gsmDriver').textContent='Driver: '+(g.driver||'log');
    document.getElementById('gsmEnabled').checked=!!m.is_enabled;
    document.getElementById('gsmPort').value=m.port||'';
    document.getElementById('gsmBaud').value=m.baud_rate||115200;
    document.getElementById('gsmCenter').value=m.sms_center||'';
    document.getElementById('gsmSender').value=m.sender_id||'';
    document.getElementById('gsmLogs').innerHTML=(g.recent_messages||[]).map(function(l){
      return '<div style="padding:4px 0;border-bottom:1px solid var(--border-2)">'+l.phone+' · '+l.status+' · '+l.body+'</div>';
    }).join('')||'—';
  }
  document.querySelectorAll('.tab').forEach(function(t){
    t.addEventListener('click',function(){
      document.querySelectorAll('.tab,.tab-panel').forEach(function(x){x.classList.remove('active');});
      t.classList.add('active');
      document.getElementById('tab-'+t.getAttribute('data-tab')).classList.add('active');
    });
  });
  document.getElementById('saveStation').addEventListener('click',function(){
    put(base+'/station',{name:stName.value,local_hostname:stHost.value,wifi_ssid:stWifi.value,timezone:stTz.value,offline_mode_enabled:stOffline.checked})
      .then(function(){toast('ایستگاه ذخیره شد');load();});
  });
  document.getElementById('saveCloud').addEventListener('click',function(){
    var b={api_url:clUrl.value};if(clToken.value)b.sync_token=clToken.value;
    put(base+'/cloud',b).then(function(r){toast(r.message||'ذخیره شد');load();});
  });
  document.getElementById('saveOcpp').addEventListener('click',function(){
    var b={api_url:ocUrl.value,email:ocEmail.value};if(ocPass.value)b.password=ocPass.value;
    put(base+'/ocpp',b).then(function(){toast('OCPP ذخیره شد');load();});
  });
  document.getElementById('testOcpp').addEventListener('click',function(){
    post(base+'/ocpp/test',{}).then(function(r){document.getElementById('ocppMsg').textContent=r.message||r.error||'';});
  });
  document.getElementById('syncDevices').addEventListener('click',function(){
    post(base+'/devices/sync',{}).then(function(r){toast('سینک: '+r.mode);renderDevices(r.devices||[]);});
  });
  function saveAnprSettings(){
    put(base+'/anpr',{plpr_url:anUrl.value,min_confidence:+anConf.value,default_camera:anCam.value,auto_push_cloud:anPush.checked,cameras:cameras})
      .then(function(){toast('تنظیمات پلاک‌خوان و دوربین‌ها ذخیره شد');load();});
  }
  document.getElementById('saveAnpr').addEventListener('click',saveAnprSettings);
  document.getElementById('saveCameras').addEventListener('click',saveAnprSettings);
  document.getElementById('saveCameras2').addEventListener('click',saveAnprSettings);
  document.getElementById('testAnpr').addEventListener('click',function(){
    post(base+'/anpr/health',{}).then(function(r){toast(r.message||r.error);load();});
  });
  document.getElementById('addCamera').addEventListener('click',function(){
    cameras.push({id:'CAM-'+(cameras.length+1),name:'دوربین جدید',rtsp_url:'',connector_id:null,enabled:true,role:'entry'});
    renderCameras();
  });
  document.getElementById('runAnprTest').addEventListener('click',function(){
    var f=anprImage.files[0];if(!f)return;
    var fd=new FormData();fd.append('image',f);
    post(base+'/anpr/test',fd,true).then(function(r){
      document.getElementById('anprTestRes').textContent=r.ok?('پلاک: '+r.plate+' · '+r.confidence+' · '+r.auto_start_status+(r.auto_start_message?' — '+r.auto_start_message:'')):(r.error+(r.hint?' — '+r.hint:''));
      load();
    });
  });
  document.getElementById('saveGsm').addEventListener('click',function(){
    var b={port:gsmPort.value,baud_rate:+gsmBaud.value,sms_center:gsmCenter.value,sender_id:gsmSender.value,is_enabled:gsmEnabled.checked};
    if(gsmPin.value)b.pin_code=gsmPin.value;
    put(base+'/gsm',b).then(function(){toast('GSM ذخیره شد');load();});
  });
  document.getElementById('testGsmOtp').addEventListener('click',function(){
    post(base+'/gsm/test-otp',{phone:gsmTestPhone.value}).then(function(r){
      var t=r.result||{};
      document.getElementById('gsmTestRes').textContent=t.debug_code?('کد تست: '+t.debug_code):'ارسال شد';
      load();
    });
  });
  load();
})();
</script>
@endpush
