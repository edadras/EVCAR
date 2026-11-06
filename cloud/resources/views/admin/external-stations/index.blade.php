@extends('admin.layouts.app', ['page' => 'external-stations', 'title' => 'ایستگاه‌های سایر اپراتورها', 'crumb' => 'مدیریت شبکه'])

@section('content')
@php use App\Support\Persian; @endphp

<div class="flex between items-center mt-8" style="margin-bottom:18px;gap:12px;flex-wrap:wrap">
    <div>
        <h3 style="font-size:16px;margin:0">ایستگاه‌های شارژ سایر شرکت‌ها</h3>
        <div class="muted fs12">فقط برای نمایش روی نقشهٔ اپ و مسیریاب — رزرو و پرداخت از QUAD EV ندارند</div>
    </div>
    <div class="flex gap-8">
        <a href="{{ route('admin.ev-operators.index') }}" class="btn btn-sm">اپراتورها و لوگوها</a>
        <a href="{{ route('admin.external-stations.create', request()->only('operator')) }}" class="btn btn-sm btn-primary">
            <span class="bic" data-ic="plus"></span> ایستگاه جدید
        </a>
    </div>
</div>

<div class="card" style="margin-bottom:16px">
    <div class="card-head">
        <div><h3>نقشه</h3><div class="ch-sub">لوگوی QUAD = ایستگاه‌های خود مجموعه · لوگوی برندها = ایستگاه‌های نمایشی</div></div>
        <form method="GET" class="flex gap-8" style="flex-wrap:wrap">
            <select name="operator" class="finput" style="min-width:170px" onchange="this.form.submit()">
                <option value="">همه اپراتورها</option>
                @foreach($operators as $op)
                <option value="{{ $op->id }}" @selected((string) request('operator') === (string) $op->id)>{{ $op->name }}</option>
                @endforeach
            </select>
            <input name="q" class="finput" value="{{ request('q') }}" placeholder="نام، شهر یا آدرس…" style="min-width:180px">
            <button class="btn btn-sm">جستجو</button>
        </form>
    </div>
    <div class="card-pad">
        <div id="extMap" style="height:460px;border-radius:14px;overflow:hidden"></div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <div><h3>فهرست ایستگاه‌ها</h3><div class="ch-sub">{{ Persian::digits($stations->total()) }} از {{ Persian::digits($total) }} ایستگاه</div></div>
    </div>
    <div class="table-wrap" style="overflow-x:auto">
        <table class="tbl">
            <thead>
                <tr><th>ایستگاه</th><th>اپراتور</th><th>شهر</th><th>سوکت / توان</th><th>تعرفه</th><th>وضعیت</th><th></th></tr>
            </thead>
            <tbody>
                @forelse($stations as $s)
                <tr>
                    <td style="white-space:normal;min-width:260px;max-width:360px">
                        <div class="fw7">{{ $s->name }}</div>
                        <div class="muted fs11 mt-4" style="line-height:1.6">{{ $s->address }}</div>
                    </td>
                    <td>
                        <div class="flex items-center gap-8">
                            <img src="{{ $s->operator?->logoUrl() }}" alt="" style="width:26px;height:26px;object-fit:contain;border-radius:6px;background:#fff;border:1px solid var(--border-2)">
                            <span class="fs12">{{ $s->operator?->name }}</span>
                        </div>
                    </td>
                    <td class="fs12">{{ $s->city ?? '—' }}</td>
                    <td class="fs12">
                        <div>{{ $s->connectorLabel() ?: '—' }}</div>
                        <div class="muted">{{ $s->maxKw() ? Persian::num($s->maxKw()).' kW' : '' }}{{ $s->ports ? ' · '.Persian::digits($s->ports).' درگاه' : '' }}</div>
                    </td>
                    <td class="fs12 tnum">{{ $s->tariff_toman ? Persian::num($s->tariff_toman).' ت/kWh' : '—' }}</td>
                    <td>
                        @if($s->is_active)<span class="pill pill-green">نمایش</span>@else<span class="pill pill-amber">مخفی</span>@endif
                        @if($s->source !== 'manual')<div class="muted fs11 mt-4">منبع: {{ $s->source }}</div>@endif
                    </td>
                    <td>
                        <div class="flex gap-8">
                            <a href="{{ route('admin.external-stations.edit', $s) }}" class="btn btn-sm btn-primary">ویرایش</a>
                            <form method="POST" action="{{ route('admin.external-stations.destroy', $s) }}" onsubmit="return confirm('ایستگاه «{{ $s->name }}» حذف شود؟');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="color:var(--red)">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="muted" style="padding:24px;text-align:center">ایستگاهی ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($stations->hasPages())<div class="card-pad">{{ $stations->links() }}</div>@endif
</div>
@endsection

@include('admin.external-stations.map-assets')

@push('scripts')
<script>
(function () {
    var ext = @json($markers);
    var ours = @json($ourMarkers);
    var map = L.map('extMap').setView([32.5, 53.5], 5);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(map);
    var bounds = [];
    ours.forEach(function (m) {
        L.marker([m.lat, m.lng], { icon: window.scLogoPin('/assets/brand/quad-icon-256.png', 'quad'), zIndexOffset: 1000 })
            .addTo(map).bindPopup('<b>' + m.name + '</b><br>ایستگاه QUAD EV');
    });
    ext.forEach(function (m) {
        L.marker([m.lat, m.lng], { icon: window.scLogoPin(m.logo, m.active ? '' : 'off') })
            .addTo(map).bindPopup('<b>' + m.name + '</b><br>' + (m.operator || '') + '<br><a href="' + m.url + '">ویرایش</a>');
        bounds.push([m.lat, m.lng]);
    });
    if (bounds.length) map.fitBounds(bounds, { padding: [30, 30], maxZoom: 13 });
})();
</script>
@endpush

@push('styles')
<style>.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}</style>
@endpush
