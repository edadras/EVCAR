@extends('partner.layouts.app', ['page' => 'reports', 'title' => 'آمار و گزارش', 'crumb' => 'پنل شرکا'])

@section('content')
@php use App\Support\Persian; @endphp

<div class="card">
    <div class="card-head"><h3>به تفکیک ایستگاه</h3></div>
    <div class="card-pad" style="padding:0">
        <table class="tbl">
            <thead><tr><th>ایستگاه</th><th>جلسات</th><th>انرژی</th><th>سهم شما</th></tr></thead>
            <tbody>
            @foreach($byStation as $row)
                <tr>
                    <td>{{ $row['station']->name }}</td>
                    <td class="tnum">{{ Persian::digits($row['sessions']) }}</td>
                    <td class="tnum">{{ Persian::kwh($row['energy']) }}</td>
                    <td class="tnum">{{ Persian::money($row['share'] / 10) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
