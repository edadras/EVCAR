@extends('app.layouts.mobile', ['tab' => 'map'])

@section('title', 'SafirCharge — نقشه شارژرها')

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
</div>

<button class="loc-btn" type="button" id="locBtn" data-ic="nav"></button>

<div class="sheet">
    <div class="sheet-grip"></div>
    <div class="row between" style="margin-bottom:12px">
        <div class="fw8" style="font-size:16px" id="mapCount">{{ Persian::digits($mapStats['total']) }} ایستگاه در این محدوده</div>
        <span class="pill pill-green"><span class="pdot"></span><span id="freeCount">{{ Persian::digits($mapStats['free']) }}</span> آزاد</span>
    </div>
    <div style="display:flex;flex-direction:column;gap:11px" id="list"></div>
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
.ms-station .si svg{width:23px;height:23px}
.sc-pin{width:32px;height:32px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:grid;place-items:center;color:#fff;font-size:14px;box-shadow:0 2px 8px rgba(0,0,0,.25)}
.sc-pin span{transform:rotate(45deg)}
.sc-pin.green{background:var(--green)}.sc-pin.amber{background:var(--amber)}
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function(){
  var markers=@json($mapMarkers);
  var stations=@json($mapStations);
  var center=@json($mapCenter);
  var userLat=center.lat,userLng=center.lng;
  var filter='all',searchQ='';

  var map=L.map('map',{zoomControl:false,attributionControl:false}).setView([center.lat,center.lng],12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19}).addTo(map);
  var userMarker=null,markerLayer=L.layerGroup().addTo(map);

  function pinIcon(color){
    return L.divIcon({
      className:'',
      html:'<div class="sc-pin '+color+'"><span>⚡</span></div>',
      iconSize:[32,32],iconAnchor:[16,32]
    });
  }

  function renderMarkers(){
    markerLayer.clearLayers();
    markers.forEach(function(m){
      var st=stations.find(function(s){return s.lat===m.lat&&s.lng===m.lng;})||{};
      var show=matchFilter(st,m);
      if(!show)return;
      if(searchQ&&m.name.indexOf(searchQ)===-1&&!(st.type||'').includes(searchQ))return;
      var mk=L.marker([m.lat,m.lng],{icon:pinIcon(m.color)}).addTo(markerLayer);
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
    var shown=stations.filter(function(s){
      if(filter==='fast'&&s.cat!=='fast')return false;
      if(filter==='free'&&!s.has_free)return false;
      if(filter==='public'&&s.cat!=='public')return false;
      if(searchQ&&s.name.indexOf(searchQ)===-1&&s.type.indexOf(searchQ)===-1)return false;
      return true;
    });
    document.getElementById('mapCount').textContent=shown.length.toLocaleString('fa-IR')+' ایستگاه در این محدوده';
    list.innerHTML=shown.map(function(s){
      var ic=s.status_pill==='pill-green'?'ic-green':'ic-orange';
      return '<a href="'+s.url+'" class="ms-station">'+
        '<div class="si '+ic+'">'+window.icon('plug')+'</div>'+
        '<div style="flex:1;min-width:0">'+
          '<div class="row between"><span class="fw7" style="font-size:14px">'+s.name+'</span><span class="pill '+s.status_pill+'"><span class="pdot"></span>'+s.status+'</span></div>'+
          '<div class="fs12 muted mt4">'+s.type+' · '+s.free+'</div>'+
          '<div class="row mt8" style="gap:5px;color:var(--text-2);font-size:12px;font-weight:600">'+window.icon('nav')+s.distance+'</div>'+
        '</div></a>';
    }).join('');
    document.querySelectorAll('.ms-station .row svg').forEach(function(s){s.style.width='13px';s.style.height='13px';});
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

  if(markers.length){
    var bounds=L.latLngBounds(markers.map(function(m){return [m.lat,m.lng];}));
    map.fitBounds(bounds.pad(0.15));
  }
  apply();
})();
</script>
@endpush
