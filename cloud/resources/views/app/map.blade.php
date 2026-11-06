@extends('app.layouts.mobile', ['tab' => 'map'])

@section('title', 'QUAD EV — نقشه شارژرها')

@section('phone')
@php use App\Support\Persian; @endphp
<div class="mapfull" id="map"></div>

<div class="map-search">
    <a href="{{ route('app.scan') }}" class="ms-qr" data-ic="qr"></a>
    <input id="mapSearch" placeholder="جستجوی ایستگاه یا مقصد…">
    <span data-ic="filter" class="ms-f"></span>
</div>

<div class="map-chips" id="chips">
    <a class="mchip mchip-link" href="{{ route('app.navigator') }}">مسیریاب برقی</a>
    <button class="mchip active" type="button" data-filter="all">همه</button>
    <button class="mchip" type="button" data-filter="fast">شارژ سریع</button>
    <button class="mchip" type="button" data-filter="free">آزاد اکنون</button>
    <button class="mchip" type="button" data-filter="public">عمومی</button>
    <button class="mchip" type="button" data-filter="quad">ایستگاه‌های QUAD EV</button>
    @if(count($externalStations))
    <button class="mchip" type="button" data-filter="others">سایر اپراتورها</button>
    @endif
</div>

<button class="loc-btn" type="button" id="locBtn" data-ic="nav"></button>

<div class="sheet">
    <div class="sheet-grip"></div>
    <div id="extDetail" class="ext-detail" style="display:none"></div>
    <div class="row between" style="margin-bottom:12px">
        <div class="fw8" style="font-size:16px" id="mapCount">{{ Persian::digits($mapStats['total']) }} ایستگاه در این محدوده</div>
        <span class="pill pill-green"><span class="pdot"></span><span id="freeCount">{{ Persian::digits($mapStats['free']) }}</span> آزاد</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:11px" id="list"></div>
    <div id="extWrap" style="display:none">
        <div class="fw8 mt16" style="font-size:14px;margin:18px 0 4px">ایستگاه‌های سایر اپراتورها</div>
        <div class="fs12 muted" style="margin-bottom:10px">فقط برای اطلاع — رزرو و پرداخت این ایستگاه‌ها از QUAD EV انجام نمی‌شود.</div>
        <div style="display:flex;flex-direction:column;gap:11px" id="extList"></div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
.mapfull{position:absolute;inset:0;background:var(--surface-3);z-index:1}
.leaflet-container{font-family:inherit}
.map-search{position:absolute;top:54px;left:16px;right:16px;height:50px;background:var(--surface);border-radius:16px;box-shadow:var(--shadow-md);display:flex;align-items:center;gap:10px;padding:0 14px;z-index:10}
.map-search input{flex:1;border:none;background:none;font-family:inherit;font-size:14px;color:var(--text)}
.map-search input:focus{outline:none}
.map-search .ms-qr,.map-search .ms-f{display:inline-grid;width:21px;height:21px;color:var(--muted);text-decoration:none}
.map-search .ms-qr{color:var(--accent)}
.map-search svg{width:21px;height:21px}
.map-chips{position:absolute;top:116px;left:0;right:0;display:flex;gap:8px;padding:0 16px;overflow-x:auto;z-index:10}
.map-chips::-webkit-scrollbar{height:0}
.mchip{flex:none;background:var(--surface);border:1px solid var(--border);border-radius:30px;padding:8px 15px;font-size:12.5px;font-weight:700;color:var(--text-2);box-shadow:var(--shadow-sm);cursor:pointer;font-family:inherit}
.mchip.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.mchip-link{text-decoration:none;display:inline-block;background:linear-gradient(135deg,var(--accent),var(--orange));color:#fff;border-color:transparent}
.loc-btn{position:absolute;bottom:330px;inset-inline-end:18px;width:46px;height:46px;border-radius:14px;background:var(--surface);border:1px solid var(--border);box-shadow:var(--shadow-md);display:grid;place-items:center;color:var(--accent);z-index:10;cursor:pointer}
.loc-btn svg{width:21px;height:21px}
.sheet{position:absolute;bottom:0;left:0;right:0;background:var(--surface);border-radius:26px 26px 0 0;box-shadow:var(--shadow-up);padding:10px 20px calc(96px + env(safe-area-inset-bottom,0));max-height:46%;overflow-y:auto;z-index:15}
.sheet::-webkit-scrollbar{width:0}
.sheet-grip{width:42px;height:5px;border-radius:10px;background:var(--border);margin:4px auto 14px}
.ms-station{display:flex;gap:12px;align-items:center;padding:12px;border-radius:16px;background:var(--surface-2);border:1px solid var(--border-2);text-decoration:none;color:inherit}
.ms-station .si{width:50px;height:50px;border-radius:13px;flex:none;display:grid;place-items:center}
.ms-station .si:not([class*="ic-"]){background:var(--surface-3);color:var(--muted)}
.ms-station .si svg{width:23px;height:23px}
.sc-pin{width:50px;height:50px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:grid;place-items:center;padding:4px;box-shadow:0 0 0 3px #facc15,0 6px 16px rgba(0,0,0,.35)}
.sc-pin img{transform:rotate(45deg);width:100%!important;height:100%!important;border-radius:50%;background:#fff;object-fit:contain;padding:2px}
.sc-pin.green{background:var(--green)}.sc-pin.amber{background:var(--amber)}.sc-pin.red{background:var(--red)}.sc-pin.gray{background:#64748b}
.op-pin{width:22px;height:22px;border-radius:50%;background:#fff;border:1.5px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.25);overflow:hidden;display:grid;place-items:center;opacity:.9}
.op-pin img{width:100%!important;height:100%!important;object-fit:contain}
.ext-detail{position:relative;background:var(--surface-2);border:1px solid var(--border-2);border-radius:16px;padding:12px;margin-bottom:16px}
.ext-detail .ex-close{position:absolute;top:8px;inset-inline-end:8px;width:28px;height:28px;border-radius:50%;border:none;background:var(--surface-3);color:var(--text-2);font-size:16px;cursor:pointer;font-family:inherit}
.ext-pop{direction:rtl;text-align:right;font-size:12.5px;line-height:1.7;color:var(--text)}
.ext-pop .eh{display:flex;gap:9px;align-items:center;margin-bottom:6px}
.ext-pop .eh img{width:36px;height:36px;border-radius:9px;object-fit:contain;background:#fff;border:1px solid #e5e7eb;flex:none}
.ext-pop .en{font-weight:800;font-size:13.5px;line-height:1.5}
.ext-pop .eo{color:var(--muted);font-size:11.5px}
.ext-pop .er{display:flex;justify-content:space-between;gap:8px;border-top:1px dashed var(--border);padding:3px 0}
.ext-pop .er span:first-child{color:var(--muted);flex:none}
.ext-pop .er span:last-child{display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
.ext-pop .ei{width:100%;height:110px;object-fit:cover;border-radius:10px;margin:4px 0}
.ext-pop .enote{background:#fff7ed;color:#c2410c;border-radius:8px;padding:6px 8px;font-size:11.5px;margin-top:6px}
.ext-pop .ego{display:block;text-align:center;margin-top:8px;background:var(--accent);color:#fff!important;border-radius:10px;padding:7px;text-decoration:none;font-weight:700}
.ms-ext .si{background:#fff!important;border:1px solid var(--border-2);overflow:hidden}
.ms-ext .si img{width:40px;height:40px;object-fit:contain}
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function(){
  var markers=@json($mapMarkers);
  var stations=@json($mapStations);
  var ext=@json($externalStations);
  var quadLogo='/assets/brand/quad-icon-256.png';
  var center=@json($mapCenter);
  var userLat=center.lat,userLng=center.lng;
  var filter='all',searchQ='';

  var map=L.map('map',{zoomControl:false,attributionControl:false}).setView([center.lat,center.lng],12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);
  var userMarker=null,markerLayer=L.layerGroup().addTo(map);

  function pinIcon(color){
    return L.divIcon({
      className:'',
      html:'<div class="sc-pin '+color+'"><img src="'+quadLogo+'" alt=""></div>',
      iconSize:[50,50],iconAnchor:[25,54],popupAnchor:[0,-50]
    });
  }
  function opIcon(s){
    return L.divIcon({
      className:'',
      html:'<div class="op-pin" style="border-color:'+(s.color||'#fff')+'"><img src="'+s.logo+'" alt=""></div>',
      iconSize:[22,22],iconAnchor:[11,11],popupAnchor:[0,-10]
    });
  }
  function esc(v){return String(v==null?'':v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
  function row(label,val){return val?'<div class="er"><span>'+label+'</span><span>'+esc(val)+'</span></div>':'';}
  function extPopup(s){
    return '<div class="ext-pop">'+
      '<div class="eh"><img src="'+esc(s.logo)+'" alt=""><div><div class="en">'+esc(s.name)+'</div><div class="eo">'+esc(s.operator)+(s.network?' · '+esc(s.network):'')+'</div></div></div>'+
      (s.image?'<img class="ei" src="'+esc(s.image)+'" alt="" loading="lazy" onerror="this.remove()">':'')+
      row('آدرس',s.address)+
      row('سوکت',s.connectors)+
      row('حداکثر توان',s.kw?s.kw+' کیلووات':'')+
      row('تعداد درگاه',s.ports)+
      row('تعرفه اعلامی',s.tariff?s.tariff+' تومان / kWh':'')+
      row('ساعات کاری',s.hours)+
      row('امکانات',s.facilities)+
      row('تلفن',s.phone||s.op_phone)+
      '<div class="enote">این ایستگاه متعلق به '+esc(s.operator)+' است؛ رزرو و پرداخت آن از QUAD EV انجام نمی‌شود.</div>'+
      '<a class="ego" target="_blank" rel="noopener" href="https://www.google.com/maps/dir/?api=1&destination='+s.lat+','+s.lng+'">مسیریابی تا ایستگاه</a>'+
    '</div>';
  }
  function extVisible(s){
    if(filter==='quad'||filter==='free'||filter==='public')return false;
    if(filter==='fast'&&!s.fast)return false;
    if(searchQ&&(s.name+' '+s.operator+' '+(s.city||'')+' '+(s.address||'')).indexOf(searchQ)===-1)return false;
    return true;
  }
  var extLayer=L.layerGroup().addTo(map);
  function renderExt(){
    extLayer.clearLayers();
    ext.forEach(function(s){
      if(!extVisible(s))return;
      var mk=L.marker([s.lat,s.lng],{icon:opIcon(s)}).addTo(extLayer);
      mk.on('click',function(){showExt(s);});
    });
  }
  function showExt(s){
    var box=document.getElementById('extDetail'),sheet=document.querySelector('.sheet');
    box.innerHTML='<button type="button" class="ex-close" aria-label="بستن">×</button>'+extPopup(s);
    box.style.display='';
    box.querySelector('.ex-close').addEventListener('click',function(){box.style.display='none';});
    sheet.scrollTop=0;
    var top=170,bottom=window.innerHeight-sheet.offsetHeight;
    map.setView([s.lat,s.lng],Math.max(map.getZoom(),14),{animate:false});
    map.panBy([0,map.getSize().y/2-(top+bottom)/2],{animate:false});
  }

  function renderMarkers(){
    markerLayer.clearLayers();
    renderExt();
    if(filter==='others')return;
    markers.forEach(function(m){
      var st=stations.find(function(s){return s.lat===m.lat&&s.lng===m.lng;})||{};
      var show=matchFilter(st,m);
      if(!show)return;
      if(searchQ&&m.name.indexOf(searchQ)===-1&&!(st.type||'').includes(searchQ))return;
      var mk=L.marker([m.lat,m.lng],{icon:pinIcon(m.color),zIndexOffset:1000}).addTo(markerLayer);
      mk.bindPopup('<b>'+m.name+'</b><br><a href="'+m.url+'">مشاهده شارژر</a>');
      mk.on('click',function(){window.location.href=m.url;});
    });
  }

  function matchFilter(st,m){
    if(filter==='fast')return st.cat==='fast';
    if(filter==='free')return st.has_free;
    if(filter==='public')return st.cat==='public';
    return true;
  }

  function renderList(){
    var list=document.getElementById('list');
    var shown=filter==='others'?[]:stations.filter(function(s){
      if(filter==='fast'&&s.cat!=='fast')return false;
      if(filter==='free'&&!s.has_free)return false;
      if(filter==='public'&&s.cat!=='public')return false;
      if(searchQ&&s.name.indexOf(searchQ)===-1&&s.type.indexOf(searchQ)===-1)return false;
      return true;
    });
    document.getElementById('mapCount').textContent=filter==='others'
      ?ext.filter(extVisible).length.toLocaleString('fa-IR')+' ایستگاه از سایر اپراتورها'
      :shown.length.toLocaleString('fa-IR')+' ایستگاه در این محدوده';
    list.innerHTML=shown.map(function(s){
      var ic=s.state==='free'?'ic-green':(s.state==='busy'?'ic-orange':(s.state==='fault'?'ic-red':''));
      return '<a href="'+s.url+'" class="ms-station">'+
        '<div class="si '+ic+'">'+window.icon('plug')+'</div>'+
        '<div style="flex:1;min-width:0">'+
          '<div class="row between"><span class="fw7" style="font-size:14px">'+s.name+'</span><span class="pill '+s.status_pill+'"><span class="pdot"></span>'+s.status+'</span></div>'+
          '<div class="fs12 muted mt4">'+s.type+' · '+s.free+'</div>'+
          '<div class="row mt8" style="gap:5px;color:var(--text-2);font-size:12px;font-weight:600">'+window.icon('nav')+s.distance+'</div>'+
        '</div></a>';
    }).join('');
    document.querySelectorAll('.ms-station .row svg').forEach(function(s){s.style.width='13px';s.style.height='13px';});
    renderExtList();
  }

  function renderExtList(){
    var wrap=document.getElementById('extWrap');
    var near=ext.filter(extVisible).map(function(s){return {s:s,d:distKm(userLat,userLng,s.lat,s.lng)};})
      .sort(function(a,b){return a.d-b.d;}).slice(0,filter==='others'?30:8);
    wrap.style.display=near.length?'':'none';
    document.getElementById('extList').innerHTML=near.map(function(x){
      var s=x.s,km=x.d<1?Math.round(x.d*1000).toLocaleString('fa-IR')+' متر':x.d.toFixed(1).replace('.','٫').replace(/\d/g,function(c){return '۰۱۲۳۴۵۶۷۸۹'[c];})+' کیلومتر';
      return '<a href="#" class="ms-station ms-ext" data-ext="'+s.id+'">'+
        '<div class="si"><img src="'+esc(s.logo)+'" alt=""></div>'+
        '<div style="flex:1;min-width:0">'+
          '<div class="row between"><span class="fw7" style="font-size:14px">'+esc(s.name)+'</span></div>'+
          '<div class="fs12 muted mt4">'+esc(s.operator)+(s.kw?' · '+s.kw+' kW':'')+(s.connectors?' · '+esc(s.connectors):'')+'</div>'+
          '<div class="fs12 mt8" style="color:var(--text-2);font-weight:600">'+km+'</div>'+
        '</div></a>';
    }).join('');
    document.querySelectorAll('#extList [data-ext]').forEach(function(a){
      a.addEventListener('click',function(e){
        e.preventDefault();
        var id=+a.getAttribute('data-ext');
        var s=ext.find(function(x){return x.id===id;});
        if(s)showExt(s);
      });
    });
  }

  function apply(){
    renderMarkers();
    renderList();
  }

  document.querySelectorAll('#chips .mchip').forEach(function(b){
    b.addEventListener('click',function(){
      document.querySelectorAll('#chips .mchip').forEach(function(x){x.classList.remove('active');});
      b.classList.add('active');
      filter=b.getAttribute('data-filter');
      apply();
    });
  });

  document.getElementById('mapSearch').addEventListener('input',function(e){
    searchQ=(e.target.value||'').trim();
    apply();
  });

  document.getElementById('locBtn').addEventListener('click',function(){
    if(!navigator.geolocation){alert('موقعیت‌یاب در دسترس نیست');return;}
    navigator.geolocation.getCurrentPosition(function(pos){
      userLat=pos.coords.latitude;userLng=pos.coords.longitude;
      if(userMarker)map.removeLayer(userMarker);
      userMarker=L.circleMarker([userLat,userLng],{radius:8,color:'#fff',fillColor:'var(--accent)',fillOpacity:1,weight:3}).addTo(map);
      map.setView([userLat,userLng],14);
    });
  });

  // نمای اولیه: ایستگاه‌های نزدیک کاربر (نه کل کشور)، با درنظر گرفتن پنل پایین صفحه.
  function distKm(a,b,c,d){var r=Math.PI/180,x=(c-a)*r,y=(d-b)*r*Math.cos((a+c)/2*r);return Math.sqrt(x*x+y*y)*6371;}
  function fitNearby(lat,lng){
    var near=markers.concat(ext).map(function(m){return {m:m,d:distKm(lat,lng,m.lat,m.lng)};}).sort(function(a,b){return a.d-b.d;});
    var pick=near.filter(function(x){return x.d<=40;});
    if(!pick.length)pick=near.slice(0,3);
    if(!pick.length){map.setView([lat,lng],12);return;}
    var pts=pick.map(function(x){return [x.m.lat,x.m.lng];});pts.push([lat,lng]);
    var sheet=document.querySelector('.map-sheet,.sheet,[data-sheet]');
    var bottom=sheet?Math.min(sheet.offsetHeight,window.innerHeight*0.55):window.innerHeight*0.45;
    map.fitBounds(L.latLngBounds(pts),{paddingTopLeft:[24,110],paddingBottomRight:[24,bottom+16],maxZoom:14});
  }
  // اندازهٔ واقعی کانتینر پس از چیدمان صفحه (جلوگیری از نمایش نیمه‌کارهٔ کاشی‌ها).
  setTimeout(function(){map.invalidateSize();if(markers.length||ext.length)fitNearby(userLat,userLng);},250);
  window.addEventListener('resize',function(){map.invalidateSize();});
  if(markers.length||ext.length){fitNearby(center.lat,center.lng);}
  apply();

  window.scLive('map',20000,function(d){
    if(!d.markers||!d.stations)return;
    markers=d.markers;stations=d.stations;
    if(d.stats)document.getElementById('freeCount').textContent=Number(d.stats.free).toLocaleString('fa-IR');
    apply();
  });
})();
</script>
@endpush
