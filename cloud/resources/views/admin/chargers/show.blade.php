@php use App\Models\Charger; use App\Http\Controllers\Admin\ChargerController; @endphp
@extends('admin.layouts.app', ['page' => 'stations', 'title' => ($charger->meta['name'] ?? null) ?: $charger->charge_point_id, 'crumb' => $charger->station->name])

@section('content')
<div class="flex between items-center mt-8" style="margin-bottom:18px">
  <a href="{{ route('admin.stations.show', $charger->station) }}" class="btn btn-sm">بازگشت به ایستگاه</a>
  <div class="flex gap-8">
    <a href="{{ route('admin.remote', ['station' => $charger->station_id]) }}" class="btn btn-sm">کنترل از راه دور</a>
    <a href="{{ route('admin.chargers.config', $charger) }}" class="btn btn-sm">تنظیمات دستگاه (OCPP)</a>
    <a href="{{ route('admin.chargers.edit', $charger) }}" class="btn btn-sm btn-primary">ویرایش شارژر و کانکتورها</a>
  </div>
</div>

<div class="card">
  <div class="card-head"><div><h3 class="tnum" dir="ltr" style="text-align:right">{{ $charger->charge_point_id }}</h3>@if(!empty($charger->meta['name']))<div class="ch-sub">{{ $charger->meta['name'] }}</div>@endif</div>
    <span class="pill {{ $charger->is_online ? 'pill-green' : 'pill-amber' }}"><span class="pdot"></span>{{ $charger->is_online ? 'آنلاین' : 'آفلاین' }}</span></div>
  <div class="card-pad">
    <div class="form-grid">
      <div><div class="fs12 muted">ایستگاه</div><div class="fw8">{{ $charger->station->name }}</div></div>
      <div><div class="fs12 muted">سازنده / مدل</div><div class="fw8">{{ trim($charger->vendor.' '.$charger->model) ?: '—' }}</div></div>
      <div><div class="fs12 muted">نوع کانکتور</div><div class="fw8">{{ Charger::CONNECTOR_TYPES[$charger->connector_type] ?? ($charger->connector_type ?: '—') }}</div></div>
      <div><div class="fs12 muted">حداکثر توان</div><div class="fw8 tnum">{{ $charger->max_power_kw ?? '—' }} kW</div></div>
      <div><div class="fs12 muted">شماره سریال</div><div class="fw8 tnum">{{ $charger->serial_number ?: '—' }}</div></div>
      <div><div class="fs12 muted">وضعیت</div><div class="fw8">{{ ChargerController::CONNECTOR_STATUSES[$charger->status] ?? $charger->status }}</div></div>
    </div>
  </div>
</div>

<div class="card mt-16">
  <div class="card-head"><h3>کانکتورها</h3></div>
  <div class="card-pad" style="padding:0">
    <table class="tbl">
      <thead><tr><th>#</th><th>نام</th><th>توان</th><th>وضعیت OCPP</th><th>عملیاتی</th><th>لینک QR</th></tr></thead>
      <tbody>
        @foreach($charger->connectors->sortBy('connector_number') as $c)
        <tr>
          <td class="tnum">{{ $c->connector_number }}</td>
          <td>{{ $c->meta['name'] ?? '—' }}</td>
          <td class="tnum">{{ $c->max_power_kw ?? $charger->max_power_kw ?? '—' }} kW</td>
          <td>{{ $c->ocpp_status }}</td>
          <td>{{ ChargerController::CONNECTOR_STATUSES[$c->operational_status] ?? $c->operational_status }}</td>
          <td class="tnum" dir="ltr" style="text-align:right">@if(isset($qr[$c->id])){{ $qr[$c->id]->scanUrl() }}@else — @endif</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
