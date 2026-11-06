@extends('admin.layouts.app', ['page' => 'external-stations', 'title' => $station ? 'ویرایش ایستگاه نمایشی' : 'افزودن ایستگاه نمایشی', 'crumb' => 'ایستگاه‌های سایر اپراتورها'])

@section('content')
@php
    use App\Models\ExternalStation;
    $dc = old('dc_connectors', $station?->dc_connectors ?? []);
    $ac = old('ac_connectors', $station?->ac_connectors ?? []);
    $lat = old('latitude', $station?->latitude);
    $lng = old('longitude', $station?->longitude);
@endphp

<div class="flex between items-center mt-8" style="margin-bottom:18px">
    <a href="{{ route('admin.external-stations.index') }}" class="btn btn-sm">بازگشت به فهرست</a>
    @if($station)
    <form method="POST" action="{{ route('admin.external-stations.destroy', $station) }}" onsubmit="return confirm('این ایستگاه حذف شود؟');">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-sm" style="color:var(--red)">حذف ایستگاه</button>
    </form>
    @endif
</div>

@if($operators->isEmpty())
<div class="alert" style="margin-bottom:16px;padding:12px 16px;background:var(--orange-soft);border-radius:var(--r);color:var(--orange)">
    ابتدا یک اپراتور (برند) با لوگو بسازید: <a href="{{ route('admin.ev-operators.create') }}">افزودن اپراتور</a>
</div>
@endif

<form method="POST" action="{{ $station ? route('admin.external-stations.update', $station) : route('admin.external-stations.store') }}">
    @csrf
    @if($station) @method('PUT') @endif

    <div class="grid col-2-1">
        <div style="display:flex;flex-direction:column;gap:16px">
            <div class="card">
                <div class="card-head"><div><h3>موقعیت روی نقشه</h3><div class="ch-sub">روی نقشه کلیک کنید یا نشانگر را بکشید</div></div></div>
                <div class="card-pad">
                    <div id="pickMap" style="height:380px;border-radius:14px;overflow:hidden;margin-bottom:12px"></div>
                    <div class="form-grid">
                        <div class="ffield">
                            <label>عرض جغرافیایی <span class="req">*</span></label>
                            <input class="finput tnum" dir="ltr" name="latitude" id="latIn" type="number" step="0.0000001" value="{{ $lat }}" required>
                        </div>
                        <div class="ffield">
                            <label>طول جغرافیایی <span class="req">*</span></label>
                            <input class="finput tnum" dir="ltr" name="longitude" id="lngIn" type="number" step="0.0000001" value="{{ $lng }}" required>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-head"><h3>مشخصات ایستگاه</h3></div>
                <div class="card-pad">
                    <div class="form-grid">
                        <div class="ffield">
                            <label>اپراتور / برند <span class="req">*</span></label>
                            <select class="finput" name="ev_operator_id" id="opSel" required>
                                @foreach($operators as $op)
                                <option value="{{ $op->id }}" data-logo="{{ $op->logoUrl() }}" @selected((string) old('ev_operator_id', $selectedOperator) === (string) $op->id)>{{ $op->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="ffield">
                            <label>نام ایستگاه <span class="req">*</span></label>
                            <input class="finput" name="name" value="{{ old('name', $station?->name) }}" placeholder="مثلاً ایستگاه شارژ مپنا - برج میلاد" required>
                        </div>
                        <div class="ffield">
                            <label>شهر</label>
                            <input class="finput" name="city" value="{{ old('city', $station?->city) }}">
                        </div>
                        <div class="ffield">
                            <label>تلفن</label>
                            <input class="finput" name="phone" dir="ltr" value="{{ old('phone', $station?->phone) }}">
                        </div>
                        <div class="ffield col-span">
                            <label>آدرس</label>
                            <input class="finput" name="address" value="{{ old('address', $station?->address) }}">
                        </div>
                        <div class="ffield">
                            <label>سوکت‌های DC</label>
                            <div class="flex gap-8" style="flex-wrap:wrap;padding-top:6px">
                                @foreach(ExternalStation::CONNECTORS as $k => $label)
                                <label class="fs12"><input type="checkbox" name="dc_connectors[]" value="{{ $k }}" @checked(in_array($k, $dc, true))> {{ $label }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="ffield">
                            <label>حداکثر توان DC (kW)</label>
                            <input class="finput tnum" name="dc_max_kw" type="number" step="0.1" min="1" max="600" value="{{ old('dc_max_kw', $station?->dc_max_kw) }}">
                        </div>
                        <div class="ffield">
                            <label>سوکت‌های AC</label>
                            <div class="flex gap-8" style="flex-wrap:wrap;padding-top:6px">
                                @foreach(ExternalStation::CONNECTORS as $k => $label)
                                <label class="fs12"><input type="checkbox" name="ac_connectors[]" value="{{ $k }}" @checked(in_array($k, $ac, true))> {{ $label }}</label>
                                @endforeach
                            </div>
                        </div>
                        <div class="ffield">
                            <label>حداکثر توان AC (kW)</label>
                            <input class="finput tnum" name="ac_max_kw" type="number" step="0.1" min="1" max="44" value="{{ old('ac_max_kw', $station?->ac_max_kw) }}">
                        </div>
                        <div class="ffield">
                            <label>تعداد درگاه</label>
                            <input class="finput tnum" name="ports" type="number" min="1" max="200" value="{{ old('ports', $station?->ports) }}">
                        </div>
                        <div class="ffield">
                            <label>تعرفه (تومان به ازای هر kWh)</label>
                            <input class="finput tnum" name="tariff_toman" type="number" min="0" value="{{ old('tariff_toman', $station?->tariff_toman) }}">
                        </div>
                        <div class="ffield">
                            <label>ساعات کاری</label>
                            <input class="finput" name="hours" value="{{ old('hours', $station?->hours) }}" placeholder="۲۴ ساعته">
                        </div>
                        <div class="ffield">
                            <label>امکانات رفاهی</label>
                            <input class="finput" name="facilities" value="{{ old('facilities', $station?->facilities) }}" placeholder="رستوران، پارکینگ، کافه">
                        </div>
                        <div class="ffield col-span">
                            <label>آدرس تصویر</label>
                            <input class="finput" name="image_url" dir="ltr" value="{{ old('image_url', $station?->image_url) }}" placeholder="https://…">
                        </div>
                        <div class="ffield col-span">
                            <label>یادداشت</label>
                            <textarea class="finput" name="notes" rows="2" maxlength="500">{{ old('notes', $station?->notes) }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div style="align-self:start;position:sticky;top:84px">
            <div class="card">
                <div class="card-head"><h3>نمایش</h3></div>
                <div class="card-pad">
                    <div class="flex items-center gap-8" style="margin-bottom:12px">
                        <img id="opLogo" src="" alt="" style="width:44px;height:44px;object-fit:contain;border-radius:10px;border:1px solid var(--border-2);background:#fff">
                        <span class="muted fs12">این لوگو روی نقشه برای این ایستگاه نمایش داده می‌شود.</span>
                    </div>
                    <label class="toggle-row" style="margin:0;padding:8px 0">
                        <span>نمایش روی نقشه اپ</span>
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $station?->is_active ?? true)) style="width:18px;height:18px">
                    </label>
                    @if($station && $station->source !== 'manual')
                    <div class="muted fs11 mt-8">منبع داده: {{ $station->source }} · {{ $station->source_ref }}</div>
                    @endif
                </div>
                <div class="card-pad" style="border-top:1px solid var(--border-2)">
                    <button type="submit" class="btn btn-primary" style="width:100%">{{ $station ? 'ذخیره تغییرات' : 'افزودن به نقشه' }}</button>
                </div>
            </div>
        </div>
    </div>
</form>
@endsection

@include('admin.external-stations.map-assets')

@push('scripts')
<script>
(function () {
    var latIn = document.getElementById('latIn'), lngIn = document.getElementById('lngIn');
    var opSel = document.getElementById('opSel'), opLogo = document.getElementById('opLogo');
    var ours = @json($ourMarkers);
    var hasPos = latIn.value && lngIn.value;
    var map = L.map('pickMap').setView(hasPos ? [+latIn.value, +lngIn.value] : [35.70, 51.40], hasPos ? 15 : 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    ours.forEach(function (m) {
        L.marker([m.lat, m.lng], { icon: window.scLogoPin(@json(asset('assets/brand/quad-icon-256.png')), 'quad') }).addTo(map).bindTooltip(m.name);
    });
    var marker = null;
    function logo() { var o = opSel && opSel.selectedOptions[0]; return o ? o.getAttribute('data-logo') : ''; }
    function place(lat, lng) {
        latIn.value = lat.toFixed(7); lngIn.value = lng.toFixed(7);
        if (!marker) {
            marker = L.marker([lat, lng], { draggable: true, icon: window.scLogoPin(logo()) }).addTo(map);
            marker.on('dragend', function () { var p = marker.getLatLng(); place(p.lat, p.lng); });
        } else {
            marker.setLatLng([lat, lng]);
        }
    }
    if (hasPos) place(+latIn.value, +lngIn.value);
    map.on('click', function (e) { place(e.latlng.lat, e.latlng.lng); });
    [latIn, lngIn].forEach(function (el) {
        el.addEventListener('change', function () {
            if (latIn.value && lngIn.value) { place(+latIn.value, +lngIn.value); map.setView([+latIn.value, +lngIn.value], 15); }
        });
    });
    function syncLogo() {
        opLogo.src = logo();
        if (marker) marker.setIcon(window.scLogoPin(logo()));
    }
    if (opSel) opSel.addEventListener('change', syncLogo);
    syncLogo();
})();
</script>
@endpush
