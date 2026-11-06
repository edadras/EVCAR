@extends('admin.layouts.app', ['page' => 'external-stations', 'title' => 'اپراتورهای شارژ', 'crumb' => 'ایستگاه‌های سایر اپراتورها'])

@section('content')
@php use App\Support\Persian; @endphp

<div class="flex between items-center mt-8" style="margin-bottom:18px">
    <div>
        <h3 style="font-size:16px;margin:0">اپراتورها و برندهای شارژ</h3>
        <div class="muted fs12">لوگوی هر اپراتور روی نقشه برای ایستگاه‌هایش نمایش داده می‌شود</div>
    </div>
    <div class="flex gap-8">
        <a href="{{ route('admin.external-stations.index') }}" class="btn btn-sm">ایستگاه‌ها</a>
        <a href="{{ route('admin.ev-operators.create') }}" class="btn btn-sm btn-primary">اپراتور جدید</a>
    </div>
</div>

<div class="card">
    <div class="table-wrap" style="overflow-x:auto">
        <table class="tbl">
            <thead><tr><th>لوگو</th><th>اپراتور</th><th>شبکه / اپ</th><th>تماس</th><th>ایستگاه‌ها</th><th>وضعیت</th><th></th></tr></thead>
            <tbody>
                @forelse($operators as $op)
                <tr>
                    <td><img src="{{ $op->logoUrl() }}" alt="" style="width:40px;height:40px;object-fit:contain;border-radius:10px;border:1px solid var(--border-2);background:#fff"></td>
                    <td>
                        <div class="fw7">{{ $op->name }}</div>
                        <div class="muted fs11" style="max-width:300px">{{ $op->description }}</div>
                    </td>
                    <td class="fs12">{{ $op->network ?? '—' }}@if($op->app_name)<div class="muted">اپ: {{ $op->app_name }}</div>@endif</td>
                    <td class="fs12">
                        @if($op->website)<div><a href="{{ $op->website }}" target="_blank" rel="noopener" dir="ltr">{{ preg_replace('#^https?://(www\.)?#', '', $op->website) }}</a></div>@endif
                        @if($op->phone)<div dir="ltr" style="text-align:right">{{ $op->phone }}</div>@endif
                    </td>
                    <td class="tnum"><a href="{{ route('admin.external-stations.index', ['operator' => $op->id]) }}">{{ Persian::digits($op->active_stations_count) }} / {{ Persian::digits($op->stations_count) }}</a></td>
                    <td>@if($op->is_active)<span class="pill pill-green">فعال</span>@else<span class="pill pill-amber">مخفی</span>@endif</td>
                    <td>
                        <div class="flex gap-8">
                            <a href="{{ route('admin.ev-operators.edit', $op) }}" class="btn btn-sm btn-primary">ویرایش</a>
                            <a href="{{ route('admin.external-stations.create', ['operator' => $op->id]) }}" class="btn btn-sm">+ ایستگاه</a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="muted" style="padding:24px;text-align:center">اپراتوری ثبت نشده است.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
