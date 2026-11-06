@extends('admin.layouts.app', ['page' => 'ev-catalog', 'title' => 'کاتالوگ خودروهای برقی', 'crumb' => 'سامانه و ارتباطات'])

@section('content')
@php use App\Support\Persian; @endphp

<div class="flex between items-center mt-8" style="margin-bottom:18px">
    <div>
        <h3 style="font-size:16px;margin:0">کاتالوگ مصرف خودروهای برقی</h3>
        <div class="muted fs12">مصرف پایه واقعی برای مسیریاب — برد WLTP/واقعی فقط مرجع</div>
    </div>
    <a href="{{ route('admin.ev-catalog.create') }}" class="btn btn-sm btn-primary">
        <span class="bic" data-ic="plus"></span> مدل جدید
    </a>
</div>

@if(!$fromDatabase)
<div class="alert alert-danger" style="margin-bottom:16px;padding:12px 16px;background:var(--orange-soft);border-radius:var(--r);color:var(--orange)">
    کاتالوگ خودرو خالی است. برای پر کردن دادهٔ اولیه:
    <code dir="ltr" style="display:block;margin-top:6px">php artisan db:seed --class=EvCatalogVehicleSeeder</code>
    یا از همین صفحه مدل‌ها را دستی اضافه کنید.
</div>
@endif

<div class="card">
    <div class="card-head">
        <div><h3>فهرست مدل‌ها</h3><div class="ch-sub">{{ Persian::digits($vehicles->count()) }} مدل</div></div>
    </div>
    <div class="table-wrap" style="overflow-x:auto">
        <table class="tbl">
            <thead>
                <tr>
                    <th>مدل</th>
                    <th>باتری</th>
                    <th>سوکت / توان شارژ</th>
                    <th>مصرف پایه</th>
                    <th>برد رسمی</th>
                    <th>برد واقعی</th>
                    <th>بدترین حالت</th>
                    <th>وضعیت</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($vehicles as $v)
                @php
                    $c = $v->consumption ?? [];
                    $base = $v->consumption_base ?? $c['base'] ?? $c['mixed'] ?? null;
                    $realRange = ($v->range_real_min_km && $v->range_real_max_km)
                        ? Persian::num($v->range_real_min_km).'–'.Persian::num($v->range_real_max_km)
                        : '—';
                @endphp
                <tr>
                    <td>
                        <div class="fw7">{{ $v->name }}</div>
                        <div class="muted fs11">{{ $v->importer ?? '' }}{{ $v->importer && $v->motor_kw ? ' · ' : '' }}{{ $v->motor_kw ? Persian::num($v->motor_kw).' kW' : '' }} {{ $v->drivetrain ?? '' }}</div>
                        <div class="muted fs11" dir="ltr" style="text-align:right">{{ $v->slug }}</div>
                    </td>
                    <td class="tnum">{{ Persian::num((float) $v->battery_kwh) }} <span class="muted fs11">{{ $v->battery_chemistry }}</span></td>
                    <td class="fs12">
                        @if($v->dc_connector || $v->dc_max_kw)
                        <div>DC: {{ $v->dc_connector ?? '؟' }}{{ $v->dc_max_kw ? ' · '.Persian::num((float) $v->dc_max_kw).' kW' : '' }}</div>
                        @endif
                        @if($v->ac_connector || $v->ac_max_kw)
                        <div class="muted">AC: {{ $v->ac_connector ?? '؟' }}{{ $v->ac_max_kw ? ' · '.Persian::num((float) $v->ac_max_kw).' kW' : '' }}</div>
                        @endif
                        @if(!$v->dc_connector && !$v->ac_connector && !$v->dc_max_kw && !$v->ac_max_kw)
                        <span class="muted">—</span>
                        @endif
                    </td>
                    <td class="tnum">{{ ($v->range_official_km ?? $v->range_wltp_km) ? Persian::num($v->range_official_km ?? $v->range_wltp_km) : '—' }} <span class="muted fs11">{{ $v->range_standard ?? ($v->range_wltp_km ? 'WLTP' : '') }}</span></td>
                    <td class="tnum">{{ $realRange }}</td>
                    <td class="tnum">{{ $v->range_worst_km ? Persian::num($v->range_worst_km) : '—' }}</td>
                    <td>
                        @if($v->is_active)
                        <span class="pill pill-green">فعال</span>
                        @else
                        <span class="pill pill-amber">غیرفعال</span>
                        @endif
                        @if($v->notes)
                        <div class="muted fs11" style="max-width:220px;margin-top:4px">{{ $v->notes }}</div>
                        @endif
                    </td>
                    <td>
                        <div class="flex gap-8">
                            <a href="{{ route('admin.ev-catalog.edit', $v) }}" class="btn btn-sm btn-primary">ویرایش</a>
                            <form method="POST" action="{{ route('admin.ev-catalog.destroy', $v) }}" onsubmit="return confirm('مدل «{{ $v->name }}» حذف شود؟');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm" style="color:var(--red)">حذف</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="muted" style="padding:24px;text-align:center">مدلی ثبت نشده است.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('styles')
<style>.bic{display:inline-grid;width:16px;height:16px}.bic svg{width:16px;height:16px}</style>
@endpush
