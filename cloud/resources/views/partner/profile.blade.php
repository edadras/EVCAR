@extends('partner.layouts.app', ['page' => '', 'title' => 'پروفایل و حساب', 'crumb' => 'پنل شرکا'])

@section('content')
@php use App\Support\Persian; @endphp

<div class="card">
    <div class="card-pad">
        <p><strong>نام شریک:</strong> {{ $partner->name }}</p>
        <p><strong>کد:</strong> {{ $partner->code }}</p>
        <p><strong>ایمیل تماس:</strong> {{ $partner->contact_email }}</p>
        <p><strong>سهم درآمد:</strong> {{ Persian::digits($partner->revenue_share_percent) }}٪</p>
        <p><strong>کاربر متصل:</strong> {{ $user->name }} ({{ $user->email }})</p>
        <p><strong>شماره شبا:</strong> {{ $partner->bank_iban ?? '—' }}</p>
    </div>
</div>
@endsection
