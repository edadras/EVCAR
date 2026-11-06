@extends('app.layouts.mobile', ['noTab' => true, 'darkStatus' => true, 'phoneClass' => 'scan-phone'])

@section('title', 'QUAD EV — اسکن QR')

@section('phone')
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.home') }}" class="ab-btn scan-btn"><span data-ic="back"></span></a>
    <div style="flex:1;text-align:center;color:#fff"><div class="fw8" style="font-size:17px">اسکن کد QR</div></div>
    <a href="{{ route('app.map') }}" class="ab-btn scan-btn" title="نقشه"><span data-ic="bolt"></span></a>
</div>

<div class="scan-area">
    <div class="viewfinder">
        <video id="scanVideo" playsinline muted autoplay></video>
        <button type="button" class="vf-start" id="scanStart" hidden>شروع دوربین</button>
        <span class="corner tl"></span><span class="corner tr"></span>
        <span class="corner bl"></span><span class="corner br"></span>
        <div class="scanline"></div>
        <div class="vf-qr" data-ic="qr"></div>
    </div>
    <p class="scan-hint" id="scanHint">کد QR روی شارژر یا کانکتور را داخل کادر قرار دهید</p>
</div>

<div class="scan-sheet">
    <label class="btn" id="scanPhotoBtn" style="cursor:pointer"><span data-ic="qr"></span> اسکن با عکس دوربین
        <input type="file" id="scanPhoto" accept="image/*" capture="environment" hidden>
    </label>
    <div class="row mt12" style="gap:11px">
        <a href="{{ route('app.map') }}" class="btn btn-ghost" style="flex:1;text-decoration:none"><span data-ic="bolt"></span> انتخاب از نقشه</a>
    </div>
    <div class="offline-note mt16">
        <span data-ic="shield"></span>
        <span>با اتصال به WiFi ایستگاه، اپ به‌صورت خودکار به <b>لبه محلی</b> وصل می‌شود. اگر چند منبع در دسترس باشد، از شما پرسیده می‌شود.</span>
    </div>
</div>
@endsection

@push('styles')
<style>
.scan-phone{background:#0a141e}
.scan-btn{background:rgba(255,255,255,.14)!important;border-color:rgba(255,255,255,.18)!important;color:#fff!important}
.scan-btn svg{width:20px;height:20px}
.scan-area{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:22px;padding:0 30px}
.viewfinder{position:relative;width:250px;height:250px;border-radius:28px;background:linear-gradient(135deg,#11212f,#0d1a26);box-shadow:0 0 0 2000px rgba(8,16,24,.55)}
.corner{position:absolute;width:36px;height:36px;border:3px solid #fff}
.corner.tl{top:14px;left:14px;border-right:none;border-bottom:none;border-radius:14px 0 0 0}
.corner.tr{top:14px;right:14px;border-left:none;border-bottom:none;border-radius:0 14px 0 0}
.corner.bl{bottom:14px;left:14px;border-right:none;border-top:none;border-radius:0 0 0 14px}
.corner.br{bottom:14px;right:14px;border-left:none;border-top:none;border-radius:0 0 14px 0}
.scanline{position:absolute;left:22px;right:22px;height:3px;border-radius:3px;background:linear-gradient(90deg,transparent,var(--accent),transparent);box-shadow:0 0 14px 2px color-mix(in srgb,var(--accent) 70%,transparent);top:26px;animation:scan 2.4s ease-in-out infinite}
@keyframes scan{0%,100%{top:26px}50%{top:220px}}
#scanVideo{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:28px;opacity:0;transition:opacity .3s}
.viewfinder.live #scanVideo{opacity:1}
.vf-start{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:3;border:none;border-radius:14px;padding:12px 20px;background:var(--accent);color:#fff;font-family:inherit;font-size:14px;font-weight:800}
.viewfinder.live .vf-qr{display:none}
.vf-qr{position:absolute;inset:50px;opacity:.18;display:grid;place-items:center}
.vf-qr svg{width:100%;height:100%}
.scan-hint{color:#9fb4c8;font-size:13.5px;text-align:center;max-width:240px;line-height:1.7}
.scan-sheet{background:var(--surface);border-radius:28px 28px 0 0;padding:22px 22px calc(30px + env(safe-area-inset-bottom,0));flex:none;box-shadow:0 -10px 40px rgba(0,0,0,.3)}
.scan-sheet .btn svg{width:18px;height:18px}
.offline-note{display:flex;align-items:flex-start;gap:10px;background:var(--surface-2);border:1px solid var(--border-2);border-radius:14px;padding:13px;font-size:12.5px;color:var(--text-2);line-height:1.6}
.offline-note svg{width:20px;height:20px;color:var(--green);flex:none;margin-top:1px}
</style>
@endpush

@push('scripts')
<script src="@v('/assets/lib/jsQR.js')"></script>
<script>
(function(){
  var video=document.getElementById('scanVideo');
  var hint=document.getElementById('scanHint');
  var startBtn=document.getElementById('scanStart');
  var photo=document.getElementById('scanPhoto');
  var vf=video.parentNode;
  var done=false,running=false,detector=null,canvas=document.createElement('canvas'),ctx=canvas.getContext('2d',{willReadFrequently:true});
  video.muted=true;
  video.setAttribute('playsinline','');

  function setHint(t){hint.textContent=t;}

  /** لینک QR سامانه (/q/TOKEN یا /app/charger?...) را روی همین دامنه باز می‌کند، مستقل از دامنهٔ چاپ‌شده. */
  function handle(text){
    if(done||!text)return false;
    text=String(text).trim();
    var url=null;
    try{url=new URL(text,location.origin);}catch(e){}
    var target=null;
    if(url&&/^\/q\/[A-Za-z0-9]+\/?$/.test(url.pathname))target=url.pathname.replace(/\/$/,'');
    else if(url&&url.pathname==='/app/charger'&&url.searchParams.get('qr'))target=url.pathname+url.search;
    else if(/^[A-Z0-9]{6,40}$/.test(text))target='/q/'+text;
    if(!target){setHint('این کد QR متعلق به شارژرهای سامانه نیست.');return false;}
    done=true;
    setHint('کد شناسایی شد، در حال باز کردن شارژر…');
    if(navigator.vibrate)navigator.vibrate(60);
    stop();
    location.href=target;
    return true;
  }

  function decode(source,w,h,maxSide){
    var scale=Math.min(1,maxSide/Math.max(w,h));
    canvas.width=Math.max(1,Math.round(w*scale));canvas.height=Math.max(1,Math.round(h*scale));
    ctx.drawImage(source,0,0,canvas.width,canvas.height);
    var img=ctx.getImageData(0,0,canvas.width,canvas.height);
    var code=jsQR(img.data,img.width,img.height,{inversionAttempts:'attemptBoth'});
    return code?code.data:null;
  }

  function stop(){running=false;var s=video.srcObject;if(s)s.getTracks().forEach(function(t){t.stop();});}

  function tick(){
    if(done||!running)return;
    if(video.readyState>=2&&video.videoWidth){
      if(detector){
        detector.detect(video).then(function(codes){if(codes.length)handle(codes[0].rawValue);}).catch(function(){detector=null;});
      }else if(window.jsQR){
        var txt=decode(video,video.videoWidth,video.videoHeight,800);
        if(txt)handle(txt);
      }
    }
    setTimeout(tick,200);
  }

  function startCamera(){
    if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){
      setHint('این مرورگر دوربین زنده را پشتیبانی نمی‌کند؛ دکمهٔ «اسکن با عکس دوربین» را بزنید.');
      return;
    }
    startBtn.hidden=true;
    setHint('در حال باز کردن دوربین…');
    navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'},width:{ideal:1280},height:{ideal:720}},audio:false}).then(function(stream){
      video.srcObject=stream;
      return video.play();
    }).then(function(){
      vf.classList.add('live');
      running=true;
      setHint('کد QR روی شارژر یا کانکتور را داخل کادر قرار دهید');
      if('BarcodeDetector' in window){
        try{detector=new BarcodeDetector({formats:['qr_code']});}catch(e){detector=null;}
      }
      tick();
    }).catch(function(err){
      var name=err&&err.name;
      if(name==='NotAllowedError'){
        setHint('اجازهٔ دوربین داده نشد. در آیفون: Settings ‹ Safari ‹ Camera را روی Allow بگذارید، یا «اسکن با عکس دوربین» را بزنید.');
        startBtn.hidden=false;
      }else if(name==='NotFoundError'||name==='OverconstrainedError'){
        setHint('دوربینی پیدا نشد؛ «اسکن با عکس دوربین» را بزنید.');
      }else{
        setHint('برای فعال شدن دوربین روی «شروع دوربین» بزنید.');
        startBtn.hidden=false;
      }
    });
  }

  startBtn.addEventListener('click',startCamera);

  photo.addEventListener('change',function(){
    var file=photo.files&&photo.files[0];
    if(!file)return;
    setHint('در حال خواندن کد از عکس…');
    var img=new Image();
    var url=URL.createObjectURL(file);
    img.onload=function(){
      var txt=null;
      [1600,1000,600].some(function(side){txt=decode(img,img.naturalWidth,img.naturalHeight,side);return !!txt;});
      URL.revokeObjectURL(url);
      photo.value='';
      if(!txt){setHint('کدی در عکس پیدا نشد. از نزدیک‌تر و بدون لرزش عکس بگیرید.');return;}
      handle(txt);
    };
    img.onerror=function(){setHint('عکس قابل خواندن نیست.');URL.revokeObjectURL(url);};
    img.src=url;
  });

  window.addEventListener('pagehide',stop);
  startCamera();
})();
</script>
@endpush

