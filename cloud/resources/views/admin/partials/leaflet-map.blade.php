<div id="{{ $mapId ?? 'scMap' }}" class="map-panel" style="height:{{ $height ?? '480px' }};border-radius:14px;overflow:hidden"></div>

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
<style>
    #{{ $mapId ?? 'scMap' }} .leaflet-container { height: 100%; width: 100%; font-family: inherit; }
    .sc-marker { width: 14px; height: 14px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 2px 8px rgba(0,0,0,.25); }
    .sc-marker.online { background: var(--green); }
    .sc-marker.busy { background: var(--amber); }
    .sc-marker.offline { background: var(--muted); }
    .sc-marker.error { background: var(--red); }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
<script>
(function () {
    var markers = @json($markers ?? []);
    var el = document.getElementById(@json($mapId ?? 'scMap'));
    if (!el || !window.L || !markers.length) return;

    var map = L.map(el, { scrollWheelZoom: true }).setView([32.5, 53.5], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);

    var bounds = [];
    markers.forEach(function (m) {
        var color = m.status === 'online' ? 'online' : (m.status === 'busy' ? 'busy' : (m.status === 'error' ? 'error' : 'offline'));
        var icon = L.divIcon({
            className: '',
            html: '<div class="sc-marker ' + color + '"></div>',
            iconSize: [14, 14],
            iconAnchor: [7, 7]
        });
        var popup = '<b>' + m.name + '</b><br>' + (m.city || '') + '<br>' + m.online + '/' + m.chargers + ' شارژر آنلاین';
        if (m.url) popup += '<br><a href="' + m.url + '">جزئیات</a>';
        L.marker([m.lat, m.lng], { icon: icon }).addTo(map).bindPopup(popup);
        bounds.push([m.lat, m.lng]);
    });

    if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 12 });
})();
</script>
@endpush
