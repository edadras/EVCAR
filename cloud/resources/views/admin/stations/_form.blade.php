@php $s = $station ?? null; @endphp
<div class="ffield"><label>نام ایستگاه *</label><input class="finput" name="name" value="{{ old('name', $s?->name) }}" required></div>
<div class="ffield mt-12"><label>کد</label><input class="finput tnum" name="code" value="{{ old('code', $s?->code) }}" dir="ltr"></div>
<div class="grid col-2 mt-12">
    <div class="ffield"><label>شهر</label><input class="finput" name="city" value="{{ old('city', $s?->city) }}"></div>
    <div class="ffield"><label>استان</label><input class="finput" name="province" value="{{ old('province', $s?->province) }}"></div>
</div>
<div class="ffield mt-12"><label>آدرس</label><input class="finput" name="address" value="{{ old('address', $s?->address) }}"></div>
<div class="grid col-2 mt-12">
    <div class="ffield"><label>عرض جغرافیایی</label><input class="finput tnum" name="latitude" value="{{ old('latitude', $s?->latitude) }}" dir="ltr"></div>
    <div class="ffield"><label>طول جغرافیایی</label><input class="finput tnum" name="longitude" value="{{ old('longitude', $s?->longitude) }}" dir="ltr"></div>
</div>
<div class="grid col-2 mt-12">
    <div class="ffield"><label>نوع</label>
        <select class="finput" name="type">
            @foreach(['public'=>'عمومی','private'=>'خصوصی','semi_public'=>'نیمه‌عمومی'] as $v=>$l)
            <option value="{{ $v }}" @selected(old('type', $s?->type ?? 'public')==$v)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    @if($s)
    <div class="ffield"><label>وضعیت</label>
        <select class="finput" name="status">
            @foreach(['active','inactive','maintenance'] as $st)
            <option value="{{ $st }}" @selected(old('status', $s->status)==$st)>{{ $st }}</option>
            @endforeach
        </select>
    </div>
    @endif
</div>
<div class="grid col-2 mt-12">
    <div class="ffield"><label>WiFi SSID</label><input class="finput" name="wifi_ssid" value="{{ old('wifi_ssid', $s?->wifi_ssid) }}"></div>
    <div class="ffield"><label>hostname محلی</label><input class="finput" name="local_hostname" value="{{ old('local_hostname', $s?->local_hostname ?? 'station.local') }}"></div>
</div>
<div class="grid col-2 mt-12">
    <div class="ffield"><label>سقف توان انشعاب (kW) — Smart Charging</label><input class="finput tnum" type="number" step="0.1" min="0" name="grid_limit_kw" value="{{ old('grid_limit_kw', $s?->meta['grid_limit_kw'] ?? '') }}" dir="ltr" placeholder="خالی = بدون محدودیت"></div>
    <div class="ffield"><label>اتصال شارژرها</label><div class="fs12 muted" style="padding-top:10px">{{ ($s?->edgeStation) ? 'از طریق کنترلر محلی Edge' : 'مستقیم به سرور اصلی — ws://<سرور>:9100/ocpp/<شناسه شارژر>' }}</div></div>
</div>
