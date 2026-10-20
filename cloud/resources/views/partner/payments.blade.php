@extends('partner.layouts.app', ['page' => 'payments', 'title' => 'درآمد و پرداختی‌ها', 'crumb' => 'پنل شرکا'])

@section('content')
@php use App\Support\Persian; @endphp

<div class="card">
    <div class="card-head"><h3>تسویه‌ها</h3></div>
    <div class="card-pad" style="padding:0">
        <table class="tbl">
            <thead><tr><th>شماره</th><th>دوره</th><th>ناخالص</th><th>سهم شما</th><th>انرژی</th><th>وضعیت</th></tr></thead>
            <tbody>
            @foreach($settlements as $s)
                <tr>
                    <td class="tnum">{{ $s->settlement_number }}</td>
                    <td class="tnum">{{ $s->period_start?->format('Y/m') }}</td>
                    <td class="tnum">{{ Persian::money($s->gross_revenue / 10) }}</td>
                    <td class="tnum">{{ Persian::money($s->partner_share / 10) }}</td>
                    <td class="tnum">{{ Persian::kwh($s->energy_kwh) }}</td>
                    <td><span class="pill">{{ $s->status }}</span></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
