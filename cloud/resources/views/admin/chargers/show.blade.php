@extends('admin.layouts.app', ['page' => 'stations', 'title' => $charger->charge_point_id, 'crumb' => $charger->station->name])

@section('content')
<div class="card">
    <div class="card-head"><h3>{{ $charger->charge_point_id }}</h3></div>
    <div class="card-pad">
        <p><strong>ایستگاه:</strong> {{ $charger->station->name }}</p>
        <p><strong>وضعیت:</strong> {{ $charger->status }} — {{ $charger->is_online ? 'آنلاین' : 'آفلاین' }}</p>
        <table class="tbl mt-16">
            <thead><tr><th>#</th><th>OCPP Status</th><th>عملیاتی</th></tr></thead>
            <tbody>
                @foreach($charger->connectors as $c)
                <tr><td class="tnum">{{ $c->connector_number }}</td><td>{{ $c->ocpp_status }}</td><td>{{ $c->operational_status }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
