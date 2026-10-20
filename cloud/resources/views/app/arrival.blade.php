@extends('app.layouts.mobile', ['noTab' => true])

@section('title', 'SafirCharge — تأیید ورود')

@section('phone')
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.home') }}" class="ab-btn"><span data-ic="back"></span></a>
    <div style="flex:1"><div class="ab-title">تأیید اتصال</div></div>
</div>

<div class="screen" style="padding-bottom:110px" id="arrivalRoot">
    <div class="card-lite" id="arrivalLoading">در حال بررسی وضعیت ورود…</div>
    <div id="arrivalEmpty" hidden>
        <div class="card-lite" style="text-align:center;padding:28px 20px">
            <div style="font-size:15px;line-height:1.8">ورود فعالی برای تأیید وجود ندارد.</div>
            <a href="{{ route('app.home') }}" class="btn btn-soft mt16" style="display:inline-flex">بازگشت به خانه</a>
        </div>
    </div>
    <div id="arrivalPanel" hidden>
        <div class="arr-hero">
            <span class="arr-pulse"></span>
            <div class="arr-plate tnum" id="arrPlate">—</div>
            <div class="fs13 muted mt8">پلاک شما در ایستگاه شناسایی شد</div>
        </div>

        <div class="card card-p mt16">
            <div class="lrow" style="padding-top:0">
                <div class="l-ic ic-blue" data-ic="pin"></div>
                <div class="l-main"><div class="l-title">ایستگاه</div><div class="l-sub" id="arrStation">—</div></div>
            </div>
            <div class="lrow">
                <div class="l-ic ic-green" data-ic="plug"></div>
                <div class="l-main"><div class="l-title">کانکتور</div><div class="l-sub" id="arrConnector">—</div></div>
                <span class="pill pill-green" id="arrConnStatus">—</span>
            </div>
            <div class="lrow" style="padding-bottom:0">
                <div class="l-ic ic-violet" data-ic="scan"></div>
                <div class="l-main"><div class="l-title">دوربین</div><div class="l-sub" id="arrCamera">—</div></div>
            </div>
        </div>

        <div class="card-lite mt12" id="arrMessage" style="line-height:1.7;font-size:13.5px"></div>

        <div class="countdown mt12" id="arrCountdown" hidden>
            <span class="fs12 muted">زمان باقی‌مانده برای تأیید</span>
            <span class="tnum fw8" id="arrTimer">—</span>
        </div>

        <button type="button" class="btn btn-soft mt16" id="arrCancel" style="width:100%"><span data-ic="qr"></span> لغو و انتخاب کانکتور دیگر</button>
        <p class="fs12 muted mt8" style="line-height:1.7;text-align:center">اگر این کانکتور مشکل دارد، ورود را لغو کنید و QR کانکتور دیگر را اسکن کنید.</p>
    </div>
</div>

<div class="startbar" id="arrivalBar" hidden>
    <div style="flex:1">
        <div class="muted fs12">مرحله بعد</div>
        <div class="fw8 fs13" id="arrBarHint">کابل را وصل کنید</div>
    </div>
    <button type="button" class="btn btn-orange" id="arrConfirm" style="width:auto;padding:0 22px"><span data-ic="bolt"></span> شروع شارژ</button>
</div>
@endsection

@push('styles')
<style>
.arr-hero{text-align:center;padding:28px 16px 8px}
.arr-pulse{display:inline-block;width:14px;height:14px;border-radius:50%;background:var(--green);box-shadow:0 0 0 0 color-mix(in srgb,var(--green) 45%,transparent);animation:arrPulse 1.8s ease-out infinite;margin-bottom:12px}
@keyframes arrPulse{0%{box-shadow:0 0 0 0 color-mix(in srgb,var(--green) 50%,transparent)}70%{box-shadow:0 0 0 14px transparent}100%{box-shadow:0 0 0 0 transparent}}
.arr-plate{font-size:26px;font-weight:800;letter-spacing:.04em}
.countdown{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px dashed var(--border);border-radius:14px;background:var(--surface-2)}
.startbar{position:absolute;bottom:0;left:0;right:0;background:color-mix(in srgb,var(--surface) 94%,transparent);backdrop-filter:blur(14px);border-top:1px solid var(--border);box-shadow:var(--shadow-up);display:flex;align-items:center;gap:12px;padding:14px 20px calc(18px + env(safe-area-inset-bottom,0));z-index:20}
.startbar .btn svg{width:18px;height:18px}
</style>
@endpush

@push('scripts')
<script>
(function(){
  var root=document.getElementById('arrivalRoot');
  var loading=document.getElementById('arrivalLoading');
  var empty=document.getElementById('arrivalEmpty');
  var panel=document.getElementById('arrivalPanel');
  var bar=document.getElementById('arrivalBar');
  var confirmBtn=document.getElementById('arrConfirm');
  var cancelBtn=document.getElementById('arrCancel');
  var chargingUrl=@json(route('app.charging'));
  var scanUrl=@json(route('app.scan'));
  var pollMs=5000;
  var timerId=null;

  function fa(n){return String(n).replace(/\d/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d];});}

  function fmtTimer(sec){
    var total=Math.max(0,Math.floor(Number(sec)||0));
    var m=Math.floor(total/60);
    var s=total%60;
    return fa(m)+':'+(s<10?'۰':'')+fa(s);
  }

  function render(data){
    if(!data||!data.pending){
      loading.hidden=true;
      panel.hidden=true;
      bar.hidden=true;
      empty.hidden=false;
      return;
    }
    loading.hidden=true;
    empty.hidden=true;
    panel.hidden=false;
    bar.hidden=false;

    document.getElementById('arrPlate').textContent=data.plate||'—';
    document.getElementById('arrStation').textContent=(data.station&&data.station.name)||'—';
    document.getElementById('arrConnector').textContent=(data.connector&&data.connector.label)||'—';
    document.getElementById('arrCamera').textContent=data.camera||'—';
    document.getElementById('arrMessage').textContent=data.auto_start_message||'کابل را به کانکتور وصل کنید و تأیید کنید.';
    var st=data.connector&&data.connector.ocpp_status;
    var stEl=document.getElementById('arrConnStatus');
    if(st){stEl.textContent=st;stEl.className='pill '+(st==='Available'||st==='Preparing'?'pill-green':'pill-orange');}
    else{stEl.textContent='منتظر';stEl.className='pill pill-orange';}

    var cd=document.getElementById('arrCountdown');
    if(data.expires_in_sec>0){
      cd.hidden=false;
      document.getElementById('arrTimer').textContent=fmtTimer(data.expires_in_sec);
    }else{cd.hidden=true;}

    confirmBtn.disabled=!data.can_confirm;
    if(cancelBtn)cancelBtn.disabled=data.can_cancel===false;
    document.getElementById('arrBarHint').textContent=data.can_confirm?'کابل وصل شد؟ شارژ را شروع کنید':'منتظر آماده‌شدن کانکتور';

    if(data.auto_start_status==='started'&&data.charging_session_uuid){
      window.location.href=chargingUrl+'?edge=1';
    }
  }

  var edgePath=(window.SC_API&&window.SC_API.arrivalEdge)||'/api/v1/app/arrival';
  var confirmPath=(window.SC_API&&window.SC_API.arrivalConfirmEdge)||'/api/v1/app/arrival/confirm';
  var cancelPath=(window.SC_API&&window.SC_API.arrivalCancelEdge)||'/api/v1/app/arrival/cancel';
  var cloudPath=(window.SC_API&&window.SC_API.arrivalCloud)||'';
  var cloudConfirmPath=(window.SC_API&&window.SC_API.arrivalConfirmCloud)||'';
  var cloudCancelPath=(window.SC_API&&window.SC_API.arrivalCancelCloud)||'';

  function fetchStatus(){
    return window.scEnsureBackend().then(function(){
      if(window.scFetchAnyEdge){
        return window.scFetchAnyEdge(edgePath).then(function(hit){
          if(hit&&hit.data&&hit.data.pending)return hit.data;
          if(cloudPath&&window.scFetch){
            return window.scFetch(cloudPath,'',{method:'GET'}).then(function(r){return r.ok?r.json():null;});
          }
          return hit&&hit.data?hit.data:null;
        });
      }
      if(window.scFetch){
        return window.scFetch(cloudPath,edgePath,{method:'GET'}).then(function(r){return r.ok?r.json():null;});
      }
      return null;
    });
  }

  function poll(){
    fetchStatus().then(function(data){
      if(!data||!data.pending){
        if(data&&data.source==='cloud'&&!data.pending){
          loading.textContent='ورود فعالی یافت نشد — داده از مرکز ابری.';
        }
      }
      render(data);
    }).catch(function(){
      loading.textContent='خطا در ارتباط. دوباره تلاش می‌شود…';
    });
  }

  function postCancel(){
    if(window.scPostAnyEdge){
      return window.scPostAnyEdge(cancelPath,{}).then(function(res){
        if(res)return res.data||{};
        if(cloudCancelPath&&window.scPost){
          return window.scPost(cloudCancelPath,cancelPath,{}).then(function(r){return r.json();});
        }
        throw new Error('ایستگاه محلی در دسترس نیست.');
      });
    }
    if(cloudCancelPath&&window.scPost){
      return window.scPost(cloudCancelPath,cancelPath,{}).then(function(r){return r.json();});
    }
    return Promise.reject(new Error('لغو ورود در دسترس نیست.'));
  }

  if(cancelBtn){
    cancelBtn.addEventListener('click',function(){
      if(!confirm('ورود به این کانکتور لغو شود؟\nمی‌توانید کانکتور دیگری را با اسکن QR انتخاب کنید.'))return;
      cancelBtn.disabled=true;
      confirmBtn.disabled=true;
      postCancel().then(function(d){
        if(d&&d.ok!==false){
          window.location.href=scanUrl;
          return;
        }
        throw new Error((d&&d.message)||'خطا در لغو');
      }).catch(function(err){
        alert(err.message||'خطا');
        cancelBtn.disabled=false;
        confirmBtn.disabled=false;
        poll();
      });
    });
  }

  confirmBtn.addEventListener('click',function(){
    confirmBtn.disabled=true;
    var done=function(res){
      var d=res.d||res.data||{};
      if(d.session_uuid){
        if(res.backend&&window.scOpenBackendPicker){
          try{localStorage.setItem('sc_backend_choice_v1',res.backend.id);}catch(e){}
        }
        window.location.href=chargingUrl+'?edge=1';
        return;
      }
      if(d.waiting_connector){
        document.getElementById('arrMessage').textContent=d.message||'کابل را وصل کنید.';
        confirmBtn.disabled=false;
        poll();
        return;
      }
      throw new Error(d.message||'خطا در تأیید');
    };
    function postConfirm(){
      if(window.scPostAnyEdge){
        return window.scPostAnyEdge(confirmPath,{}).then(function(res){
          if(res)return done({ok:res.ok,d:res.data,backend:res.backend});
          if(cloudConfirmPath&&window.scPost){
            return window.scPost(cloudConfirmPath,confirmPath,{}).then(function(r){
              return r.json().then(function(d){return done({ok:r.ok,d:d});});
            });
          }
          throw new Error('ایستگاه محلی در دسترس نیست — WiFi ایستگاه را بررسی کنید.');
        });
      }
      if(cloudConfirmPath&&window.scPost){
        return window.scPost(cloudConfirmPath,confirmPath,{}).then(function(r){
          return r.json().then(function(d){return done({ok:r.ok,d:d});});
        });
      }
      return Promise.reject(new Error('تأیید ورود در دسترس نیست.'));
    }
    var chain=postConfirm();
    chain.catch(function(err){
      alert(err.message||'خطا');
      confirmBtn.disabled=false;
    });
  });

  poll();
  timerId=setInterval(poll,pollMs);
  window.addEventListener('pagehide',function(){if(timerId)clearInterval(timerId);});
})();
</script>
@endpush
