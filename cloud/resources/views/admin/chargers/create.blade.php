@extends('admin.layouts.app', ['page' => 'stations', 'title' => 'افزودن شارژر', 'crumb' => $station->name])

@section('content')
<div class="flex items-center gap-12 mt-8" style="margin-bottom:18px">
  <a href="{{ route('admin.stations.show', $station) }}" class="btn btn-sm"><span class="bic" data-ic="dots"></span> بازگشت به ایستگاه</a>
</div>

<div class="grid col-2-1">
  <div class="card">
    <div class="card-head"><div><h3>شارژر جدید</h3><div class="ch-sub">{{ $station->name }} · ثبت در OCPP</div></div></div>
    <div class="card-pad">
      <form method="POST" action="{{ route('admin.stations.chargers.store', $station) }}">@csrf
        <div class="form-grid">
          <div class="ffield col-span"><label>شناسه Charge Point (OCPP) <span class="req">*</span></label><input class="finput tnum" name="charge_point_id" value="{{ old('charge_point_id', 'CP-'.strtoupper(\Illuminate\Support\Str::random(4))) }}" required dir="ltr" style="text-align:right"></div>
          <div class="ffield"><label>سازنده</label><input class="finput" name="vendor" value="{{ old('vendor') }}" placeholder="مثلاً ABB"></div>
          <div class="ffield"><label>مدل</label><input class="finput" name="model" value="{{ old('model') }}" placeholder="مثلاً Terra 184"></div>
          <div class="ffield"><label>حداکثر توان (kW)</label><input class="finput tnum" name="max_power_kw" type="number" value="{{ old('max_power_kw', 120) }}" dir="ltr" style="text-align:right"></div>
          <div class="ffield"><label>نوع کانکتور</label>
            <select class="fselect" name="connector_type">
              @foreach(['CCS2','CHAdeMO','Type2','Type1'] as $ct)
              <option value="{{ $ct }}" @selected(old('connector_type', 'CCS2')===$ct)>{{ $ct }}</option>
              @endforeach
            </select>
          </div>
          <div class="ffield"><label>تعداد کانکتور <span class="req">*</span></label><input class="finput tnum" name="connector_count" type="number" min="1" max="4" value="{{ old('connector_count', 2) }}" required dir="ltr" style="text-align:right"></div>
        </div>
        <button type="submit" class="btn btn-primary mt-16" style="height:48px"><span class="bic" data-ic="plus"></span> ثبت شارژر</button>
      </form>
    </div>
  </div>
  <div class="card" style="align-self:start">
    <div class="card-head"><h3>راهنما</h3></div>
    <div class="card-pad">
      <div class="lrow" style="border:none;padding:8px 0"><div class="l-main"><div class="l-title">شناسه OCPP</div><div class="l-sub">باید با شارژر فیزیکی یکسان باشد</div></div></div>
      <div class="lrow" style="border:none;padding:8px 0"><div class="l-main"><div class="l-title">کانکتورها</div><div class="l-sub">پس از ثبت، نازل‌ها به‌صورت خودکار ساخته می‌شوند</div></div></div>
    </div>
  </div>
</div>
@endsection

@push('styles')
<style>
.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}
</style>
@endpush

@push('scripts')
<script>
(function(){document.querySelectorAll('[data-ic]').forEach(function(el){el.innerHTML=window.icon(el.getAttribute('data-ic'));});})();
</script>
@endpush
