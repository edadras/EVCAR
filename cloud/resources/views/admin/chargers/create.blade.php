@php
  $charger = $charger ?? null;
  $editing = $charger !== null;
  $cmeta = $charger?->meta ?? [];
  $connCount = $editing ? (int) $charger->connectors->max('connector_number') : 2;
@endphp
@extends('admin.layouts.app', ['page' => 'stations', 'title' => $editing ? 'ویرایش شارژر '.$charger->charge_point_id : 'افزودن شارژر', 'crumb' => $station->name])

@section('content')
<div class="flex items-center gap-12 mt-8" style="margin-bottom:18px">
  <a href="{{ $editing ? route('admin.chargers.show', $charger) : route('admin.stations.show', $station) }}" class="btn btn-sm"><span class="bic" data-ic="dots"></span> {{ $editing ? 'بازگشت به شارژر' : 'بازگشت به ایستگاه' }}</a>
</div>

<form method="POST" action="{{ $editing ? route('admin.chargers.update', $charger) : route('admin.stations.chargers.store', $station) }}">@csrf
@if($editing)@method('PUT')@endif
<div class="grid col-2-1">
  <div style="display:flex;flex-direction:column;gap:16px">
  <div class="card">
    <div class="card-head"><div><h3>{{ $editing ? 'مشخصات شارژر' : 'شارژر جدید' }}</h3><div class="ch-sub">{{ $station->name }} · ثبت در OCPP</div></div></div>
    <div class="card-pad">
        <div class="form-grid">
          <div class="ffield"><label>شناسه Charge Point (OCPP) <span class="req">*</span></label><input class="finput tnum" name="charge_point_id" value="{{ old('charge_point_id', $charger?->charge_point_id ?? 'CP-'.strtoupper(\Illuminate\Support\Str::random(4))) }}" required dir="ltr" style="text-align:right">
            @if($editing)<span class="fhint">اگر عوض شود، باید روی خود دستگاه هم همین شناسه تنظیم شود.</span>@endif</div>
          <div class="ffield"><label>نام شارژر</label><input class="finput" name="name" value="{{ old('name', $cmeta['name'] ?? '') }}" placeholder="مثلاً شارژر ورودی پارکینگ"></div>
          <div class="ffield"><label>سازنده</label><input class="finput" name="vendor" value="{{ old('vendor', $charger?->vendor) }}" placeholder="مثلاً ZT"></div>
          <div class="ffield"><label>مدل</label><input class="finput" name="model" value="{{ old('model', $charger?->model) }}" placeholder="مثلاً ZT_DC02_160"></div>
          <div class="ffield"><label>شماره سریال</label><input class="finput tnum" name="serial_number" value="{{ old('serial_number', $charger?->serial_number) }}" dir="ltr" style="text-align:right"></div>
          <div class="ffield"><label>حداکثر توان (kW)</label><input class="finput tnum" name="max_power_kw" type="number" min="1" max="500" value="{{ old('max_power_kw', $charger?->max_power_kw ?? 120) }}" dir="ltr" style="text-align:right"></div>
          <div class="ffield"><label>نوع کانکتور</label>
            <select class="fselect" name="connector_type">
              @foreach(\App\Models\Charger::CONNECTOR_TYPES as $ct => $label)
              <option value="{{ $ct }}" @selected(old('connector_type', $charger?->connector_type ?? 'GBT')===$ct)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="ffield"><label>تعداد کانکتور <span class="req">*</span></label><input class="finput tnum" name="connector_count" type="number" min="{{ $editing ? max(1, $connCount) : 1 }}" max="4" value="{{ old('connector_count', $connCount) }}" required dir="ltr" style="text-align:right">
            @if($editing)<span class="fhint">با افزایش، کانکتور جدید و QR آن ساخته می‌شود. برای حذف، دکمهٔ × کنار همان کانکتور را بزنید.</span>@endif</div>
          <div class="ffield"><label>اتصال OCPP</label>
            <select class="fselect" name="connection_mode">
              @foreach(['auto' => 'خودکار (Edge اگر ایستگاه دارد، وگرنه مستقیم)', 'direct' => 'مستقیم به سرور اصلی', 'edge' => 'از طریق Edge ایستگاه'] as $v => $l)
              <option value="{{ $v }}" @selected(old('connection_mode', $charger?->connection_mode ?? 'auto')===$v)>{{ $l }}</option>
              @endforeach
            </select>
          </div>
          @if($editing)
          <div class="ffield"><label>وضعیت شارژر</label>
            <select class="fselect" name="status">
              @foreach(\App\Http\Controllers\Admin\ChargerController::CONNECTOR_STATUSES as $v => $l)
              <option value="{{ $v }}" @selected(old('status', $charger->status)===$v)>{{ $l }}</option>
              @endforeach
            </select>
          </div>
          @endif
          <div class="ffield"><label>رمز OCPP (Basic Auth، اختیاری)</label><input class="finput" name="ocpp_password" type="password" minlength="8" autocomplete="new-password" dir="ltr" placeholder="{{ $editing && !empty($cmeta['ocpp_password']) ? 'تنظیم شده — خالی بگذارید تا تغییر نکند' : '' }}">
            @if($editing && !empty($cmeta['ocpp_password']))
            <label class="fs12 muted mt-4" style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="clear_ocpp_password" value="1"> حذف رمز</label>
            @endif</div>
        </div>
        <div class="fs12 muted mt-12">آدرس OCPP در تنظیمات شارژر: مستقیم ← <span dir="ltr">wss://ev.edadras.com/ocpp/&lt;شناسه&gt;</span> · از طریق Edge ← <span dir="ltr">ws://&lt;IP ایستگاه&gt;:9000/ocpp/&lt;شناسه&gt;</span></div>
    </div>
  </div>

  @if($editing)
  <div class="card">
    <div class="card-head"><div><h3>کانکتورها</h3><div class="ch-sub">نام، توان و وضعیت هر کانکتور</div></div></div>
    <div class="card-pad" style="display:flex;flex-direction:column;gap:10px">
      @foreach($charger->connectors->sortBy('connector_number') as $cn)
      <div class="conn-edit">
        <div class="ce-num tnum">{{ $cn->connector_number }}</div>
        <input class="finput" name="connectors[{{ $cn->id }}][name]" value="{{ old('connectors.'.$cn->id.'.name', $cn->meta['name'] ?? '') }}" placeholder="نام (مثلاً کانکتور {{ ['A','B','C','D'][$cn->connector_number - 1] ?? $cn->connector_number }})" style="flex:1;min-width:120px">
        <input class="finput tnum" type="number" min="1" max="500" name="connectors[{{ $cn->id }}][max_power_kw]" value="{{ old('connectors.'.$cn->id.'.max_power_kw', $cn->max_power_kw) }}" placeholder="kW" style="max-width:90px" dir="ltr">
        <span class="fs12 muted">kW</span>
        <select class="fselect" name="connectors[{{ $cn->id }}][operational_status]" style="max-width:120px">
          @foreach(\App\Http\Controllers\Admin\ChargerController::CONNECTOR_STATUSES as $v => $l)
          <option value="{{ $v }}" @selected(old('connectors.'.$cn->id.'.operational_status', $cn->operational_status)===$v)>{{ $l }}</option>
          @endforeach
        </select>
        <span class="pill fs12">{{ $cn->ocpp_status }}</span>
        <button type="submit" form="delConn{{ $cn->id }}" class="ce-del" title="حذف کانکتور" aria-label="حذف کانکتور"
          onclick="return confirm('کانکتور {{ $cn->connector_number }} و QR آن حذف شود؟');" data-ic="x"></button>
      </div>
      @endforeach
    </div>
  </div>
  @endif
  </div>

  <div style="display:flex;flex-direction:column;gap:16px;align-self:start">
    <div class="card">
      <div class="card-head"><h3>راهنما</h3></div>
      <div class="card-pad">
        <div class="lrow" style="border:none;padding:8px 0"><div class="l-main"><div class="l-title">شناسه OCPP</div><div class="l-sub">باید با شارژر فیزیکی یکسان باشد</div></div></div>
        <div class="lrow" style="border:none;padding:8px 0"><div class="l-main"><div class="l-title">کانکتورها</div><div class="l-sub">هر کانکتور به‌صورت خودکار یک QR اختصاصی می‌گیرد</div></div></div>
      </div>
    </div>
    <button type="submit" class="btn btn-primary" style="height:48px"><span class="bic" data-ic="plus"></span> {{ $editing ? 'ذخیره تغییرات' : 'ثبت شارژر' }}</button>
    @if($editing)
    <button type="submit" form="delCharger" class="btn" style="height:44px;color:var(--red);border-color:var(--red-soft)"
      onclick="var v=prompt('حذف کل شارژر و همهٔ کانکتورها و QRهایش. سوابق شارژ می‌ماند.\nبرای تأیید، شناسهٔ شارژر را بنویسید: {{ $charger->charge_point_id }}');if(v===null)return false;document.getElementById('delChargerId').value=v;return true;"><span class="bic" data-ic="x"></span> حذف کل شارژر</button>
    @endif
  </div>
</div>
</form>
@if($editing)
<form id="delCharger" method="POST" action="{{ route('admin.chargers.destroy', $charger) }}" hidden>@csrf @method('DELETE')<input type="hidden" name="confirm_id" id="delChargerId"></form>
@foreach($charger->connectors as $cn)
<form id="delConn{{ $cn->id }}" method="POST" action="{{ route('admin.connectors.destroy', $cn) }}" hidden>@csrf @method('DELETE')</form>
@endforeach
@endif
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
.conn-edit{display:flex;align-items:center;gap:10px;border:1px solid var(--border);border-radius:13px;padding:10px 12px;background:var(--surface-2);flex-wrap:wrap}
.ce-del{width:34px;height:34px;border-radius:9px;border:1px solid var(--border);background:var(--surface);color:var(--red);display:grid;place-items:center;margin-inline-start:auto;cursor:pointer}.ce-del svg{width:16px;height:16px}
.ce-num{width:34px;height:34px;border-radius:10px;background:var(--accent-soft);color:var(--accent-strong);display:grid;place-items:center;font-weight:800;flex:none}
</style>
@endpush

@push('scripts')
<script>
(function(){document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});})();
</script>
@endpush
