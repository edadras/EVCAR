@extends('admin.layouts.app', ['page' => 'stations', 'title' => $station->name, 'crumb' => 'ایستگاه‌ها'])

@section('content')
<div class="flex between items-center mt-8" style="margin-bottom:18px">
    <div>
        <span class="pill">{{ $station->code }}</span>
        <span class="muted fs13" style="margin-right:8px">{{ $station->city }}</span>
    </div>
    <div class="flex gap-8">
        <a href="{{ route('admin.remote', ['station' => $station->id]) }}" class="btn btn-sm">کنترل از راه دور</a>
        <a href="{{ route('admin.stations.edit', $station) }}" class="btn btn-sm">ویرایش</a>
        <a href="{{ route('admin.stations.chargers.create', $station) }}" class="btn btn-sm btn-primary">+ شارژر</a>
    </div>
</div>

<div class="card">
    <div class="card-head"><h3>شارژرها و کانکتورها</h3></div>
    <div class="card-pad" style="padding:0">
        <table class="tbl">
            <thead><tr><th>Charge Point ID</th><th>مدل</th><th>توان</th><th>آنلاین</th><th>کانکتور</th><th></th></tr></thead>
            <tbody>
                @forelse($station->chargers as $charger)
                <tr>
                    <td class="tnum">{{ $charger->charge_point_id }}</td>
                    <td>{{ $charger->vendor }} {{ $charger->model }}</td>
                    <td class="tnum">{{ $charger->max_power_kw ?? '—' }} kW</td>
                    <td>{{ $charger->is_online ? 'بله' : 'خیر' }}</td>
                    <td class="tnum">{{ $charger->connectors->count() }}</td>
                    <td><a href="{{ route('admin.chargers.show', $charger) }}" class="btn btn-sm">جزئیات</a></td>
                </tr>
                @empty
                <tr><td colspan="6" class="muted" style="text-align:center;padding:24px">شارژری ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
