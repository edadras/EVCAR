@extends('admin.layouts.app', ['page' => 'insights', 'title' => 'تحلیل، پیش‌بینی و خروجی', 'crumb' => 'مدیریت شبکه'])

@section('content')
@php
  use App\Support\Persian;
  $maxExpected = max(0.01, collect($forecast)->max('expected'));
@endphp

<div class="card mt-8">
  <div class="card-head"><div><h3>خروجی گزارش (CSV سازگار با Excel)</h3><div class="ch-sub">جلسات، درآمد روزانهٔ ایستگاه‌ها، مالیات بر ارزش افزوده ({{ Persian::digits(config('safircharge.finance.vat_percent', 10)) }}٪) و تسویهٔ شرکا</div></div></div>
  <div class="card-pad">
    <form method="GET" class="flex gap-8 items-center" style="flex-wrap:wrap" onsubmit="return false">
      <label class="fs12 muted">از <input class="finput tnum" type="date" id="expFrom" value="{{ $from }}"></label>
      <label class="fs12 muted">تا <input class="finput tnum" type="date" id="expTo" value="{{ $to }}"></label>
      @foreach(['sessions' => 'جلسات شارژ', 'revenue' => 'درآمد روزانه', 'tax' => 'گزارش مالیاتی', 'settlements' => 'تسویه‌ها'] as $type => $label)
        <button type="button" class="btn btn-sm" onclick="location.href='{{ route('admin.exports', $type) }}?from='+document.getElementById('expFrom').value+'&to='+document.getElementById('expTo').value">{{ $label }}</button>
      @endforeach
    </form>
  </div>
</div>

<div class="grid col-2 mt-16" style="gap:16px">
  <div class="card">
    <div class="card-head">
      <div><h3>پیش‌بینی شلوغی ۲۴ ساعت آینده</h3><div class="ch-sub">میانگین همان ساعتِ همان روز هفته در ۸ هفتهٔ گذشته (تعداد جلسه)</div></div>
      <form method="GET"><select class="fselect" name="station_id" onchange="this.form.submit()"><option value="">کل شبکه</option>@foreach($stations as $st)<option value="{{ $st->id }}" @selected($stationId === $st->id)>{{ $st->name }}</option>@endforeach</select></form>
    </div>
    <div class="card-pad">
      <div style="display:flex;align-items:flex-end;gap:3px;height:140px">
        @foreach($forecast as $slot)
          <div title="{{ Persian::digits($slot['hour']) }}:۰۰ — {{ Persian::digits($slot['expected']) }}" style="flex:1;background:var(--accent);opacity:{{ 0.35 + 0.65 * $slot['expected'] / $maxExpected }};height:{{ max(2, round($slot['expected'] / $maxExpected * 130)) }}px;border-radius:4px 4px 0 0"></div>
        @endforeach
      </div>
      <div style="display:flex;gap:3px" class="fs12 muted">
        @foreach($forecast as $i => $slot)<div style="flex:1;text-align:center">{{ $i % 3 === 0 ? Persian::digits($slot['hour']) : '' }}</div>@endforeach
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><div><h3>کاندید سرویس پیشگیرانه</h3><div class="ch-sub">توقف‌های غیرعادی (EmergencyStop، PowerLoss، ریست…) و هشدارهای خرابی در ۳۰ روز</div></div></div>
    <table class="tbl">
      <thead><tr><th>شارژر</th><th>ایستگاه</th><th>توقف غیرعادی</th><th>هشدار</th></tr></thead>
      <tbody>
      @forelse($maintenance as $m)
        <tr><td class="tnum" dir="ltr">{{ $m['charge_point_id'] }}</td><td>{{ $m['station'] ?? '—' }}</td><td class="tnum">{{ Persian::num($m['abnormal_stops']) }}</td><td class="tnum">{{ Persian::num($m['alerts']) }}</td></tr>
      @empty
        <tr><td colspan="4" class="muted" style="text-align:center;padding:24px">موردی یافت نشد.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="card mt-16">
  <div class="card-head"><div><h3>عملکرد ایستگاه‌ها (۳۰ روز اخیر)</h3><div class="ch-sub">بهره‌وری = زمان اشغال کانکتورها ÷ کل زمان در دسترس</div></div></div>
  <table class="tbl">
    <thead><tr><th>ایستگاه</th><th>شهر</th><th>جلسه</th><th>انرژی (kWh)</th><th>درآمد (تومان)</th><th>میانگین مدت</th><th>بهره‌وری</th></tr></thead>
    <tbody>
    @foreach($performance as $p)
      <tr>
        <td>{{ $p['station'] }}</td><td>{{ $p['city'] }}</td>
        <td class="tnum">{{ Persian::num($p['sessions']) }}</td>
        <td class="tnum">{{ Persian::digits(number_format($p['kwh'], 1)) }}</td>
        <td class="tnum">{{ Persian::money((int) round($p['revenue'] / 10)) }}</td>
        <td class="tnum">{{ Persian::digits($p['avg_minutes']) }} دقیقه</td>
        <td class="tnum">{{ Persian::digits($p['utilization']) }}٪</td>
      </tr>
    @endforeach
    </tbody>
  </table>
</div>
@endsection
