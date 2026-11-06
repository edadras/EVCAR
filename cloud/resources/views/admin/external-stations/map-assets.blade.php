@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
.op-pin{width:24px;height:24px;border-radius:50%;background:#fff;border:1.5px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.25);display:grid;place-items:center;overflow:hidden}
.op-pin img{width:100%!important;height:100%!important;object-fit:contain}
.op-pin.quad{width:46px;height:46px;border:3px solid #facc15;box-shadow:0 0 0 2px #fff,0 5px 14px rgba(0,0,0,.35);padding:2px}
.op-pin.off{opacity:.45;filter:grayscale(1)}
.leaflet-container{font-family:inherit}
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
window.scLogoPin = function (logo, cls) {
    var s = cls === 'quad' ? 46 : 24;
    return L.divIcon({ className: '', html: '<div class="op-pin ' + (cls || '') + '"><img src="' + logo + '" alt=""></div>', iconSize: [s, s], iconAnchor: [s / 2, s / 2], popupAnchor: [0, -s / 2] });
};
</script>
@endpush
