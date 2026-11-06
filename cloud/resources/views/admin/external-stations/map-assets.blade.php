@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
.op-pin{width:34px;height:34px;border-radius:50%;background:#fff;border:2px solid #fff;box-shadow:0 2px 8px rgba(0,0,0,.3);display:grid;place-items:center;overflow:hidden}
.op-pin img{width:100%!important;height:100%!important;object-fit:contain}
.op-pin.quad{width:40px;height:40px;border:3px solid #facc15}
.op-pin.off{opacity:.45;filter:grayscale(1)}
.leaflet-container{font-family:inherit}
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
window.scLogoPin = function (logo, cls) {
    var s = cls === 'quad' ? 40 : 34;
    return L.divIcon({ className: '', html: '<div class="op-pin ' + (cls || '') + '"><img src="' + logo + '" alt=""></div>', iconSize: [s, s], iconAnchor: [s / 2, s / 2], popupAnchor: [0, -s / 2] });
};
</script>
@endpush
