@extends('app.layouts.mobile', ['noTab' => true, 'darkStatus' => true, 'phoneClass' => 'scan-phone'])

@section('title', 'SafirCharge — اسکن QR')

@section('phone')
@php $firstTarget = $scanTargets[0] ?? null; @endphp
<div class="appbar" style="padding-top:2px">
    <a href="{{ route('app.home') }}" class="ab-btn scan-btn"><span data-ic="back"></span></a>
    <div style="flex:1;text-align:center;color:#fff"><div class="fw8" style="font-size:17px">اسکن کد QR</div></div>
    <a href="{{ route('app.map') }}" class="ab-btn scan-btn" title="نقشه"><span data-ic="bolt"></span></a>
</div>

<div class="scan-area">
    <div class="viewfinder">
        <video id="scanVideo" playsinline muted></video>
        <span class="corner tl"></span><span class="corner tr"></span>
        <span class="corner bl"></span><span class="corner br"></span>
        <div class="scanline"></div>
        <div class="vf-qr" data-ic="qr"></div>
    </div>
    <p class="scan-hint" id="scanHint">کد QR روی شارژر یا کانکتور را داخل کادر قرار دهید</p>
</div>

<div class="scan-sheet">
    @if($firstTarget)
    <a href="{{ $firstTarget['url'] }}" class="btn" id="scanPrimary" style="text-decoration:none"><span data-ic="bolt"></span> رفتن به {{ $firstTarget['label'] }}</a>
    @else
    <button class="btn" id="scanPrimary" type="button" disabled style="opacity:.55"><span data-ic="qr"></span> کانکتوری برای اسکن در دسترس نیست</button>
    @endif
    @foreach(array_slice($scanTargets, 1) as $target)
    <a href="{{ $target['url'] }}" class="btn btn-ghost mt8" style="width:100%;text-decoration:none"><span data-ic="bolt"></span> {{ $target['label'] }}</a>
    @endforeach
    <div class="row mt12" style="gap:11px">
        <a href="{{ route('app.map') }}" class="btn btn-ghost" style="flex:1;text-decoration:none"><span data-ic="qr"></span> ورود دستی کد</a>
        <button class="btn btn-ghost" style="flex:none;width:56px" type="button" aria-label="چراغ قوه">⚡</button>
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
<script src="/assets/vendor/jsQR.js"></script>
<script>
(function(){
  var video=document.getElementById('scanVideo');
  var hint=document.getElementById('scanHint');
  var vf=video.parentNode;
  var origin=location.origin;
  var done=false,detector=null,canvas=document.createElement('canvas'),ctx=canvas.getContext('2d',{willReadFrequently:true});

  function setHint(t){hint.textContent=t;}
  function handle(text){
    if(done||!text)return;
    var url;
    try{url=new URL(text,origin);}catch(e){url=null;}
    if(url&&url.origin===origin&&(/^\/q\/[A-Za-z0-9]+$/.test(url.pathname)||url.pathname==='/app/charger')){
      done=true;
      setHint('کد شناسایی شد، در حال باز کردن شارژر…');
      if(navigator.vibrate)navigator.vibrate(60);
      stop();
      location.href=url.href;
    }else{
      setHint('این کد QR متعلق به شارژرهای سامانه نیست.');
    }
  }
  function stop(){var s=video.srcObject;if(s)s.getTracks().forEach(function(t){t.stop();});}
  function tick(){
    if(done)return;
    if(video.readyState>=2){
      if(detector){
        detector.detect(video).then(function(codes){if(codes.length)handle(codes[0].rawValue);}).catch(function(){});
      }else if(window.jsQR){
        var w=video.videoWidth,h=video.videoHeight,scale=Math.min(1,640/Math.max(w,h));
        canvas.width=Math.round(w*scale);canvas.height=Math.round(h*scale);
        ctx.drawImage(video,0,0,canvas.width,canvas.height);
        var img=ctx.getImageData(0,0,canvas.width,canvas.height);
        var code=jsQR(img.data,img.width,img.height,{inversionAttempts:'dontInvert'});
        if(code)handle(code.data);
      }
    }
    setTimeout(tick,250);
  }
  if(!navigator.mediaDevices||!navigator.mediaDevices.getUserMedia){
    setHint('مرورگر شما دسترسی به دوربین را پشتیبانی نمی‌کند؛ با دوربین گوشی کد را اسکن کنید.');
    return;
  }
  navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false}).then(function(stream){
    video.srcObject=stream;
    return video.play();
  }).then(function(){
    vf.classList.add('live');
    if('BarcodeDetector' in window){
      try{detector=new BarcodeDetector({formats:['qr_code']});}catch(e){detector=null;}
    }
    tick();
  }).catch(function(){
    setHint('دسترسی به دوربین داده نشد. اجازهٔ دوربین را فعال کنید یا با دوربین گوشی کد را اسکن کنید.');
  });
  window.addEventListener('pagehide',stop);
})();
</script>
@endpush

