@extends('partner.layouts.app', ['page' => 'stations', 'title' => 'ایستگاه‌ها و دستگاه‌ها', 'crumb' => 'پنل شرکا'])

@section('content')
@php use App\Support\Persian; @endphp

<div class="grid col-2-1">
    <div class="card">
        <div class="card-head"><h3>نقشه ایستگاه‌های شما</h3></div>
        <div class="card-pad">@include('admin.partials.leaflet-map', ['markers' => $mapMarkers, 'mapId' => 'partnerMap'])</div>
    </div>
    <div class="card">
        <div class="card-head"><h3>فهرست</h3></div>
        <div class="card-pad" style="padding:0">
            <table class="tbl">
                <thead><tr><th>نام</th><th>شارژر</th><th>وضعیت</th></tr></thead>
                <tbody>
                @foreach($stations as $st)
                    <tr>
                        <td>{{ $st->name }}<div class="muted fs12">{{ $st->city }}</div></td>
                        <td class="tnum">{{ Persian::digits($st->chargers_count) }}</td>
                        <td><span class="pill">{{ $st->status }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
